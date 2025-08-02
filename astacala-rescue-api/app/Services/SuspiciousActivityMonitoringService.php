<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Suspicious Activity Monitoring Service
 * 
 * Advanced threat detection and security monitoring for API requests
 * Implements pattern recognition, rate limiting, and automated response
 */
class SuspiciousActivityMonitoringService
{
    protected const CACHE_PREFIX = 'security_monitor:';
    protected const DEFAULT_THRESHOLD_MINUTES = 60;
    protected const DEFAULT_BLOCK_DURATION = 1440; // 24 hours

    protected array $thresholds = [
        'failed_auth_attempts' => 5,
        'rapid_requests' => 50,
        'unique_endpoints' => 20,
        'payload_size_mb' => 10,
        'suspicious_patterns' => 3,
        'blocked_duration_minutes' => self::DEFAULT_BLOCK_DURATION,
    ];

    /**
     * Analyze request for suspicious activity
     */
    public function analyzeRequest(Request $request): array
    {
        $clientIdentifier = $this->getClientIdentifier($request);
        $suspiciousIndicators = [];

        // Check if client is already blocked
        if ($this->isClientBlocked($clientIdentifier)) {
            $suspiciousIndicators[] = 'CLIENT_BLOCKED';
            return $suspiciousIndicators;
        }

        // Perform various security checks
        $checks = [
            'checkFailedAuthenticationPattern' => $this->checkFailedAuthenticationPattern($request, $clientIdentifier),
            'checkRapidRequestPattern' => $this->checkRapidRequestPattern($request, $clientIdentifier),
            'checkSuspiciousEndpointScanning' => $this->checkSuspiciousEndpointScanning($request, $clientIdentifier),
            'checkPayloadSizeAnomaly' => $this->checkPayloadSizeAnomaly($request),
            'checkSqlInjectionPatterns' => $this->checkSqlInjectionPatterns($request),
            'checkXssPatterns' => $this->checkXssPatterns($request),
            'checkDirectoryTraversalPatterns' => $this->checkDirectoryTraversalPatterns($request),
            'checkBotLikeUserAgent' => $this->checkBotLikeUserAgent($request),
            'checkGeographicAnomalies' => $this->checkGeographicAnomalies($request, $clientIdentifier),
            'checkUnusualHttpMethods' => $this->checkUnusualHttpMethods($request),
        ];

        foreach ($checks as $checkName => $result) {
            if ($result) {
                $suspiciousIndicators[] = strtoupper(str_replace('check', '', $checkName));
            }
        }

        // Evaluate threat level and take action
        $threatLevel = $this->evaluateThreatLevel($suspiciousIndicators);

        if ($threatLevel >= 3) {
            $this->blockClient($clientIdentifier, $threatLevel);
            $suspiciousIndicators[] = 'AUTO_BLOCKED';
        }

        // Log activity
        $this->logSuspiciousActivity($request, $clientIdentifier, $suspiciousIndicators, $threatLevel);

        return $suspiciousIndicators;
    }

    /**
     * Get unique client identifier
     */
    protected function getClientIdentifier(Request $request): string
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        return md5($ip . '|' . $userAgent);
    }

    /**
     * Check if client is currently blocked
     */
    protected function isClientBlocked(string $clientIdentifier): bool
    {
        return Cache::has(self::CACHE_PREFIX . 'blocked:' . $clientIdentifier);
    }

    /**
     * Block client for specified duration
     */
    protected function blockClient(string $clientIdentifier, int $threatLevel): void
    {
        $blockDuration = min($threatLevel * 60, $this->thresholds['blocked_duration_minutes']);

        Cache::put(
            self::CACHE_PREFIX . 'blocked:' . $clientIdentifier,
            [
                'blocked_at' => now(),
                'threat_level' => $threatLevel,
                'expires_at' => now()->addMinutes($blockDuration)
            ],
            $blockDuration * 60 // Convert to seconds
        );

        Log::channel('security')->emergency('Client Auto-Blocked', [
            'client_identifier' => $clientIdentifier,
            'threat_level' => $threatLevel,
            'block_duration_minutes' => $blockDuration,
            'blocked_until' => now()->addMinutes($blockDuration)->toISOString(),
        ]);
    }

    /**
     * Check for failed authentication patterns
     */
    protected function checkFailedAuthenticationPattern(Request $request, string $clientIdentifier): bool
    {
        // Only check auth endpoints
        if (!str_contains($request->path(), 'auth/login')) {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX . 'failed_auth:' . $clientIdentifier;
        $attempts = Cache::get($cacheKey, 0);

        // This would be called after processing the request
        // For now, we'll check if it's a POST to login endpoint
        if ($request->isMethod('POST')) {
            Cache::put($cacheKey, $attempts + 1, self::DEFAULT_THRESHOLD_MINUTES * 60);
            return ($attempts + 1) >= $this->thresholds['failed_auth_attempts'];
        }

        return false;
    }

    /**
     * Check for rapid request patterns
     */
    protected function checkRapidRequestPattern(Request $request, string $clientIdentifier): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'requests:' . $clientIdentifier;
        $requests = Cache::get($cacheKey, []);

        // Add current timestamp
        $requests[] = time();

        // Keep only requests from last minute
        $requests = array_filter($requests, fn($timestamp) => $timestamp > (time() - 60));

        Cache::put($cacheKey, $requests, 300); // Keep for 5 minutes

        return count($requests) >= $this->thresholds['rapid_requests'];
    }

    /**
     * Check for suspicious endpoint scanning
     */
    protected function checkSuspiciousEndpointScanning(Request $request, string $clientIdentifier): bool
    {
        $cacheKey = self::CACHE_PREFIX . 'endpoints:' . $clientIdentifier;
        $endpoints = Cache::get($cacheKey, []);

        $currentEndpoint = $request->path();

        if (!in_array($currentEndpoint, $endpoints)) {
            $endpoints[] = $currentEndpoint;
        }

        // Keep endpoints from last hour
        Cache::put($cacheKey, $endpoints, self::DEFAULT_THRESHOLD_MINUTES * 60);

        return count($endpoints) >= $this->thresholds['unique_endpoints'];
    }

    /**
     * Check for payload size anomalies
     */
    protected function checkPayloadSizeAnomaly(Request $request): bool
    {
        $contentLength = $request->header('Content-Length', 0);
        $payloadSizeMB = $contentLength / (1024 * 1024);

        return $payloadSizeMB > $this->thresholds['payload_size_mb'];
    }

    /**
     * Check for SQL injection patterns
     */
    protected function checkSqlInjectionPatterns(Request $request): bool
    {
        $suspiciousPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\'\s*(OR|AND)\s*\'\s*=\s*\')/i',
            '/(\s*OR\s*1\s*=\s*1)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\bxp_cmdshell\b)/i',
            '/(\bsp_executesql\b)/i',
        ];

        $content = $request->getContent();
        $queryString = $request->getQueryString() ?? '';
        $allInput = $content . ' ' . $queryString;

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $allInput)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for XSS patterns
     */
    protected function checkXssPatterns(Request $request): bool
    {
        $suspiciousPatterns = [
            '/<script[\s\S]*?>[\s\S]*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[\s\S]*?>/i',
            '/<object[\s\S]*?>/i',
            '/<embed[\s\S]*?>/i',
            '/expression\s*\(/i',
            '/vbscript:/i',
        ];

        $content = $request->getContent();
        $queryString = $request->getQueryString() ?? '';
        $allInput = $content . ' ' . $queryString;

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $allInput)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for directory traversal patterns
     */
    protected function checkDirectoryTraversalPatterns(Request $request): bool
    {
        $suspiciousPatterns = [
            '/\.\.\//i',
            '/\.\.[\\\\]/i',
            '/%2e%2e%2f/i',
            '/%2e%2e%5c/i',
            '/\.\.\%2f/i',
            '/etc\/passwd/i',
            '/windows\/system32/i',
        ];

        $fullUrl = $request->fullUrl();
        $content = $request->getContent();

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $fullUrl . ' ' . $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for bot-like user agents
     */
    protected function checkBotLikeUserAgent(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        $botPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'parser',
            'curl',
            'wget',
            'httpie',
            'postman',
            'insomnia',
            'python-requests',
            'axios',
            'fetch',
            'node-fetch',
            'scanner',
            'exploit',
            'attack',
            'hack',
            'pen',
            'sqlmap',
            'nmap',
            'nikto',
            'burp',
            'zap'
        ];

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for geographic anomalies
     */
    protected function checkGeographicAnomalies(Request $request, string $clientIdentifier): bool
    {
        // This would typically integrate with a GeoIP service
        // For now, we'll implement a basic check

        $cacheKey = self::CACHE_PREFIX . 'geo:' . $clientIdentifier;
        $previousLocation = Cache::get($cacheKey);

        // Mock location data - in production, use actual GeoIP service
        $currentLocation = $this->getMockLocationFromIP($request->ip());

        if ($previousLocation && $currentLocation) {
            $distance = $this->calculateDistance(
                $previousLocation['lat'],
                $previousLocation['lon'],
                $currentLocation['lat'],
                $currentLocation['lon']
            );

            // Flag if client appears to have moved > 1000km in < 1 hour
            $timeDiff = time() - ($previousLocation['timestamp'] ?? 0);
            if ($distance > 1000 && $timeDiff < 3600) {
                return true;
            }
        }

        if ($currentLocation) {
            Cache::put($cacheKey, array_merge($currentLocation, ['timestamp' => time()]), 86400);
        }

        return false;
    }

    /**
     * Check for unusual HTTP methods
     */
    protected function checkUnusualHttpMethods(Request $request): bool
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        return !in_array(strtoupper($request->method()), $allowedMethods);
    }

    /**
     * Evaluate overall threat level
     */
    protected function evaluateThreatLevel(array $indicators): int
    {
        $highRiskIndicators = [
            'SQL_INJECTION_PATTERNS',
            'DIRECTORY_TRAVERSAL_PATTERNS',
            'FAILED_AUTHENTICATION_PATTERN',
            'CLIENT_BLOCKED'
        ];

        $mediumRiskIndicators = [
            'XSS_PATTERNS',
            'RAPID_REQUEST_PATTERN',
            'SUSPICIOUS_ENDPOINT_SCANNING',
            'BOT_LIKE_USER_AGENT'
        ];

        $threatLevel = 0;

        foreach ($indicators as $indicator) {
            if (in_array($indicator, $highRiskIndicators)) {
                $threatLevel += 3;
            } elseif (in_array($indicator, $mediumRiskIndicators)) {
                $threatLevel += 2;
            } else {
                $threatLevel += 1;
            }
        }

        return min($threatLevel, 10); // Cap at 10
    }

    /**
     * Log suspicious activity
     */
    protected function logSuspiciousActivity(Request $request, string $clientIdentifier, array $indicators, int $threatLevel): void
    {
        if (empty($indicators)) {
            return;
        }

        $logData = [
            'timestamp' => now()->toISOString(),
            'client_identifier' => $clientIdentifier,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_url' => $request->fullUrl(),
            'indicators' => $indicators,
            'threat_level' => $threatLevel,
            'payload_size' => strlen($request->getContent()),
            'headers' => $this->getSafeHeaders($request),
        ];

        $logLevel = match (true) {
            $threatLevel >= 7 => 'critical',
            $threatLevel >= 5 => 'error',
            $threatLevel >= 3 => 'warning',
            default => 'info'
        };

        Log::channel('security')->{$logLevel}('Suspicious Activity Detected', $logData);
    }

    /**
     * Get safe headers for logging
     */
    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        // Remove sensitive headers
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($sensitiveHeaders as $header) {
            unset($headers[$header]);
        }

        return $headers;
    }

    /**
     * Mock location data for testing
     */
    protected function getMockLocationFromIP(string $ip): ?array
    {
        // In production, this would use a real GeoIP service
        $mockLocations = [
            '127.0.0.1' => ['lat' => -6.2088, 'lon' => 106.8456, 'country' => 'ID'], // Jakarta
            '192.168.1.1' => ['lat' => -6.2088, 'lon' => 106.8456, 'country' => 'ID'],
            // Add more mock locations as needed
        ];

        return $mockLocations[$ip] ?? null;
    }

    /**
     * Calculate distance between two coordinates
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get current statistics for monitoring dashboard
     */
    public function getSecurityStatistics(): array
    {
        return [
            'blocked_clients' => $this->getBlockedClientsCount(),
            'threat_events_24h' => $this->getThreatEventsCount(24),
            'top_threat_types' => $this->getTopThreatTypes(),
            'geographic_distribution' => $this->getGeographicDistribution(),
            'attack_timeline' => $this->getAttackTimeline(),
        ];
    }

    /**
     * Get count of currently blocked clients
     */
    protected function getBlockedClientsCount(): int
    {
        // This would query the cache for active blocks
        // For now, return a mock value
        return 0;
    }

    /**
     * Get threat events count for specified hours
     */
    protected function getThreatEventsCount(int $hours): int
    {
        // This would query logs or database for threat events
        // For now, return a mock value
        return 0;
    }

    /**
     * Get top threat types
     */
    protected function getTopThreatTypes(): array
    {
        // This would analyze recent logs
        // For now, return mock data
        return [
            'SQL_INJECTION_PATTERNS' => 15,
            'RAPID_REQUEST_PATTERN' => 12,
            'BOT_LIKE_USER_AGENT' => 8,
            'FAILED_AUTHENTICATION_PATTERN' => 5,
        ];
    }

    /**
     * Get geographic distribution of threats
     */
    protected function getGeographicDistribution(): array
    {
        // This would analyze geographic data from logs
        return [
            'ID' => 45,
            'US' => 20,
            'CN' => 15,
            'RU' => 10,
            'Unknown' => 10,
        ];
    }

    /**
     * Get attack timeline
     */
    protected function getAttackTimeline(): array
    {
        // This would generate timeline data from logs
        $timeline = [];

        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i)->format('H:00');
            $timeline[$hour] = rand(0, 25); // Mock data
        }

        return $timeline;
    }
}
