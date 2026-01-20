<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
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
        $appointment = Appointment::where('appointment_id', $id)->firstOrFail();
        return view('psychiatrist.video_room', compact('appointment'));
    }
}
