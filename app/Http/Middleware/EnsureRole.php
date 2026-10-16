<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to staff roles, e.g. ->middleware('role:doctor,admin').
 * Anonymous app users must never reach doctor/listener endpoints.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, $roles, true)) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
