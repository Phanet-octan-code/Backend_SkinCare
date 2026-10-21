<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Enforce server-side role-based access control.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Administrator always possesses root authorization if admin is in roles or standalone
        if (!in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Forbidden: You do not have permission to access this resource.',
                    'required_roles' => $roles,
                    'your_role' => $user->role,
                ], 403);
            }

            abort(403, "Access Denied: Your role [{$user->role}] is not authorized to access this resource.");
        }

        return $next($request);
    }
}
