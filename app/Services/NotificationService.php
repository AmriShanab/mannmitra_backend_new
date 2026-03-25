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
            $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);

            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            
            // ADD THIS: Log successes so you know it worked!
            \Illuminate\Support\Facades\Log::info("FCM Success: Sent to token ending in " . substr($fcmToken, -6));
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            \Illuminate\Support\Facades\Log::error("FCM Error: " . $errorMessage);
            
            // AUTO-CLEAN DEAD TOKENS
            if (
                str_contains($errorMessage, 'Requested entity was not found') || 
                str_contains($errorMessage, 'NotRegistered') || 
                str_contains($errorMessage, 'not a valid FCM registration token')
            ) {
                // Find the user with this dead token and wipe it
                \App\Models\User::where('fcmToken', $fcmToken)->update(['fcmToken' => null]);
                \Illuminate\Support\Facades\Log::info("Removed dead FCM token from database.");
            }

            return false;
        }
    }
}