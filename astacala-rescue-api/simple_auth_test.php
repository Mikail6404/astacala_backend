<?php

// Simple authentication test for Phase 3
$url = 'http://127.0.0.1:8000/api/v1/auth/login';
$data = [
    'email' => 'test@astacala.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

$decoded = json_decode($response, true);
if ($decoded && isset($decoded['success']) && $decoded['success']) {
    echo "✅ Authentication successful!\n";
    echo "Token: " . substr($decoded['data']['token'], 0, 20) . "...\n";
} else {
    echo "❌ Authentication failed\n";
    echo "Error: " . ($decoded['message'] ?? 'Unknown error') . "\n";
}
