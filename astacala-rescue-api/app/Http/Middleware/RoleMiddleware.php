<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Auth::user();

        // Check if user has any of the required roles
        if (!empty($roles)) {
            $userRole = strtolower($user->role ?? 'user'); // Normalize to lowercase
            $normalizedRoles = array_map('strtolower', $roles); // Normalize required roles

            if (!in_array($userRole, $normalizedRoles)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles),
                    'user_role' => $user->role,
                    'required_roles' => $roles
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
