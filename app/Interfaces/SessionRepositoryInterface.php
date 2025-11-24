<?php

namespace App\Interfaces;

interface SessionRepositoryInterface
{
    public function createSession(array $data);
}