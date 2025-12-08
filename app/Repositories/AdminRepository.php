<?php

namespace App\Repositories;
use App\Interfaces\AdminRepositoryInterface;
use App\Models\CrisisAlert;
use App\Models\User;
use Carbon\Carbon;

class AdminRepository implements AdminRepositoryInterface
{
    public function getSystemStats()
    {
        return [
            'total_users' => User::count(),
            'active_listners' => User::where('role', 'listener')->where('is_verified', true)->count(),
            'sessions_today' => User::whereDate('created_at', Carbon::today())->count(),
            'pending_alerts' => CrisisAlert::where('status', 'pending')->count()
        ];
    }

    public function getPendingProfessionals()
    {
        return User::whereIn('role', ['listener', 'psychiatrist'])
            ->where('is_verified', false)
            ->latest()
            ->get(['id', 'name', 'email', 'role', 'created_at']);
    }

    public function getActiveCrisisAlerts()
    {
        return CrisisAlert::with('session.user')
        ->where('status', 'pending')
        ->latest()
        ->get();
    }

    public function verifyUser($userId)
    {
        $user = User::findOrFail($userId);
        $user->update([
            'is_verified' => true,
            'verified_at' => Carbon::now()
        ]);

        return $user;
    }
}