<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Jobs\SendPersonalizedPushJob;
use Illuminate\Console\Command;

class SendWellnessReminders extends Command
{
    protected $signature = 'app:send-wellness-reminders';
    protected $description = 'Dispatch jobs to send personalized wellness reminders to ALL active users';

    public function handle()
    {
        $this->info('Dispatching Wellness Jobs to the Queue for ALL users...');

        // Fetch ALL anonymous users who have an FCM token (No mood filter!)
        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            ->chunk(500, function ($users) {
                foreach ($users as $user) {
                    // Instantly push this user to the background queue
                    SendPersonalizedPushJob::dispatch($user);
                }
            });

        $this->info('All jobs dispatched successfully!');
    }
}