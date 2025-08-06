<?php

// Test different admin endpoints to see what's working
require_once 'vendor/autoload.php';

echo "Testing multiple admin endpoints:\n";
echo "=================================\n";

// Get fresh token
$loginData = [
    'email' => 'admin@uat.test',
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $token = $data['data']['tokens']['accessToken'];
    echo "Got token: $token\n\n";

    // Test various endpoints
    $endpoints = [
        '/api/v1/users/profile',
        '/api/v1/users/statistics',
        '/api/v1/users/admin-list',
        '/api/v1/users/reports',
    ];

    foreach ($endpoints as $endpoint) {
        echo "Testing $endpoint:\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "  Status: $code\n";
        if ($code !== 200) {
            echo "  Response: $resp\n";
        } else {
            $decoded = json_decode($resp, true);
            echo "  Success: " . ($decoded['success'] ? 'YES' : 'NO') . "\n";
            echo "  Message: " . ($decoded['message'] ?? 'N/A') . "\n";
        }
        echo "\n";
    }
}
