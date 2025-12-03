<?php

namespace App\Repositories;

use App\Interfaces\CrisisRepositoryInterface;
use App\Models\CrisisAlert;

class CrisisAlertRepository implements CrisisRepositoryInterface
{
    public function logAlert($sessionId, $keyword, $severity = 'high')
    {
        return CrisisAlert::create([
            'session_id' => $sessionId,
            'trigger_keyword' => $keyword,
            'severity' => $severity,
            'status' => 'pending',
        ]); 
    }
}