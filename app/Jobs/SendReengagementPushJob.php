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
use App\Support\Language;

class SendReengagementPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected  $user;
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
        $languageName = Language::promptName($this->user->language);

        $session = $repo->getActiveSession($this->user->id);
        $recentMessages = $repo->getRecentMessages($session->id, 4);

        $systemPrompt = "
            You are Mann Mitra, a warm, highly empathetic friend. 
            SITUATION: The user has not opened the app or talked to you for exactly 7 days. 
            TASK: Write a gentle, welcoming Push Notification to invite them back. 
            LANGUAGE: {$languageName}.
            RULES: 
            1. Say you miss them or have been thinking about them.
            2. DO NOT guilt-trip them for being gone.
            3. Keep it VERY short. Title max 4 words. Body max 15 words.
            CONTEXT: Their last chats from a week ago: {$recentMessages}
            OUTPUT STRICTLY IN JSON:
            {\"title\": \"<Short Push Title>\", \"body\": \"<Short Push Body>\"}
        ";

        $title = '';
        $body = '';

        try {
            $aiData = $openAi->getChatCompletion($systemPrompt, "Generate a welcoming re-engagement push notification.");
            if (!empty($aiData['title']) && !empty($aiData['body'])) {
                $title = $aiData['title'];
                $body = $aiData['body'];
            } else {
                throw new \Exception("AI returned empty data.");
            }
        } catch (\Exception $e) {
            Log::warning("AI generation failed for re-engagement push. Using fallback. Error: " . $e->getMessage());
            $pool = Language::lines('push.reengagement', $this->user->language);
            $fallBackMsg = Arr::random($pool);
            $title = $fallBackMsg['title'];
            $body = $fallBackMsg['body'];
        }

        $notificationService->sendToUser(
            $this->user->fcmToken,
            $title,
            $body,
            ['screen' => 'home']
        );

    }
}
