<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class AppointmentService
{
    public function createRequest($user, $data)
    {
        // Generate a unique appointment ID
        $aptId = 'APT-' . strtoupper(Str::random(8));
        $meetingLink = 'MEET-' . strtoupper(Str::random(12));

        return Appointment::create([
            'appointment_id' => $aptId,
            'user_id' => $user->id,
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
            'mode' => $data['mode'] ?? 'video',
            'notes' => $data['notes'] ?? null,
            'meeting_link' => $meetingLink,
            'status' => 'pending',
            'transaction_id' => $data['transaction_id'] ?? null,
        ]);
    }

    public function acceptRequest($appointmentId, $psychiatristId)
    {
        $appointment = Appointment::where('appointment_id', $appointmentId)->firstOrFail();
        if($appointment->status != 'pending'){
            throw new \Exception('The Appointment is no longer available.', 400);
        }

        $hasConflict = Appointment::where('psychiatrist_id', $psychiatristId)
                                    ->where('scheduled_at', $appointment->schedule_at)
                                    ->where('status', 'confirmed')
                                    ->get();

        if($hasConflict){
            throw new Exception("You already have an appointment on this time slot");
        }

        $appointment->update([
            'psychiatrist_id' => $psychiatristId,
            'status' => 'confirmed',
        ]);

        return $appointment;
    }
}