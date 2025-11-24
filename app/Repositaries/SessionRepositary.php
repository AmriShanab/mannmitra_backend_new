<?php

namespace App\Repositaries;

use App\Models\Session;
use App\Interfaces\SessionRepositaryInterface;

class SessionRepositary implements SessionRepositaryInterface
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