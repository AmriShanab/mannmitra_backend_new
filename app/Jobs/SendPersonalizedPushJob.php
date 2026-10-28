<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
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

    public function handle(NotificationService $notificationService): void
    {
        // Fixed, hardcoded copy (lang/<code>/push.php): no OpenAI call for push notifications.
        $msg = Arr::random(Language::lines('push.mood', $this->user->language));

        $notificationService->sendToUser(
            $this->user->fcmToken,
            $msg['title'],
            $msg['body'],
            ['screen' => 'mood_checkin']
        );
    }
}
