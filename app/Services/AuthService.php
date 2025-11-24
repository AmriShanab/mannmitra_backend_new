<?php

namespace App\Services;

use App\Interfaces\SessionRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Traits\ApiResponse;

class AuthService
{
    use ApiResponse;
    protected $userRepositary;
    protected $sessionRepositary;

    public function __construct(
        UserRepositoryInterface $userRepositary,
        SessionRepositoryInterface $sessionRepositary
    ) {
        $this->userRepositary = $userRepositary;
        $this->sessionRepositary = $sessionRepositary;
    }

    public function handleAnonymousLogin(array $data)
    {
        try {
            $user = null;
            if (!empty($data['fcm_token'])) {
                $user = $this->userRepositary->findByFcmToken($data['fcm_token']);
            }

            if (!$user) {
                $user = $this->userRepositary->createAnonymousUser($data);
            }

            $token = $user->createToken('mannmitra_auth_token')->plainTextToken;

            $session = $user->sessions()->create([
                'type' => $data['session_type'] ?? 'text',
            ]);

            return $this->successResponse([
                'user' => $user,
                'session' => $session,
                'anonymous_token' => $token,
            ], 'Anonymous Session Started Successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
