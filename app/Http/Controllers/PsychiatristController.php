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

        // A finished appointment can't be re-entered (e.g. via the Back button or an old link).
        if (in_array($appointment->status, ['completed', 'closed', 'cancelled', 'expired'])) {
            return redirect()->route('psychiatrist.dashboard');
        }

        $rt = $realtime->session(Auth::user(), RealtimeService::KIND_CALL, $appointment->meeting_link, $role);

        return response()
            ->view('psychiatrist.video_room', compact('appointment', 'rt'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
