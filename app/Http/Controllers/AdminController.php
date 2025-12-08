<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function dashboard()
    {
        $data = $this->adminService->getDashboardData();
        return $this->successResponse($data, 'Admin Dashboard data fetched successfully');
    }

    public function approveUser($id)
    {
        $user = $this->adminService->approveProfessional($id);
        return $this->successResponse($user, "User {$user->name} approved successfully");
    }
}
