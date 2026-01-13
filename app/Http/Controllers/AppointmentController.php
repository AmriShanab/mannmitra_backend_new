<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    protected $appointementService;

    public function __construct(AppointmentService $appointementService)
    {
        $this->appointementService = $appointementService;
    }

    public function createAppointment(Request $request)
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        if (!$user->is_paid == true) {
            return response()->json(['status' => false, 'message' => 'Payment required to create an appointment'], 402);
        }

        try {
            $appointment = $this->appointementService->createRequest($user, $request->all());
            return response()->json(['status' => true, 'data' => $appointment, 'message' => 'Appointment request created successfully'], 201);
        } catch (\Throwable $th) {
            return response()->json(['status' => false, 'message' => 'Failed to create appointment request: ' . $th->getMessage()], 500);
        }
    }

    public function listPendingAppointments()
    {
        $appointments = Appointment::where('status', 'pending')
            ->with('user:id,name')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json(['status' => true, 'data' => $appointments], 200);
    }

    public function accept($id)
    {
        try {
            $doctor = Auth::user();
            $appointment = $this->appointementService->acceptRequest($id, $doctor->id);

            return response()->json(['status' => true, 'message' => 'Appointment confirmed.', 'data' => $appointment]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getJoinDetails($id)
    {
        $appointment = Appointment::where('appointment_id', $id)->firstOrFail();

        $userId = Auth::id();
        if ($appointment->user_id !== $userId && $appointment->psychiatrist_id !== $userId) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'appointment_id' => $appointment->appointment_id,
                'meeting_link' => $appointment->meeting_link,
                'mode' => $appointment->mode
            ]
        ]);
    }

    // 5. Doctor: Get My Confirmed Schedule
    public function mySchedule()
    {
        $doctor = Auth::user();

        $appointments = Appointment::where('psychiatrist_id', $doctor->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->with('user:id,name') // Get Patient Name
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json(['status' => true, 'data' => $appointments]);
    }
}
