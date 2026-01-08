<?php

namespace App\Repositories;

use App\Interfaces\SubscriptionRespositoryInterface;
use App\Models\Subscription;

use function Symfony\Component\Clock\now;

class SubscriptionRepository implements SubscriptionRespositoryInterface
{
    public function createSubscription(array $data)
    {
        return Subscription::create($data);
    }

    public function getActiveSSubscription($userId)
    {
        return Subscription::where('user_id', $userId)
                            ->where('status', 'active')
                            ->where('expires_at', '>', now())
                            ->first();
    }
}