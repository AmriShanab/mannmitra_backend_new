<?php

namespace App\Console\Commands;

use App\Jobs\SendCrisisFollowUpPushJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCrisisFollowUpReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-crisis-follow-up-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to check on users who logged a low mood 2 days ago';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Dispatching crisis follow-up reminder jobs...");

        User::where('role', 'anonymous')
            ->whereNotNull('fcmToken')
            ->whereHas('moodEntries', function ($query) {
                $query->whereDate('created_at', Carbon::today()->subDays(2))
                    ->where('primary_mood', '<=', 3);
            })
            ->whereDoesntHave('moodEntries', function ($query) {
                $query->whereDate('created_at', Carbon::today()->subDays(2));
            })
            ->chunk(500, function ($users) {
                foreach ($users as $user) {
                    SendCrisisFollowUpPushJob::dispatch($user);
                }
            });

        $this->info('Compassion check-in jobs dispatched successfully!');
    }
}
