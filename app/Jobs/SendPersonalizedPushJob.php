<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\AIService;
use App\Repositories\CompanionRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Support\Language;

class SendPersonalizedPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    // Optional: If OpenAI fails, retry this job 3 times before giving up
    public $tries = 3; 
    
    // Optional: Wait 10 seconds between retries
    public $backoff = 10;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(NotificationService $notificationService, AIService $openAi, CompanionRepository $repo)
    {
        $languageName = Language::promptName($this->user->language);
        
        $session = $repo->getActiveSession($this->user->id);
        $recentMessages = $repo->getRecentMessages($session->id, 5); 
        $recentMoods = $repo->getRecentMoods($this->user->id, 3);

        $systemPrompt = "
            You are Mann Mitra, a warm, empathetic friend. Write a highly personalized Push Notification to remind the user to log their mood today.
            LANGUAGE: {$languageName}.
            RULES: Keep it VERY short. Title max 4 words. Body max 12 words.
            CONTEXT:
            Recent Moods: {$recentMoods}
            Recent Chats: {$recentMessages}
            OUTPUT STRICTLY IN JSON:
            {\"title\": \"<Short Push Title>\", \"body\": \"<Short Push Body>\"}
        ";

        $title = "";
        $body = "";

        try {
            $aiData = $openAi->getChatCompletion($systemPrompt, "Generate a personalized mood reminder push notification.");
            if (!empty($aiData['title']) && !empty($aiData['body'])) {
                $title = $aiData['title'];
                $body = $aiData['body'];
            } else {
                throw new \Exception("AI returned empty data.");
            }
        } catch (\Exception $e) {
            Log::warning("AI Push Gen failed for User {$this->user->id}. Error: " . $e->getMessage());
            $pool = Language::lines('push.mood', $this->user->language);
            $fallbackMsg = Arr::random($pool);
            $title = $fallbackMsg['title'];
            $body = $fallbackMsg['body'];
        }

        // Send via Firebase
        $notificationService->sendToUser(
            $this->user->fcmToken,
            $title,
            $body,
            ['screen' => 'mood_checkin'] 
        );
    }
}