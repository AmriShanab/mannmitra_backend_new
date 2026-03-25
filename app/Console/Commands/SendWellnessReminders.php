<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Jobs\SendPersonalizedPushJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendWellnessReminders extends Command
{
    protected $signature = 'app:send-wellness-reminders';
    protected $description = 'Dispatch jobs to send personalized wellness reminders';

    public function handle()
    {
        $this->info('Dispatching Wellness Jobs to the Queue...');
        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            ->whereDoesntHave('moodEntries', function ($query) {
                $query->whereDate('created_at', Carbon::today());
            })
            ->chunk(500, function ($users) {
                foreach ($users as $user) {
                    // Instantly push this user to the background queue
                    SendPersonalizedPushJob::dispatch($user);
                }
            });

        $this->info('All jobs dispatched successfully!');
    }
}