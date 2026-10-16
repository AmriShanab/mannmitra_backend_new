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
            'notes' => 'nullable|string|max:2000',
            'mode' => 'nullable|in:video,audio'
        ]);

        $user = Auth::user();
        
        $amountInPaise = (int) config('services.pricing.appointment') * 100;

        try {
            $order = $this->paymentGateway->createOrder($amountInPaise, 'INR', [
                'type' => 'appointment',
                'user_id' => (string) $user->id,
            ]);

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
                        'key' => config('services.razorpay.key')
                    ]
                ]
            ], 201);
            
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\Log::error('Appointment create failed: ' . $th->getMessage());
            return response()->json(['status' => false, 'message' => 'Unable to start payment. Please try again.'], 502);
        }
    }

    public function paymentSuccess(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|string', 
            'payment_id' => 'required',
            'signature' => 'required'
        ]);

        $existing = Appointment::where('appointment_id', $request->appointment_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$existing || !$existing->razorpay_order_id) {
            return response()->json(['status' => false, 'message' => 'Appointment not found'], 404);
        }

        $isValid = $this->paymentGateway->verifyPayment([
            'order_id' => $existing->razorpay_order_id,
            'payment_id' => $request->payment_id,
            'signature' => $request->signature
        ]);

        if (!$isValid) {
            return response()->json(['status' => false, 'message' => 'Invalid payment'], 400);
        }

        try {
            $appointment = $this->appointementService->processPayment($request->appointment_id, $request->payment_id);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 409);
        }

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

    public function requestCancellation($id)
    {
        try {
            $out = $this->appointementService->requestCancellation($id, Auth::id());
            $appointment = $out['appointment'];

            if ($out['result'] === 'requested' && $appointment->psychiatrist_id) {
                $this->push($appointment->psychiatrist_id, 'Cancellation request', 'A patient has asked to cancel an appointment. Please review it on your dashboard.');
            }

            return response()->json([
                'status' => true,
                'result' => $out['result'],
                'message' => $out['result'] === 'cancelled'
                    ? 'Appointment cancelled. Your refund will reach you in 3 to 4 days.'
                    : 'Cancellation requested. Your psychiatrist will confirm it.',
                'data' => $appointment,
            ]);
        } catch (\Exception $e) {
            return $this->cancelError($e);
        }
    }

    public function decideCancellation(Request $request, $id)
    {
        $request->validate(['approve' => 'required|boolean']);

        try {
            $appointment = $this->appointementService->decideCancellation($id, Auth::id(), $request->boolean('approve'));

            $this->push($appointment->user_id,
                $appointment->status === 'cancelled' ? 'Appointment cancelled' : 'Cancellation declined',
                $appointment->status === 'cancelled'
                    ? 'Your appointment is cancelled. Your refund will reach you in 3 to 4 days.'
                    : 'Your psychiatrist could not cancel this appointment. It is still scheduled.');

            return response()->json(['status' => true, 'data' => $appointment]);
        } catch (\Exception $e) {
            return $this->cancelError($e);
        }
    }

    private function cancelError(\Exception $e)
    {
        $code = in_array($e->getCode(), [404, 422, 502], true) ? $e->getCode() : 500;
        return response()->json(['status' => false, 'message' => $code === 500 ? 'Something went wrong.' : $e->getMessage()], $code);
    }

    private function push($userId, string $title, string $body): void
    {
        try {
            $token = \App\Models\User::whereKey($userId)->value('fcmToken');
            if ($token) {
                app(\App\Services\NotificationService::class)->sendToUser($token, $title, $body, ['type' => 'appointment_cancel']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cancellation push failed: ' . $e->getMessage());
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

            // Unpaid (abandoned checkout) bookings are not shown to the patient.
            $appointments = Appointment::where('user_id', $userId)
                ->where('status', '!=', 'pending_payment')
                ->with(['psychiatrist:id,name,phone'])
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
                "message" => "Unable to load appointments right now."
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
            $uid = Auth::id();
            if ($appointment->user_id !== $uid && $appointment->psychiatrist_id !== $uid) {
                return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
            }

            // Idempotent: both sides may hang up. Only a confirmed session (a doctor was assigned)
            // can be completed; a pending one must stay cancellable/refundable.
            if ($appointment->status === 'confirmed') {
                $appointment->status = 'completed';
                $appointment->save();
            }

            return response()->json(['status' => true, 'message' => 'Appointment Completed Successfully']);
        }

        return response()->json(['status' => false, 'message' => 'Appointment Not Found'], 404);
    }
}