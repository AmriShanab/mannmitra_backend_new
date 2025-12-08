<?php

namespace App\Services;

use App\Interfaces\AdminRepositoryInterface;

class AdminService
{
    protected $adminRepo;

    public function __construct(AdminRepositoryInterface $adminRepo)
    {
        $this->adminRepo = $adminRepo;
    }

    public function getDashboardData()
    {
        return [
            'stats' => $this->adminRepo->getSystemStats(),
            'pending_approvals' => $this->adminRepo->getPendingProfessionals(),
            'crisis_alerts' => $this->adminRepo->getActiveCrisisAlerts()
        ];
    }

    public function approveProfessional($userId)
    {
        return $this->adminRepo->verifyUser($userId);
    }
}