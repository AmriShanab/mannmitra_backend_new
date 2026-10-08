<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderJob;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'app:send-appointment-reminders';
    protected $description = 'Remind patients (push) and doctors (dashboard) 30 minutes before a confirmed appointment';

    public function handle()
    {
        $count = 0;

        Appointment::dueForReminder()->select('id')->get()->each(function ($appointment) use (&$count) {
            // Claim it first: only one run can flip the NULL, so nobody is reminded twice.
            $claimed = Appointment::where('id', $appointment->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if ($claimed) {
                SendAppointmentReminderJob::dispatch($appointment->id);
                $count++;
            }
        });

        $this->info("Queued {$count} appointment reminder(s).");
    }
}
