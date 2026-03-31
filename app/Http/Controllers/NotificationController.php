<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $notifications = $user->notifications;

        $formattedNotifications = $notifications->map(function ($notification) {
            $data = $notification->data;

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'New Notification',
                'body' => $data['body'] ?? '',
                'timestamp' => $notification->created_at->toIso8601ZuluString(),
                'isRead' => $notification->read_at !== null,
                'type' => $data['type'] ?? 'alert', 
                'payload' => $data['payload'] ?? (object)[]
            ];
            
        });

        return response()->json([
            'success' => true,
            'data' => $formattedNotifications
        ]);
    }
}
