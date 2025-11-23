<?php

namespace App\Repositaries;

use App\Models\User;
use App\Interfaces\UserRepositaryInterface;
use Illuminate\Support\Str;

class UserRepositary implements UserRepositaryInterface
{
    public function createAnonymousUser(array $data)
    {
        return User::create([
            'name' => 'Guest-' . Str::random(5),
            'anonymous_id' => Str::uuid(),
            'role' => 'anonymous',
            'device_id' => $data['device_id'] ?? null,
            'language' => $data['language'] ?? 'en',
        ]);
    }
    
    public function findByDeviceId($deviceId)
    {
        return User::where('device_id', $deviceId)->first();
    }
}
