<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestLoginRequest;
use App\Http\Requests\AdminLoginRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use App\Models\User; // <--- Added this
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // <--- Added this

class AuthController extends Controller
{
    use ApiResponse;

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    // 1. Guest Login (Anonymous)
    public function guestLogin(GuestLoginRequest $request)
    {
        return $this->authService->handleAnonymousLogin($request->validated());
    }

    // 2. API Login (For Listeners & Mobile App Users)
    // This returns JSON with a Token
    public function apiLogin(Request $request)
    {
        // A. Validate
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // B. Find User
        $user = User::where('email', $request->email)->first();

        // C. Check Password
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid Credentials', 401);
        }

        // D. (Optional) Ensure only Listeners/Admins can log in via this route
        // if ($user->role !== 'listener' && $user->role !== 'admin') {
        //     return $this->errorResponse('Access Denied', 403);
        // }

        // E. Generate Token
        $token = $user->createToken('auth_token')->plainTextToken;

        // F. Return JSON
        return $this->successResponse([
            'user' => $user,
            'token' => $token
        ], 'User Logged In Successfully');
    }

    // --- ADMIN WEB LOGIN (Keep this for your Admin Panel) ---

    public function showLoginForm()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        
        return view('admin.auth.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            $user = Auth::user();

            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            if($user->role === 'listener'){
                return redirect()->route('listener.dashboard');
            }

            if($user->role === 'doctor'){
                return redirect()->route('psychiatrist.dashboard');
            }

            Auth::logout();
            return back()->withErrors([
                'email' => 'Access denied. You are not an Admin.',
            ]);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        if ($request->wantsJson()) {
            $request->user()->currentAccessToken()->delete();
            return $this->successResponse(null, 'Logged out successfully');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}