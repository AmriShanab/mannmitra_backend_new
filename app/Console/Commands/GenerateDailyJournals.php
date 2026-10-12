<?php

namespace App\Console\Commands;

use App\Jobs\GenerateJournalEntryJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyJournals extends Command
{
    protected $signature = 'companion:generate-daily-journals';

    protected $description = 'Queue one daily journal job per user who chatted today';

    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $count = 0;

        User::whereHas('sessions.messages', function ($query) use ($today) {
            $query->where('sender', 'user')->whereDate('created_at', $today);
        })->chunkById(200, function ($users) use ($today, &$count) {
            foreach ($users as $user) {
                GenerateJournalEntryJob::dispatch($user, GenerateJournalEntryJob::DAILY, $today);
                $count++;
            }
        });

        $this->info("Queued daily journal jobs for {$count} users.");
    }
}
