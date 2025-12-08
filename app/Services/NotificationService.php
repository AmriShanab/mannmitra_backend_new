<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')));
        $this->messaging = $factory->createMessaging();
    }

    public function sendToUser($fcmToken, $title, $body, $data = [])
    {
        if (!$fcmToken) return false;

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($data); // Optional: Send payload (e.g., jump to specific screen)

            $this->messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            // Log error but don't crash app
            Log::error("FCM Error: " . $e->getMessage());
            return false;
        }
    }
}