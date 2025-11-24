<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestLoginRequest; 
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use ApiResponse;

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function guestLogin(GuestLoginRequest $request)
    {
        try {
            DB::beginTransaction();

            
            $result = $this->authService->handleAnonymousLogin($request->validated());

            DB::commit();

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'session' => [
                    'id' => $result['current_session']->id,
                    'type' => $result['current_session']->type,
                    'created_at' => $result['current_session']->created_at,
                ],
                'token' => $result['token'],
            ], 'Anonymous Session Started Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}