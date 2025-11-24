<?php

namespace App\Services;

use App\Interfaces\SessionRepositaryInterface;
use App\Interfaces\UserRepositaryInterface;

class AuthService
{
    protected $userRepositary;
    protected $sessionRepositary;

    public function __construct(
        UserRepositaryInterface $userRepositary,
        SessionRepositaryInterface $sessionRepositary    
    )
    {
        $this->userRepositary = $userRepositary;
        $this->sessionRepositary = $sessionRepositary;
    }

    public function handleAnonymousLogin(array $data)
    {
        $user = null;
        if(!empty($data['fcm_token'])) {
            $user = $this->userRepositary->findByFcmToken($data['fcm_token']);
        }

        if(!$user)
        {
            $user = $this->userRepositary->createAnonymousUser($data);
        }

        $token = $user->createToken('mannmitra_auth_token')->plainTextToken;

        $session = $this->sessionRepositary->createSession([
            'user_id' => $user->id,
            'type' => $data['session_type'] ?? 'text',
        ]);

        return [
            'user' => $user,
            'token' => $token,
            'current_session' => $session,
        ];
    }
}