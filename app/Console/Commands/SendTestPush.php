<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTestPush extends Command
{
    protected $signature = 'app:send-test-push
        {--token= : FCM token to send to directly}
        {--user= : User id whose saved FCM token to use}
        {--all : Send to every anonymous user with a token (asks for confirmation)}
        {--title=MannMitra test : Notification title}
        {--body=This is a test notification. If you can read this, push is working. : Notification body}';

    protected $description = 'Send a test push notification immediately (no queue)';

    public function handle(NotificationService $push): int
    {
        $title = $this->option('title');
        $body = $this->option('body');
        $data = ['type' => 'test', 'sent_at' => now()->toIso8601String()];

        if ($token = $this->option('token')) {
            return $this->report($push->sendToUser($token, $title, $body, $data), 'token ...' . substr($token, -6));
        }

        if ($id = $this->option('user')) {
            $user = User::find($id);
            if (!$user) {
                $this->error("User $id not found.");
                return self::FAILURE;
            }
            if (!$user->fcmToken) {
                $this->error("User $id has no saved FCM token (the app has not registered one).");
                return self::FAILURE;
            }
            return $this->report($push->sendToUser($user->fcmToken, $title, $body, $data), "user $id");
        }

        if ($this->option('all')) {
            $count = User::where('role', 'anonymous')->whereNotNull('fcmToken')->count();
            if (!$this->confirm("Send this test to $count users?")) {
                return self::SUCCESS;
            }
            $ok = 0;
            User::where('role', 'anonymous')->whereNotNull('fcmToken')->chunk(200, function ($users) use ($push, $title, $body, $data, &$ok) {
                foreach ($users as $u) {
                    $ok += $push->sendToUser($u->fcmToken, $title, $body, $data) ? 1 : 0;
                }
            });
            $this->info("Sent $ok of $count.");
            return self::SUCCESS;
        }

        $this->error('Give one of: --token=..., --user=ID, or --all');
        return self::FAILURE;
    }

    private function report(bool $sent, string $target): int
    {
        if ($sent) {
            $this->info("Accepted by Firebase for $target. Check the device.");
            return self::SUCCESS;
        }
        $this->error("Failed for $target. See storage/logs/laravel.log, look for 'FCM Error'.");
        return self::FAILURE;
    }
}
