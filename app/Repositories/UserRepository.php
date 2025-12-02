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
            'fcmToken' => $data['fcmToken'] ?? null,
            'language' => $data['languageCode'] ?? 'en',
        ]);
    }
    
    public function findByFcmToken($fcmToken)
    {
        return User::where('fcmToken', $fcmToken)->first();
    }

    public function updateLanguage($userId, $languageCode)
    {
        $user = User::findOrFail($userId);
        $user->language = $languageCode;
        $user->save();

        return $user;
    }
}
