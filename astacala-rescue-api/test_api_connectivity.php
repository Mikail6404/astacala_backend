<?php

// Simple API connectivity test for cross-platform integration validation
// This script tests if the backend API endpoints are responding correctly

$baseUrl = 'http://127.0.0.1:8000';

echo "=== Cross-Platform Integration API Connectivity Test ===\n";
echo "Base URL: $baseUrl\n\n";

// Test endpoints
$endpoints = [
    'Health Check' => '/api/v1/health',
    'Auth Register' => '/api/v1/auth/register',
    'Reports Index' => '/api/v1/reports',
    'Gibran Auth' => '/api/gibran/auth/login',
    'Gibran Dashboard' => '/api/gibran/dashboard/statistics',
];

foreach ($endpoints as $name => $endpoint) {
    $url = $baseUrl.$endpoint;
    echo "Testing $name: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "  ❌ ERROR: $error\n";
    } else {
        echo "  ✅ HTTP $httpCode\n";
    }
    echo "\n";
}

echo "Test completed.\n";
