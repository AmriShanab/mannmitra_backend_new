<?php

namespace App\Http\Controllers; 

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestLoginRequest; 
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use ApiResponse;

    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    // --- FLUTTER APP LOGIN (Keep as is) ---
    public function guestLogin(GuestLoginRequest $request)
    {
        return $this->authService->handleAnonymousLogin($request->validated());
    }

    // --- ADMIN WEB LOGIN START ---

    public function showLoginForm()
    {
        // If already logged in as admin, go straight to dashboard
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        
        return view('admin.auth.login');
    }

    // UPDATED: This now handles Browser Login (Session) instead of API Token
    public function login(Request $request)
    {
        // 1. Validate Input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Attempt Login using Laravel Session Auth
        // 'remember' checks the checkbox input if present
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            $user = Auth::user();

            // 3. Security Check: Is this an Admin?
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            // If user logged in but is NOT admin (e.g. a Listener trying to hack in)
            Auth::logout();
            return back()->withErrors([
                'email' => 'Access denied. You are not an Admin.',
            ]);
        }

        // 4. Failed Login
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}