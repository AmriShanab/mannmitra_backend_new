<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use App\Support\Language;

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
    public function handle(NotificationService $notificationService): void
    {
        // Fixed, hardcoded copy (lang/<code>/push.php): no OpenAI call for push notifications.
        $msg = Arr::random(Language::lines('push.crisis_followup', $this->user->language));

        $notificationService->sendToUser(
            $this->user->fcmToken,
            $msg['title'],
            $msg['body'],
            ['screen' => 'mood_checkin']
        );
    }
}
