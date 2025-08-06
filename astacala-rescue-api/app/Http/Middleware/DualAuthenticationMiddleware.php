<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Dual Authentication Middleware
 *
 * Handles both mobile JWT authentication and web session authentication
 * No role switching - just platform identification for backend API
 */
class DualAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info('DualAuthenticationMiddleware: Processing request', [
            'url' => $request->url(),
            'method' => $request->method(),
            'has_bearer_token' => $request->bearerToken() ? true : false,
            'has_session' => $request->hasSession(),
            'session_admin_id' => $request->hasSession() ? $request->session()->get('admin_id') : null,
        ]);

        // Handle mobile JWT tokens (existing system)
        if ($request->bearerToken()) {
            return $this->authenticateJWT($request, $next);
        }

        // Handle web session cookies (existing system)
        if ($request->hasSession()) {
            if ($request->session()->has('admin_id')) {
                return $this->authenticateSession($request, $next);
            } else {
                // Session exists but no admin_id - invalid session
                Log::warning('DualAuthenticationMiddleware: Session exists but no admin_id found');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session - admin not logged in',
                    'error_code' => 'INVALID_SESSION',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ],
                ], 401);
            }
        }

        Log::warning('DualAuthenticationMiddleware: No valid authentication found');

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - No valid authentication provided',
            'error_code' => 'AUTH_REQUIRED',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ],
        ], 401);
    }

    /**
     * Authenticate mobile user via JWT token
     */
    private function authenticateJWT(Request $request, Closure $next)
    {
        try {
            // Use Laravel Sanctum for JWT authentication
            $user = Auth::guard('sanctum')->user();

            if (! $user) {
                Log::warning('DualAuthenticationMiddleware: Invalid JWT token');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token',
                    'error_code' => 'INVALID_TOKEN',
                ], 401);
            }

            // Add mobile user context
            $request->merge([
                'authenticated_as' => 'mobile_user',
                'platform' => 'mobile',
                'user_type' => 'volunteer',
                'user_id' => $user->id,
                'auth_method' => 'jwt_token',
            ]);

            Log::info('DualAuthenticationMiddleware: Mobile user authenticated', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('DualAuthenticationMiddleware: JWT authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error_code' => 'AUTH_FAILED',
            ], 401);
        }
    }

    /**
     * Authenticate web admin via session
     */
    private function authenticateSession(Request $request, Closure $next)
    {
        try {
            $adminId = $request->session()->get('admin_id');

            if (! $adminId) {
                Log::warning('DualAuthenticationMiddleware: No admin_id in session');

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid session - admin not logged in',
                    'error_code' => 'INVALID_SESSION',
                ], 401);
            }

            // Add web admin context (no user model needed, just admin_id)
            $request->merge([
                'authenticated_as' => 'web_admin',
                'platform' => 'web',
                'user_type' => 'admin',
                'admin_id' => $adminId,
                'auth_method' => 'session_cookie',
            ]);

            Log::info('DualAuthenticationMiddleware: Web admin authenticated', [
                'admin_id' => $adminId,
            ]);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('DualAuthenticationMiddleware: Session authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Session authentication failed',
                'error_code' => 'SESSION_AUTH_FAILED',
            ], 401);
        }
    }
}
