<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestLoginRequest; 
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\Request;

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
        // dd($request->validated());
        return $this->authService->handleAnonymousLogin($request->validated());
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

        return $this->successResponse([
            'user' => new \App\Http\Resources\UserResource($user),
            'token' => $token
        ], 'Login successful');
    }
}