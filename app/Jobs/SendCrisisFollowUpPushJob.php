<?php

namespace App\Jobs;

use App\Models\User;
use App\Repositories\CompanionRepository;
use App\Services\AIService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SendCrisisFollowUpPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    public $tries = 3;
    public $backoff = 10;



    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService, AIService $openAi, CompanionRepository $repo): void
    {
        $fallbackEn = [
            ["title" => "Thinking of you 💙", "body" => "You were feeling a bit low a couple of days ago. Are you doing any better today?"],
            ["title" => "Checking in", "body" => "We're here for you. Take a deep breath and let us know how today is going."]
        ];
        $fallbackHi = [
            ["title" => "आपके बारे में सोच रहे हैं 💙", "body" => "कुछ दिन पहले आप उदास थे। क्या आज आप बेहतर महसूस कर रहे हैं?"],
            ["title" => "हम आपके साथ हैं", "body" => "एक गहरी सांस लें और हमें बताएं कि आज का दिन कैसा है।"]
        ];

        $languageName = ($this->user->language == 'hi') ? 'conversational Hinglish (Latin script)' : 'English';

        $session = $repo->getActiveSession($this->user->id);
        $recentMessages = $repo->getRecentMessages($session->id, 5);

        $systemPrompt = "
            You are Mann Mitra, a warm, highly empathetic friend. 
            SITUATION: The user logged a very low mood/crisis 2 days ago and hasn't checked in since. 
            TASK: Write a gentle, supportive Push Notification to check on them. 
            LANGUAGE: {$languageName}.
            RULES: 
            1. Acknowledge they had a hard time recently, but keep it very gentle.
            2. Ask if they are feeling any better today.
            3. Keep it VERY short. Title max 4 words. Body max 15 words.
            CONTEXT: Recent Chats: {$recentMessages}
            OUTPUT STRICTLY IN JSON:
            {\"title\": \"<Short Push Title>\", \"body\": \"<Short Push Body>\"}
        ";

        $title = '';
        $body = '';

        try {
            $aiData = $openAi->getChatCompletion($systemPrompt, "Generate a gentle follow-up push notification for a user who was feeling low.");
            if (!empty($aiData['title']) && !empty($aiData['body'])) {
                $title = $aiData['title'];
                $body = $aiData['body'];
            } else {
                throw new \Exception("AI returned empty data.");
            }
        } catch (\Exception $e) {
            Log::warning("AI failed to generate follow-up push for user {$this->user->id}. Error: {$e->getMessage()}");

            $pool = ($this->user->language == 'hi') ? $fallbackHi : $fallbackEn;
            $fallBackMsg = Arr::random($pool);
            $title = $fallBackMsg['title'];
            $body = $fallBackMsg['body'];
        }

        $notificationService->sendToUser(
            $this->user->fcmToken,
            $title,
            $body,
            ['screen' => 'mood_checkin']
        );
    }
}
