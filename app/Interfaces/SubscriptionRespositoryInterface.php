<?php

namespace App\Interfaces;

interface SubscriptionRespositoryInterface
{
    public function createSubscription(array $data);
    public function getActiveSSubscription($userId);
}