<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class AppointmentService
{
    public function createRequest($user, $data, $razorpayOrderId)
    {
        $aptId = 'APT-' . strtoupper(Str::random(8));
        $meetingLink = 'MEET-' . strtoupper(Str::random(12));

        return Appointment::create([
            'appointment_id' => $aptId,
            'user_id' => $user->id,
            'scheduled_at' => Carbon::parse($data['scheduled_at'], 'Asia/Kolkata')->format('Y-m-d H:i:s'),
            'mode' => $data['mode'] ?? 'video',
            'notes' => $data['notes'] ?? null,
            'meeting_link' => $meetingLink,
            'status' => 'pending_payment', 
            'razorpay_order_id' => $razorpayOrderId,
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);
    }

    public function processPayment($appointmentId, $paymentId)
    {
        $appointment = Appointment::where('appointment_id', $appointmentId)->firstOrFail();
        
        $appointment->update([
            'status' => 'pending', 
            'razorpay_payment_id' => $paymentId,
            'transaction_id' => $paymentId
        ]);

        return $appointment;
    }

    public function acceptRequest($appointmentId, $psychiatristId)
    {
        $appointment = Appointment::where('appointment_id', $appointmentId)->firstOrFail();
        if ($appointment->status != 'pending') {
            throw new \Exception('The Appointment is no longer available.', 400);
        }

        $appointment->update([
            'psychiatrist_id' => $psychiatristId,
            'status' => 'confirmed',
        ]);

        return $appointment;
    }
}