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

    private function getMaskedName($user)
    {
        return "Anonymous User #" . (1000 + $user->id);
    }

    public function listPendingAppointments()
    {
        $appointments = \App\Models\Appointment::where('status', 'pending')
            ->with('user:id,name')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $appointments->transform(function ($apt) {
            $apt->user->name = $this->getMaskedName($apt->user);
            return $apt;
        });

        return response()->json(['status' => true, 'data' => $appointments]);
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

        if (in_array($appointment->status, ['expired', 'closed', 'cancelled'])) {
            return response()->json([
                'status' => false, 
                'message' => 'This appointment has expired or is no longer active.'
            ], 403);
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

    public function mySchedule()
    {
        $doctor = Auth::user();

        $appointments = Appointment::where('psychiatrist_id', $doctor->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->with('user:id,name')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $appointments->transform(function ($apt) {
            $apt->user->name = $this->getMaskedName($apt->user);
            return $apt;
        });

        return response()->json(['status' => true, 'data' => $appointments]);
    }

    public function getAppointmentsOfUser()
    {
        try {
            $userId = Auth::id();

            $appointments = Appointment::where('user_id', $userId)
                ->with(['psychiatrist:id,name'])
                ->orderBy('scheduled_at', 'desc')
                ->get();

            if ($appointments->isEmpty()) {
                return response()->json([
                    "status" => true,
                    "message" => "No appointments found.",
                    "data" => []
                ], 200);
            }

            return response()->json([
                "status" => true,
                "data" => $appointments
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => "Error: " . $th->getMessage()
            ], 500);
        }
    }

    public function closingAppointments(Request $request)
    {
        $request->validate([
            'meeting_link' => 'required|string',
        ]);

        $appointment = Appointment::where('meeting_link', $request->meeting_link)->first();

        if($appointment){
            $appointment->status = 'completed';
            $appointment->save();

            return response()->json(['status' => true, 'message' => 'Appointment Completed Successfully']);
        }

        return response()->json(['status' => false, 'message' => 'Appointment Not Found'], 404);


    }
}
