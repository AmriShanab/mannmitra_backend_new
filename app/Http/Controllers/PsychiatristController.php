<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\Auth;

class PsychiatristController extends Controller
{
    public function index()
    {
        return view('psychiatrist.dashboard', [
            'user' => Auth::user(),
        ]);
    }

    public function openVideoPage($id, RealtimeService $realtime)
    {
        $appointment = Appointment::where('appointment_id', $id)->firstOrFail();

        $role = $realtime->participantRole(Auth::user(), RealtimeService::KIND_CALL, $appointment->meeting_link);
        if ($role !== 'doctor') {
            abort(403, 'You are not assigned to this appointment.');
        }

        $rt = $realtime->session(Auth::user(), RealtimeService::KIND_CALL, $appointment->meeting_link, $role);

        return view('psychiatrist.video_room', compact('appointment', 'rt'));
    }
}
