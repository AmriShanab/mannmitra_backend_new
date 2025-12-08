<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendWellnessReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-wellness-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for users who forgot to log mood and send reminders';
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Wellness Engine...');

        // 1. Find Users: Who is "Active" but hasn't logged a mood TODAY?
        // (Assuming you have a 'moodEntries' relationship on User model)
        $users = User::where('role', 'anonymous')
            ->whereNotNull('fcmToken') // Only those we can reach
            ->whereDoesntHave('moodEntries', function ($query) {
                $query->whereDate('created_at', Carbon::today());
            })
            ->get();

        $count = 0;

        foreach ($users as $user) {
            // Personalized Message based on App Language
            $title = ($user->language == 'hi') ? "नमस्ते!" : "Hi there!";
            $body = ($user->language == 'hi') 
                ? "आपने आज अपना मूड लॉग नहीं किया। आप कैसा महसूस कर रहे हैं?" 
                : "You haven't logged your mood today. How are you feeling?";

            // Send via Service
            $sent = $this->notificationService->sendToUser(
                $user->fcmToken,
                $title,
                $body,
                ['screen' => 'mood_checkin'] // Tell Flutter to open Mood screen
            );

            if ($sent) $count++;
        }

        $this->info("Reminders sent to {$count} users.");
    }
}
