<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Jobs\SendReengagementPushJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReengagementReminders extends Command
{
    protected $signature = 'app:send-reengagement-reminders';
    protected $description = 'Dispatch jobs to re-engage users inactive for exactly 7 days';

    public function handle()
    {
        $this->info("Finding users who have been away for 7 days...");

        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            // 1. They MUST HAVE logged something exactly 7 days ago
            ->whereHas('moodEntries', function ($query) {
                $query->whereDate('created_at', Carbon::today()->subDays(7));
            })
            // 2. They MUST NOT HAVE logged anything since then (Days 1 to 6)
            ->whereDoesntHave('moodEntries', function ($query) {
                $query->where('created_at', '>', Carbon::today()->subDays(7)->endOfDay());
            })
            ->chunk(500, function ($users) {
                foreach ($users as $user) {
                    SendReengagementPushJob::dispatch($user);
                }
            });

        $this->info('Re-engagement check-in jobs dispatched successfully!');
    }
}