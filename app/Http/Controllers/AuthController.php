<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function guestLogin(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'nullable|string',
            'languageCode' => 'nullable|string|size:2',
            'session_type' => 'nullable|in:text, voice, video',
        ]);

        try {
            DB::beginTransaction();

            $result = $this->authService->handleAnonymousLogin($validated);

            DB::commit();

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'session' => [
                    'id' => $result['current_session']->id,
                    'type' => $result['current_session']->type,
                    'created_at' => $result['current_session']->created_at,
                ],
                'token' => $result['token'],
                
            ], 'Anonymous Session Start Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
