<?php

namespace App\Interfaces;

interface AdminRepositoryInterface
{
    public function getSystemStats();
    public function getPendingProfessionals();
    public function getActiveCrisisAlerts();
    public function verifyUser($userId);
}