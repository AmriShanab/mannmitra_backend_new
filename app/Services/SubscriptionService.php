<?php

namespace App\Services;

use App\Interfaces\SubscriptionRespositoryInterface;
use Carbon\Carbon;
use App\Models\User;

// use Illuminate\Support\Carbon;

class SubscriptionService
{
    protected $subscriptionRepo;

    public function __construct(SubscriptionRespositoryInterface $subscriptionRepo)
    {
        $this->subscriptionRepo = $subscriptionRepo;
    }

    public function processNewSubscription($user, $planType, $txnId, $amount)
    {
        $startDate = Carbon::now();
        $expiryDate = ($planType == 'monthly')
            ? $startDate->copy()->addMonth()
            : $startDate->copy()->addYear();

        $subscription = $this->subscriptionRepo->createSubscription([
            'user_id' => $user->id,
            'plan_type' => $planType,
            'transaction_id' => $txnId,
            'amount' => $amount,
            'starts_at' => $startDate,
            'expires_at' => $expiryDate,
            'status' => 'active',
        ]);

        $user->update(['is_paid' => true]);

        return $subscription;
    }
}