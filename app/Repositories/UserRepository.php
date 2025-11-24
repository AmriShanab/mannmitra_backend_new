<?php

namespace App\Repositories;

use App\Models\User;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    public function createAnonymousUser(array $data)
    {
        return User::create([
            'name' => 'Guest-' . Str::random(5),
            'anonymous_id' => Str::uuid(),
            'role' => 'anonymous',
            'fcm_token' => $data['fcm_token'] ?? null,
            'language' => $data['language'] ?? 'en',
        ]);
    }
    
    public function findByFcmToken($fcmToken)
    {
        return User::where('fcm_token', $fcmToken)->first();
    }
}
