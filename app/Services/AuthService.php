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
            if (!empty($data['fcmToken'])) {
                $user = $this->userRepositary->findByFcmToken($data['fcmToken']);
            }

            if (!$user) {
                // dd($data);
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
