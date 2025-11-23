<?php

namespace App\Services;

use App\Interfaces\UserRepositaryInterface;

class AuthService
{
    protected $userRepositary;

    public function __construct(UserRepositaryInterface $userRepositary)
    {
        $this->userRepositary = $userRepositary;
    }

    public function handleAnonymousLogin(array $data)
    {
        $user = null;
        if(!empty($data['device_id'])) {
            $user = $this->userRepositary->findByDeviceId($data['device_id']);
        }

        if(!$user)
        {
            $user = $this->userRepositary->createAnonymousUser($data);
        }

        $token = $user->createToken('mannmitra_auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}