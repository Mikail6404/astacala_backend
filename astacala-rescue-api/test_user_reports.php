<?php

/**
 * Test script for user reports API endpoint
 */

require_once 'vendor/autoload.php';

$baseUrl = 'http://localhost:8000/api';

// Test authentication and get token
echo "=== Testing User Reports API ===\n";

// First, login to get token
$loginData = [
    'email' => 'test@example.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
$loginData = json_decode($loginResponse, true);

if (!$loginData || !isset($loginData['success']) || !$loginData['success']) {
    echo "Login failed: " . $loginResponse . "\n";
    exit(1);
}

// Check if token is nested in data or directly available
$token = isset($loginData['data']['tokens']['accessToken']) ? $loginData['data']['tokens']['accessToken'] : (isset($loginData['data']['token']) ? $loginData['data']['token'] : (isset($loginData['token']) ? $loginData['token'] : null));

if (!$token) {
    echo "No token found in response: " . $loginResponse . "\n";
    exit(1);
}

echo "✅ Login successful, token obtained\n";

// Test user reports endpoint
echo "\n=== Testing GET /api/users/reports ===\n";

curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users/reports');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_POSTFIELDS, null);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);

$reportsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Status: $httpCode\n";
echo "Response: " . $reportsResponse . "\n";

$reportsData = json_decode($reportsResponse, true);

if ($httpCode === 200 && $reportsData && isset($reportsData['success']) && $reportsData['success']) {
    echo "✅ User reports API working!\n";
    echo "Total reports: " . count($reportsData['data']['reports']) . "\n";

    if (isset($reportsData['data']['statistics'])) {
        $stats = $reportsData['data']['statistics'];
        echo "User Statistics:\n";
        echo "  - Total: " . $stats['total_reports'] . "\n";
        echo "  - Pending: " . $stats['pending_reports'] . "\n";
        echo "  - Resolved: " . $stats['resolved_reports'] . "\n";
        echo "  - In Progress: " . $stats['in_progress_reports'] . "\n";
    }
} else {
    echo "❌ User reports API failed\n";
}

curl_close($ch);

echo "\n=== Test Complete ===\n";
