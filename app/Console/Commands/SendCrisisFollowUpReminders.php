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
        $this->info("Finding users who had a low mood OR a crisis alert 2 days ago...");

        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            ->where(function ($query) {
                // CONDITION A: They logged a low mood exactly 2 days ago...
                $query->whereHas('moodEntries', function ($subQuery) {
                    $subQuery->whereDate('created_at', Carbon::today()->subDays(2))
                        ->where('primary_mood', '<=', 3);
                })
                    // CONDITION B: ...OR they triggered a crisis alert exactly 2 days ago
                    ->orWhereHas('crisisAlerts', function ($subQuery) {
                        $subQuery->whereDate('created_at', Carbon::today()->subDays(2));
                    });
            })
            // AND they haven't checked in since then (yesterday or today)
            ->whereDoesntHave('moodEntries', function ($query) {
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
