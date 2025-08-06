<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-Platform API Rate Limiting Middleware
 *
 * Implements rate limiting for mobile and web API endpoints
 * Different limits for different platforms and endpoint types
 */
class CrossPlatformRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = $this->getMaxAttempts($request, $limiter);
        $decayMinutes = $this->getDecayMinutes($limiter);

        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'platform' => $this->getPlatform($request),
                'endpoint' => $request->path(),
                'retry_after' => $retryAfter,
            ]);

            return $this->buildRateLimitResponse($retryAfter, $maxAttempts, $decayMinutes);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $key, $maxAttempts, $decayMinutes);
    }

    /**
     * Resolve the request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $platform = $this->getPlatform($request);
        $user = $this->getUser($request);

        if ($user) {
            // Authenticated user rate limiting
            return "api_rate_limit:{$platform}:user:{$user}";
        }

        // IP-based rate limiting for unauthenticated requests
        return "api_rate_limit:{$platform}:ip:{$request->ip()}";
    }

    /**
     * Get platform from request context
     */
    protected function getPlatform(Request $request): string
    {
        // Check if platform context was set by DualAuthenticationMiddleware
        if ($request->has('platform')) {
            return $request->get('platform');
        }

        // Fallback: detect platform from User-Agent or endpoint
        $userAgent = $request->userAgent();

        if (str_contains($userAgent, 'Flutter') || str_contains($userAgent, 'Dart')) {
            return 'mobile';
        }

        if ($request->is('api/gibran/*')) {
            return 'web';
        }

        return 'unknown';
    }

    /**
     * Get user identifier for authenticated requests
     */
    protected function getUser(Request $request): ?string
    {
        // Mobile user
        if ($request->get('authenticated_as') === 'mobile_user') {
            return 'mobile_'.$request->get('user_id');
        }

        // Web admin
        if ($request->get('authenticated_as') === 'web_admin') {
            return 'web_admin_'.$request->get('admin_id');
        }

        return null;
    }

    /**
     * Get maximum attempts based on platform and limiter type
     */
    protected function getMaxAttempts(Request $request, string $limiter): int
    {
        $platform = $this->getPlatform($request);

        return match ($limiter) {
            // Authentication endpoints - stricter limits
            'auth' => match ($platform) {
                'mobile' => 5, // 5 login attempts per period
                'web' => 3,    // 3 admin login attempts per period
                default => 3
            },

            // Password reset - very strict
            'password_reset' => 2, // Only 2 password reset attempts per period

            // File upload - moderate limits
            'upload' => match ($platform) {
                'mobile' => 10, // 10 file uploads per period
                'web' => 20,    // 20 file uploads per period (bulk operations)
                default => 5
            },

            // General API - generous limits
            'api' => match ($platform) {
                'mobile' => 100, // 100 requests per period for mobile
                'web' => 200,    // 200 requests per period for web dashboard
                default => 60
            },

            // Report submission - moderate limits
            'reports' => match ($platform) {
                'mobile' => 20, // 20 report submissions per period
                'web' => 50,    // 50 report operations per period
                default => 10
            },

            default => 60
        };
    }

    /**
     * Get decay minutes based on limiter type
     */
    protected function getDecayMinutes(string $limiter): int
    {
        return match ($limiter) {
            'auth' => 15,          // 15 minutes for auth attempts
            'password_reset' => 60, // 1 hour for password reset
            'upload' => 10,        // 10 minutes for file uploads
            'api' => 1,           // 1 minute for general API
            'reports' => 5,       // 5 minutes for report submissions
            default => 1
        };
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(int $retryAfter, int $maxAttempts, int $decayMinutes): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please slow down.',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'rate_limit' => [
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
                'retry_after_seconds' => $retryAfter,
                'retry_after_minutes' => ceil($retryAfter / 60),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID', uniqid()),
            ],
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, string $key, int $maxAttempts, int $decayMinutes): Response
    {
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        $retryAfter = RateLimiter::availableIn($key);

        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->unix(),
        ]);

        if ($remaining === 0) {
            $response->headers->set('Retry-After', $retryAfter);
        }

        return $response;
    }
}
