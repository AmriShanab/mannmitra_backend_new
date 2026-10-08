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

        $query = $user->notifications();
        if ($request->filled('limit')) {
            $query->limit(min((int) $request->limit, 100));
        }
        $notifications = $query->get();

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

    public function markRead($id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
