<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    use ApiResponse;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function plans()
    {
        return $this->successResponse(config('services.pricing.plans'), 'Plans retrieved successfully');
    }

    public function createOrder(Request $request)
    {
        $request->validate(['plan_type' => 'required|in:monthly,yearly']);

        try {
            $order = $this->subscriptionService->createOrder(Auth::user(), $request->plan_type);
            return $this->successResponse($order, 'Order created successfully', 201);
        } catch (\Throwable $e) {
            Log::error('Subscription order creation failed: ' . $e->getMessage());
            return $this->errorResponse('Unable to start payment. Please try again.', 502);
        }
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'transaction_id' => 'required|string',
            'signature' => 'required|string',
        ]);

        try {
            $subscription = $this->subscriptionService->confirmPurchase(
                Auth::user(),
                $request->order_id,
                $request->transaction_id,
                $request->signature
            );

            return $this->successResponse($this->payload($subscription), 'Subscription created successfully');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            Log::error('Subscription purchase failed: ' . $e->getMessage());
            return $this->errorResponse('Subscription failed. If you were charged, please contact support.', 500);
        }
    }

    public function status()
    {
        $user = Auth::user();
        $subscription = $this->subscriptionService->getUserSubscription($user->id);

        if (!$subscription) {
            return response()->json(['status' => false, 'message' => 'No active subscription found'], 404);
        }

        return $this->successResponse($this->payload($subscription), 'Active subscription retrieved successfully');
    }

    /**
     * Razorpay webhook: backstop for when the app dies before calling /subscription/purchase.
     */
    public function webhook(Request $request)
    {
        $secret = config('services.razorpay.webhook_secret');
        if (!$secret) {
            return response()->json(['status' => false, 'message' => 'Webhook not configured'], 503);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            return response()->json(['status' => false, 'message' => 'Invalid signature'], 400);
        }

        if ($request->input('event') === 'order.paid') {
            $order = $request->input('payload.order.entity', []);
            $payment = $request->input('payload.payment.entity', []);
            $notes = $order['notes'] ?? [];

            if (is_array($notes) && ($notes['type'] ?? null) === 'subscription' && !empty($payment['id'])) {
                $user = User::find($notes['user_id'] ?? null);
                if ($user) {
                    try {
                        $this->subscriptionService->activate($user, $notes['plan_type'] ?? '', [
                            'id' => $order['id'],
                            'amount' => $order['amount'],
                        ], $payment['id']);
                    } catch (\Throwable $e) {
                        Log::error('Razorpay webhook activation failed: ' . $e->getMessage());
                        return response()->json(['status' => false], 500);
                    }
                }
            }
        }

        return response()->json(['status' => true]);
    }

    private function payload($subscription): array
    {
        return array_merge($subscription->toArray(), [
            'is_paid' => $subscription->status === 'active' && $subscription->expires_at->isFuture(),
        ]);
    }
}
