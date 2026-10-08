<?php

namespace App\Http\Controllers;

use App\Services\RealtimeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RealtimeController extends Controller
{
    use ApiResponse;

    public function __construct(protected RealtimeService $realtime)
    {
    }

    /**
     * POST /realtime/session  { kind: chat|call, room: <ticket code | meeting link> }
     * Returns a signed room token, the socket URL and (for calls) ICE servers.
     */
    public function session(Request $request)
    {
        $data = $request->validate([
            'kind' => 'required|in:chat,call',
            'room' => 'required|string|max:100',
        ]);

        $user = Auth::user();
        $role = $this->realtime->participantRole($user, $data['kind'], $data['room']);

        if (!$role) {
            return $this->errorResponse('You are not allowed to join this session', 403);
        }

        try {
            return $this->successResponse($this->realtime->session($user, $data['kind'], $data['room'], $role));
        } catch (\Throwable $e) {
            Log::error('Realtime session failed: ' . $e->getMessage());
            return $this->errorResponse('Realtime service unavailable', 503);
        }
    }
}
