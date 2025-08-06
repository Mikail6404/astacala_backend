<?php

namespace App\Http\Middleware;

use App\Services\SuspiciousActivityMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Request Logging Middleware
 *
 * Comprehensive logging for API requests with security monitoring
 * Tracks authentication, performance, and potential security threats
 */
class ApiRequestLoggingMiddleware
{
    protected SuspiciousActivityMonitoringService $securityMonitor;

    public function __construct(SuspiciousActivityMonitoringService $securityMonitor)
    {
        $this->securityMonitor = $securityMonitor;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = $request->header('X-Request-ID', uniqid());

        // Analyze request for suspicious activity before processing
        $suspiciousIndicators = $this->securityMonitor->analyzeRequest($request);

        // Block request if client is blocked
        if (in_array('CLIENT_BLOCKED', $suspiciousIndicators)) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your request has been blocked due to suspicious activity',
            ], 403);
        }

        // Log incoming request
        $this->logIncomingRequest($request, $requestId, $suspiciousIndicators);

        // Process request
        $response = $next($request);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Log response
        $this->logResponse($request, $response, $responseTime, $requestId);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Log incoming request details
     */
    protected function logIncomingRequest(Request $request, string $requestId, array $suspiciousIndicators = []): void
    {
        $logData = [
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'platform' => $this->getPlatform($request),
            'authentication' => $this->getAuthenticationInfo($request),
            'headers' => $this->getSafeHeaders($request),
            'query_params' => $request->query(),
            'payload_size' => strlen($request->getContent()),
            'content_type' => $request->header('Content-Type'),
            'suspicious_indicators' => $suspiciousIndicators,
        ];

        // Add request body for non-sensitive endpoints (excluding passwords)
        if ($this->shouldLogRequestBody($request)) {
            $logData['request_body'] = $this->sanitizeRequestBody($request);
        }

        Log::channel('api')->info('API Request', $logData);
    }

    /**
     * Log response details
     */
    protected function logResponse(Request $request, Response $response, float $responseTime, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime, 2),
            'response_size' => strlen($response->getContent()),
            'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'database_queries' => $this->getDatabaseQueryCount(),
        ];

        // Log response body for errors or if debugging enabled
        if ($response->getStatusCode() >= 400 || config('app.debug')) {
            $logData['response_body'] = $this->sanitizeResponseBody($response);
        }

        $logLevel = $this->getLogLevel($response->getStatusCode());
        Log::channel('api')->{$logLevel}('API Response', $logData);
    }

    /**
     * Get database query count
     */
    protected function getDatabaseQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    /**
     * Get log level based on status code
     */
    protected function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info'
        };
    }

    /**
     * Get safe headers (excluding sensitive information)
     */
    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        // Remove sensitive headers
        unset($headers['authorization'], $headers['cookie'], $headers['x-api-key']);

        return $headers;
    }

    /**
     * Get platform information from request
     */
    protected function getPlatform(Request $request): string
    {
        return $request->get('platform', 'unknown');
    }

    /**
     * Get authentication information
     */
    protected function getAuthenticationInfo(Request $request): array
    {
        return [
            'authenticated_as' => $request->get('authenticated_as', 'unauthenticated'),
            'user_type' => $request->get('user_type', 'unknown'),
            'auth_method' => $request->get('auth_method', 'none'),
            'user_id' => $request->get('user_id'),
            'admin_id' => $request->get('admin_id'),
        ];
    }

    /**
     * Check if request body should be logged
     */
    protected function shouldLogRequestBody(Request $request): bool
    {
        $sensitiveEndpoints = [
            'auth/login',
            'auth/register',
            'auth/forgot-password',
            'auth/reset-password',
            'auth/change-password',
        ];

        foreach ($sensitiveEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize request body to remove sensitive data
     */
    protected function sanitizeRequestBody(Request $request): array
    {
        $body = $request->all();

        // Remove sensitive fields
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'api_key',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[REDACTED]';
            }
        }

        return $body;
    }

    /**
     * Sanitize response body
     */
    protected function sanitizeResponseBody(Response $response): array
    {
        $content = $response->getContent();

        if (! $content) {
            return [];
        }

        $decoded = json_decode($content, true);

        if (! $decoded) {
            return ['raw_content' => substr($content, 0, 500)]; // First 500 chars
        }

        // Remove sensitive response data
        if (isset($decoded['data']['tokens'])) {
            $decoded['data']['tokens'] = '[REDACTED]';
        }

        return $decoded;
    }
}
