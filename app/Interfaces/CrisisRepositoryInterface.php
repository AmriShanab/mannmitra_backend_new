<?php

namespace App\Interfaces;

interface CrisisRepositoryInterface
{
    public function logAlert($sessionId, $keyword, $severity = 'high');
}