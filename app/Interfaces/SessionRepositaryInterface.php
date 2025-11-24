<?php

namespace App\Interfaces;

interface SessionRepositaryInterface
{
    public function createSession(array $data);
}