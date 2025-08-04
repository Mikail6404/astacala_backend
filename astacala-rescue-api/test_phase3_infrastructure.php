<?php

/**
 * Phase 3 Infrastructure Validation Test
 * Testing backend API endpoints functionality
 */

$baseUrl = 'http://localhost:8000';

echo "=== Phase 3 Infrastructure Validation Test ===\n\n";

// Test 1: Health Check
echo "1. Testing API Health Endpoint:\n";
$healthResponse = file_get_contents($baseUrl . '/api/health');
echo "Response: " . $healthResponse . "\n\n";

// Test 2: Authentication Test
echo "2. Testing Authentication System:\n";
$loginData = json_encode([
    'email' => 'test@astacala.com',
    'password' => 'password'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        'content' => $loginData
    ]
]);

$authResponse = file_get_contents($baseUrl . '/api/v1/auth/login', false, $context);
echo "Login Response: " . $authResponse . "\n\n";

// Test 3: Disaster Reports Endpoint
echo "3. Testing Disaster Reports Endpoint (without auth):\n";
$reportsContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: application/json'
        ]
    ]
]);

$reportsResponse = file_get_contents($baseUrl . '/api/v1/reports', false, $reportsContext);
echo "Reports Response: " . $reportsResponse . "\n\n";

echo "=== Infrastructure Validation Complete ===\n";
