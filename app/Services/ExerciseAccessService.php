<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Activities;
use App\Models\ExerciseCredit;
use App\Models\ExerciseRun;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Pay-per-exercise access: each paid pack unlocks N completed sessions of one exercise.
 * Subscribers use exercises without consuming credits.
 */
class ExerciseAccessService
{
    // A finished run counts as "recent" for the wrap-up turn after the exercise ends.
    private const RECENT_RUN_MINUTES = 15;

    public function __construct(protected PaymentGatewayInterface $paymentGateway)
    {
    }

    public function pricing(): array
    {
        return config('services.pricing.exercise');
    }

    public function paywallEnabled(): bool
    {
        return (bool) ($this->pricing()['enabled'] ?? true);
    }

    /** True when the user needs no credits: paywall switched off, or an active subscriber. */
    public function hasFreeAccess(User $user): bool
    {
        return !$this->paywallEnabled() || $this->isSubscriber($user);
    }

    public function isSubscriber(User $user): bool
    {
        return Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function sessionsLeft(User $user, string $activityId): int
    {
        return (int) ExerciseCredit::where('user_id', $user->id)
            ->where('activity_id', $activityId)
            ->selectRaw('COALESCE(SUM(sessions_total - sessions_used), 0) as left_count')
            ->value('left_count');
    }

    /**
     * Per-exercise access info for the catalog, keyed by activity_id.
     */
    public function accessMap(User $user, $activities): array
    {
        $price = $this->pricing();
        $subscriber = $this->hasFreeAccess($user);

        $left = ExerciseCredit::where('user_id', $user->id)
            ->selectRaw('activity_id, SUM(sessions_total - sessions_used) as left_count')
            ->groupBy('activity_id')
            ->pluck('left_count', 'activity_id');

        $map = [];
        foreach ($activities as $activity) {
            $map[$activity->activity_id] = [
                'price' => $price['amount'],
                'pack_sessions' => $price['sessions'],
                'sessions_left' => (int) ($left[$activity->activity_id] ?? 0),
                'is_free' => $subscriber,
            ];
        }
        return $map;
    }

    // ------------------------------------------------------------------ runs

    /**
     * Start (or resume) a run. Returns null when the user has neither credits nor a subscription.
     */
    public function ensureRun(User $user, string $activityId): ?ExerciseRun
    {
        $active = $this->activeRun($user, $activityId);
        if ($active) {
            return $active;
        }

        $freePass = $this->hasFreeAccess($user);
        if (!$freePass && $this->sessionsLeft($user, $activityId) < 1) {
            return null;
        }

        // Only one live run per user: close anything left half-done.
        ExerciseRun::where('user_id', $user->id)->where('status', 'started')->update(['status' => 'abandoned']);

        return ExerciseRun::create([
            'user_id' => $user->id,
            'activity_id' => $activityId,
            'status' => 'started',
            'free_pass' => $freePass,
        ]);
    }

    public function activeRun(User $user, ?string $activityId = null): ?ExerciseRun
    {
        return ExerciseRun::where('user_id', $user->id)
            ->where('status', 'started')
            ->when($activityId, fn($q) => $q->where('activity_id', $activityId))
            ->latest('id')
            ->first();
    }

    /**
     * True while an exercise is running or just finished (for the wrap-up chat turn).
     */
    public function hasLiveRun(User $user): bool
    {
        return ExerciseRun::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('status', 'started')
                    ->orWhere(fn($q) => $q->where('status', 'completed')
                        ->where('completed_at', '>=', now()->subMinutes(self::RECENT_RUN_MINUTES)));
            })
            ->exists();
    }

    /**
     * Mark the active run done and consume one session (idempotent per run).
     */
    public function completeActiveRun(User $user): ?ExerciseRun
    {
        return DB::transaction(function () use ($user) {
            $run = ExerciseRun::where('user_id', $user->id)
                ->where('status', 'started')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$run) {
                return null;
            }

            if (!$run->free_pass) {
                // Oldest pack first.
                $credit = ExerciseCredit::where('user_id', $user->id)
                    ->where('activity_id', $run->activity_id)
                    ->whereColumn('sessions_used', '<', 'sessions_total')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($credit) {
                    $credit->increment('sessions_used');
                    $run->exercise_credit_id = $credit->id;
                }
            }

            $run->status = 'completed';
            $run->completed_at = now();
            $run->save();

            return $run;
        });
    }

    // -------------------------------------------------------------- purchases

    public function createOrder(User $user, string $activityId): array
    {
        if (!Activities::where('activity_id', $activityId)->exists()) {
            throw new \DomainException('Unknown exercise', 404);
        }

        $price = $this->pricing();
        $order = $this->paymentGateway->createOrder($price['amount'] * 100, 'INR', [
            'type' => 'exercise',
            'user_id' => (string) $user->id,
            'activity_id' => $activityId,
        ]);

        return [
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'] ?? 'INR',
            'key' => $order['key'] ?? config('services.razorpay.key'),
            'activity_id' => $activityId,
            'sessions' => $price['sessions'],
        ];
    }

    public function confirmPurchase(User $user, string $orderId, string $paymentId, string $signature): ExerciseCredit
    {
        $valid = $this->paymentGateway->verifyPayment([
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'signature' => $signature,
        ]);

        if (!$valid) {
            throw new \DomainException('Invalid payment signature', 400);
        }

        $order = $this->paymentGateway->fetchOrder($orderId);
        $notes = $order['notes'] ?? [];

        if (($notes['type'] ?? null) !== 'exercise' || (string) ($notes['user_id'] ?? '') !== (string) $user->id) {
            throw new \DomainException('Order does not belong to this user', 403);
        }

        return $this->grant($user, $notes['activity_id'] ?? '', $order, $paymentId);
    }

    /**
     * Grant a pack. Idempotent on razorpay_order_id (checkout confirm and webhook can both call it).
     */
    public function grant(User $user, string $activityId, array $order, string $paymentId): ExerciseCredit
    {
        $price = $this->pricing();

        if ((int) $order['amount'] !== (int) $price['amount'] * 100) {
            throw new \DomainException('Amount mismatch', 400);
        }
        if (!Activities::where('activity_id', $activityId)->exists()) {
            throw new \DomainException('Unknown exercise', 404);
        }

        try {
            return DB::transaction(function () use ($user, $activityId, $order, $paymentId, $price) {
                $existing = ExerciseCredit::where('razorpay_order_id', $order['id'])->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }

                return ExerciseCredit::create([
                    'user_id' => $user->id,
                    'activity_id' => $activityId,
                    'razorpay_order_id' => $order['id'],
                    'transaction_id' => $paymentId,
                    'amount' => $price['amount'],
                    'sessions_total' => $price['sessions'],
                    'sessions_used' => 0,
                ]);
            });
        } catch (QueryException $e) {
            // Concurrent duplicate confirm (unique order id): return the stored row.
            $existing = ExerciseCredit::where('razorpay_order_id', $order['id'])->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
    }
}
