<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Tickets;
use App\Models\User;

/**
 * Decides who may enter a chat/call room, and mints the short-lived credentials the
 * socket server and WebRTC need (signed room token + time-limited TURN login).
 */
class RealtimeService
{
    public const KIND_CHAT = 'chat';
    public const KIND_CALL = 'call';

    /**
     * Returns the participant role ('user' | 'listener' | 'doctor') or null if not allowed.
     * $room is the ticket code for chat and the appointment meeting_link for calls.
     */
    public function participantRole(User $user, string $kind, string $room): ?string
    {
        if ($kind === self::KIND_CHAT) {
            $ticket = Tickets::where('ticket_id', $room)->first();
            if (!$ticket || !in_array($ticket->status, ['open', 'in_progress'], true)) {
                return null;
            }
            if ((int) $ticket->user_id === (int) $user->id) return 'user';
            if ($ticket->listener_id && (int) $ticket->listener_id === (int) $user->id) return 'listener';
            return null;
        }

        if ($kind === self::KIND_CALL) {
            $appointment = Appointment::where('meeting_link', $room)->first();
            if (!$appointment || in_array($appointment->status, ['expired', 'closed', 'cancelled', 'completed'], true)) {
                return null;
            }
            if ((int) $appointment->user_id === (int) $user->id) return 'user';
            if ($appointment->psychiatrist_id && (int) $appointment->psychiatrist_id === (int) $user->id) return 'doctor';
            return null;
        }

        return null;
    }

    /**
     * Signed token: base64url(payload).base64url(hmac_sha256). Verified by the socket server
     * with the same REALTIME_SECRET. Bound to one user, one room and one kind.
     */
    public function issueToken(User $user, string $kind, string $room, string $role, int $ttlSeconds = 7200): string
    {
        $payload = $this->b64(json_encode([
            'sub' => (int) $user->id,
            'room' => $room,
            'kind' => $kind,
            'role' => $role,
            'name' => $user->name,
            'exp' => time() + $ttlSeconds,
        ]));

        return $payload . '.' . $this->b64(hash_hmac('sha256', $payload, $this->secret(), true));
    }

    public function socketUrl(): string
    {
        return rtrim((string) config('services.realtime.url'), '/');
    }

    /**
     * ICE servers. With TURN_SECRET set this uses coturn's time-limited credentials
     * (use-auth-secret), so nothing reusable is ever shipped in the app or web page.
     */
    public function iceServers(User $user): array
    {
        $servers = [['urls' => 'stun:stun.l.google.com:19302']];

        $urls = array_filter(array_map('trim', explode(',', (string) config('services.turn.urls'))));
        if (!$urls) {
            return $servers;
        }

        $secret = config('services.turn.secret');
        if ($secret) {
            $username = (time() + 3600) . ':' . $user->id;
            $servers[] = [
                'urls' => array_values($urls),
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
            ];
        } elseif (config('services.turn.username')) {
            // Legacy static login (from .env, not from source code). Prefer TURN_SECRET.
            $servers[] = [
                'urls' => array_values($urls),
                'username' => config('services.turn.username'),
                'credential' => config('services.turn.password'),
            ];
        }

        return $servers;
    }

    /** Everything a client needs to join a room. */
    public function session(User $user, string $kind, string $room, string $role): array
    {
        return [
            'token' => $this->issueToken($user, $kind, $room, $role),
            'socket_url' => $this->socketUrl(),
            'role' => $role,
            'ice_servers' => $kind === self::KIND_CALL ? $this->iceServers($user) : [],
        ];
    }

    private function secret(): string
    {
        $secret = config('services.realtime.secret');
        if (!$secret) {
            throw new \RuntimeException('REALTIME_SECRET is not configured');
        }
        return $secret;
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
