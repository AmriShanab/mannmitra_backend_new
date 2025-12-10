<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrisisAlert;
use App\Models\Message;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function index()
    {
        $data = $this->adminService->getDashboardData();

        return view('admin.dashboard', [
            'stats' => $data['stats'],
            'alerts' => $data['crisis_alerts'],
            'approvals' => $data['pending_approvals']
        ]);
    }

    public function getChatHistory($sessionId)
    {
        // Fetch the messages cleanly
        $messages = \App\Models\Message::where('session_id', $sessionId)
            ->orderBy('created_at', 'asc') // Oldest first
            ->get(['sender', 'content', 'created_at']); // Select only what we need

        // Return standard JSON list
        return response()->json($messages);
    }

    public function resolveAlert($alertId)
    {
        $alert = CrisisAlert::findOrFail($alertId);
        $alert->update(['status' => 'resolved']);

        return response()->json(['success' => true]);
    }
}
