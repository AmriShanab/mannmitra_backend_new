<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Stored in the notifications table. Patients see it in the app's notification list;
 * doctors see it in the bell on their dashboard.
 */
class AppointmentReminder extends Notification
{
    public function __construct(
        private string $title,
        private string $body,
        private string $appointmentId,
        private string $scheduledAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => 'appointment_reminder',
            'payload' => [
                'appointment_id' => $this->appointmentId,
                'scheduled_at' => $this->scheduledAt,
            ],
        ];
    }
}
