<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PsychiatristController extends Controller
{
    public function index()
    {
        return view('psychiatrist.dashboard', [
            'user' => Auth::user(),
        ]);
    }

    public function openVideoPage($id)
    {
        // 1. Fetch the appointment to verify it exists
        $appointment = \App\Models\Appointment::where('appointment_id', $id)->firstOrFail();

        // 2. Return the HTML view (we will create this next)
        return view('psychiatrist.video_room', compact('appointment'));
    }
}
