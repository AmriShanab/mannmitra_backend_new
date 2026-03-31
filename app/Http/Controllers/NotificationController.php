<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->get()
            ->map(function ($notification) {
                $data = is_array($notification->data) ? $notification->data : [];

                $title = $data['title'] ?? 'Notification';
                $body = $data['body'] ?? '';

                $rawType = (string) ($data['type'] ?? 'alert');
                $allowedTypes = ['appointment_reminder', 'message', 'alert', 'video_call'];
                $type = in_array($rawType, $allowedTypes, true) ? $rawType : 'alert';

                unset($data['title'], $data['body'], $data['type']);

                return [
                    'id' => 'notif_' . $notification->id,
                    'title' => $title,
                    'body' => $body,
                    'timestamp' => optional($notification->created_at)->toISOString(),
                    'isRead' => !is_null($notification->read_at),
                    'type' => $type,
                    'payload' => $this->toCamelCaseKeys($data),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    private function toCamelCaseKeys(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $camelKey = is_string($key) ? Str::camel($key) : $key;

            if (is_array($value)) {
                $result[$camelKey] = $this->toCamelCaseKeys($value);
                continue;
            }

            $result[$camelKey] = $value;
        }

        return $result;
    }
}
