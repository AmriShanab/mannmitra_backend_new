<?php

namespace App\Console\Commands;

use App\Jobs\SendCrisisFollowUpPushJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCrisisFollowUpReminders extends Command
{
    protected $signature = 'app:send-crisis-follow-up-reminders';
    protected $description = 'Dispatch jobs to check on users who logged a low mood 2 days ago';

    public function handle()
    {
        $this->info("Dispatching crisis follow-up reminder jobs...");

        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            ->whereHas('moodEntries', function ($query) {
                // 1. They logged a mood exactly 2 days ago...
                $query->whereDate('created_at', Carbon::today()->subDays(2))
                      ->where('primary_mood', '<=', 3);
            })
            ->whereDoesntHave('moodEntries', function ($query) {
                // 2. AND they have NOT logged any moods SINCE then (yesterday or today)
                // This checks anything greater than the very end of 2 days ago
                $query->where('created_at', '>', Carbon::today()->subDays(2)->endOfDay());
            })
            ->chunk(500, function ($users) {
                foreach ($users as $user) {
                    SendCrisisFollowUpPushJob::dispatch($user);
                }
            });

        $this->info('Compassion check-in jobs dispatched successfully!');
    }
}