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
        return $this->authService->handleAnonymousLogin($request->validated());
    }
}