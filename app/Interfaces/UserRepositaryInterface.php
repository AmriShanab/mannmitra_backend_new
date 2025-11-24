<?php

namespace App\Interfaces;

interface UserRepositaryInterface
{
    public function createAnonymousUser(array $data);
    public function findByFcmToken($fcmToken);
}
