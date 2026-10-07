<?php

namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\SubscriptionRespositoryInterface;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    protected $subscriptionRepo;
    protected $paymentGateway;

    public function __construct(SubscriptionRespositoryInterface $subscriptionRepo, PaymentGatewayInterface $paymentGateway)
    {
        $this->subscriptionRepo = $subscriptionRepo;
        $this->paymentGateway = $paymentGateway;
    }

    public function plan(string $planType): array
    {
        $plan = config("services.pricing.plans.$planType");
        if (!$plan) {
            throw new \DomainException('Unknown plan', 400);
        }
        return $plan;
    }

    /**
     * Create a Razorpay order priced on the server.
     */
    public function createOrder(User $user, string $planType): array
    {
        $plan = $this->plan($planType);

        $order = $this->paymentGateway->createOrder($plan['amount'] * 100, 'INR', [
            'type' => 'subscription',
            'user_id' => (string) $user->id,
            'plan_type' => $planType,
        ]);

        return [
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'] ?? 'INR',
            'key' => $order['key'] ?? config('services.razorpay.key'),
            'plan_type' => $planType,
        ];
    }

    /**
     * Verify the checkout signature, confirm the order is ours, then activate.
     */
    public function confirmPurchase(User $user, string $orderId, string $paymentId, string $signature): Subscription
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

        if (($notes['type'] ?? null) !== 'subscription' || (string) ($notes['user_id'] ?? '') !== (string) $user->id) {
            throw new \DomainException('Order does not belong to this user', 403);
        }

        return $this->activate($user, $notes['plan_type'] ?? '', $order, $paymentId);
    }

    /**
     * Activate (or extend) a subscription. Idempotent on razorpay_order_id.
     */
    public function activate(User $user, string $planType, array $order, string $paymentId): Subscription
    {
        $plan = $this->plan($planType);

        if ((int) $order['amount'] !== (int) $plan['amount'] * 100) {
            throw new \DomainException('Amount mismatch', 400);
        }

        try {
            return DB::transaction(function () use ($user, $planType, $plan, $order, $paymentId) {
                $existing = Subscription::where('razorpay_order_id', $order['id'])->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }

                $current = Subscription::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('expires_at')
                    ->lockForUpdate()
                    ->first();

                // Renewing early stacks on top of the remaining time.
                $start = $current ? Carbon::parse($current->expires_at) : Carbon::now();
                $expiry = $plan['period'] === 'year' ? $start->copy()->addYear() : $start->copy()->addMonth();

                $subscription = $this->subscriptionRepo->createSubscription([
                    'user_id' => $user->id,
                    'plan_type' => $planType,
                    'transaction_id' => $paymentId,
                    'razorpay_order_id' => $order['id'],
                    'amount' => $plan['amount'],
                    'starts_at' => $start,
                    'expires_at' => $expiry,
                    'status' => 'active',
                ]);

                $user->update(['is_paid' => true]);

                return $subscription;
            });
        } catch (QueryException $e) {
            // Concurrent duplicate confirm (unique order/transaction id): return the stored row.
            $existing = Subscription::where('razorpay_order_id', $order['id'])->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
    }

    public function getUserSubscription($userId)
    {
        $subscription = $this->subscriptionRepo->getActiveSubscriptionByUserId($userId);

        if (!$subscription) {
            // Keep the flag honest when a subscription has lapsed.
            User::where('id', $userId)->where('is_paid', true)->update(['is_paid' => false]);
        }

        return $subscription;
    }

    /**
     * Mark lapsed subscriptions expired and clear is_paid for users with nothing active.
     */
    public function expireLapsed(): int
    {
        $expired = Subscription::where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired']);

        User::where('is_paid', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('subscriptions')
                    ->whereColumn('subscriptions.user_id', 'users.id')
                    ->where('subscriptions.status', 'active')
                    ->where('subscriptions.expires_at', '>', now());
            })
            ->update(['is_paid' => false]);

        return $expired;
    }
}
