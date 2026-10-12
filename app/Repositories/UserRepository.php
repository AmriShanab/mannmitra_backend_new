<?php

namespace App\Repositories;

use App\Models\User;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Str;
use App\Support\Language;

class UserRepository implements UserRepositoryInterface
{
    public function createAnonymousUser(array $data)
    {
        return User::create([
            'name' => 'Guest-' . Str::random(5),
            'anonymous_id' => Str::uuid(),
            'role' => 'anonymous',
            'fcmToken' => $data['fcmToken'] ?? null,
            'language' => Language::normalize($data['languageCode'] ?? null),
        ]);
    }
    
    public function findByFcmToken($fcmToken)
    {
        return User::where('fcmToken', $fcmToken)->first();
    }

    public function updateLanguage($userId, $languageCode)
    {
        $user = User::findOrFail($userId);
        $user->language = Language::normalize($languageCode);
        $user->save();

        return $user;
    }
}
