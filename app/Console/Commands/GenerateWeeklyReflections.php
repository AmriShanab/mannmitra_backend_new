<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\JournalService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class GenerateWeeklyReflections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-weekly-reflections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate AI reflections for all users based on their weekly journals';
    protected $journalService;
    protected $notificationService;

    public function __construct(JournalService $journalService, NotificationService $notificationService)
    {
        return parent::__construct();
        $this->journalService = $journalService;
        $this->notificationService = $notificationService;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting weekly reflection generation...');
        User::chunk(100, function ($users) {
            $this->info('Found ' . $users->count() . ' users to process.'); // <--- Add this
            foreach ($users as $key => $user) {
                $this->info('Processing user: ' . $user->id);
                try {
                    $result = $this->journalService->generateWeeklyReflection($user->id);
                    if($result){
                        $this->info("Generated reflection for User ID: {$user->id}");
                        if($user->fcmToken){
                            $this->notificationService->sendToUser(
                            $user->fcmToken, 
                            'Weekly Insight Ready', 
                            "Your AI reflection for this week is now available in your journal.",
                            ['screen' => 'journal_view', 'entry_id' => $result['linked_entry_id']]
                            );
                        }
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }
        });

        $this->info('Weekly reflection generation completed.');
    }
}
