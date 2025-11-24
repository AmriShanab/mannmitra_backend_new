<?php

namespace App\Repositories;

use App\Models\Session;
use App\Interfaces\SessionRepositoryInterface;

class SessionRepository implements SessionRepositoryInterface
{
    public function createSession(array $data)
    {
        return Session::create([
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'status' => 'active'
        ]); 
    }
}