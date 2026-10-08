<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(public int $appointmentId)
    {
    }

    public function handle(NotificationService $push): void
    {
        $appointment = Appointment::with(['user', 'psychiatrist'])->find($this->appointmentId);

        // The booking may have been cancelled/closed since the reminder was queued.
        if (!$appointment || $appointment->status !== 'confirmed') {
            return;
        }

        // scheduled_at is stored as Indian wall-clock time, so format it as-is.
        $when = Carbon::parse($appointment->scheduled_at);
        $time = $when->format('g:i A');
        $iso = $when->format('Y-m-d H:i:s');

        // ---- Patient: push notification + in-app list ----
        $patient = $appointment->user;
        if ($patient) {
            $hindi = $patient->language === 'hi';
            $doctorName = $appointment->psychiatrist ? $appointment->psychiatrist->name : null;

            $title = $hindi ? 'सत्र 30 मिनट में शुरू होगा ⏰' : 'Your session starts in 30 minutes ⏰';
            $body = $hindi
                ? "आपका सत्र {$time} बजे शुरू होगा। जुड़ने के लिए ऐप खोलें।"
                : 'Your session' . ($doctorName ? " with Dr. {$doctorName}" : '') . " starts at {$time}. Open the app to join.";

            try {
                $patient->notify(new AppointmentReminder($title, $body, $appointment->appointment_id, $iso));
            } catch (\Throwable $e) {
                Log::error("Reminder (in-app) failed for patient {$patient->id}: " . $e->getMessage());
            }

            if ($patient->fcmToken) {
                $push->sendToUser($patient->fcmToken, $title, $body, [
                    'type' => 'appointment_reminder',
                    'appointment_id' => $appointment->appointment_id,
                ]);
            }
        }

        // ---- Doctor: dashboard notification ----
        $doctor = $appointment->psychiatrist;
        if ($doctor) {
            $maskedName = 'Anonymous User #' . (1000 + (int) $appointment->user_id);
            $doctor->notify(new AppointmentReminder(
                'Session starts in 30 minutes',
                "Your {$appointment->mode} session with {$maskedName} starts at {$time}.",
                $appointment->appointment_id,
                $iso
            ));
        }
    }
}
