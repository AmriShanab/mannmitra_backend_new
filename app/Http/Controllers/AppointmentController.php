<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Interfaces\PaymentGatewayInterface; // <-- Import the interface
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    protected $appointementService;
    protected $paymentGateway;

    public function __construct(AppointmentService $appointementService, PaymentGatewayInterface $paymentGateway)
    {
        $this->appointementService = $appointementService;
        $this->paymentGateway = $paymentGateway;
    }

    public function createAppointment(Request $request)
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'notes' => 'nullable|string',
            'mode' => 'nullable|string'
        ]);

        $user = Auth::user();
        
        $amountInPaise = 49900; 

        try {
            $order = $this->paymentGateway->createOrder($amountInPaise, 'INR');

            $appointment = $this->appointementService->createRequest($user, $request->all(), $order['id']);

            return response()->json([
                'status' => true, 
                'message' => 'Appointment request created successfully',
                'data' => [
                    'appointment' => $appointment,
                    'razorpay_checkout' => [
                        'order_id' => $order['id'],
                        'amount' => $amountInPaise,
                        'currency' => 'INR',
                        'key' => env('RAZORPAY_KEY')
                    ]
                ]
            ], 201);
            
        } catch (\Throwable $th) {
            return response()->json(['status' => false, 'message' => 'Failed to create appointment request: ' . $th->getMessage()], 500);
        }
    }

    public function paymentSuccess(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string', 
            'payment_id' => 'required',
            'signature' => 'required'
        ]);

        $isValid = $this->paymentGateway->verifyPayment([
            'payment_id' => $request->payment_id,
            'signature' => $request->signature
        ]);

        if (!$isValid) {
            return response()->json(['status' => false, 'message' => 'Invalid payment'], 400);
        }

        $appointment = $this->appointementService->processPayment($request->appointment_id, $request->payment_id);
        
        return response()->json(['status' => true, 'message' => 'Payment verified. Appointment is now waiting for a doctor.', 'data' => $appointment]);
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