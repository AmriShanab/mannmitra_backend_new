<?php

namespace App\Console\Commands;

use App\Jobs\GenerateJournalEntryJob;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateWeeklyJournals extends Command
{
    protected $signature = 'companion:generate-weekly-journals';

    protected $description = 'Queue one weekly journal job per user who chatted in the past 7 days';

    public function handle()
    {
        $since = now()->subDays(7);
        $today = now()->toDateString();
        $count = 0;

        User::whereHas('sessions.messages', function ($query) use ($since) {
            $query->where('sender', 'user')->where('created_at', '>=', $since);
        })->chunkById(200, function ($users) use ($today, &$count) {
            foreach ($users as $user) {
                GenerateJournalEntryJob::dispatch($user, GenerateJournalEntryJob::WEEKLY, $today);
                $count++;
            }
        });

        $this->info("Queued weekly journal jobs for {$count} users.");
    }
}
