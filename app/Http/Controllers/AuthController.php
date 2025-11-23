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
            'device_id' => 'nullable|string',
            'language' => 'nullable|string|size:2'
        ]);

        try {
            DB::beginTransaction();

            $result = $this->authService->handleAnonymousLogin($validated);

            DB::commit();

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                
            ], 'Anonymous Session Start Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
