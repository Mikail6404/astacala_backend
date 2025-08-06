<?php

// Debug response structures
$baseUrl = 'http://127.0.0.1:8000';

echo "=== Response Structure Debug ===\n\n";

// Mobile auth
echo "1. Mobile Registration Response:\n";
$mobileData = [
    'name' => 'Debug User Mobile',
    'email' => 'debug_mobile_'.time().'@test.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'role' => 'VOLUNTEER',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl.'/api/v1/auth/register');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mobileData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$parsedResponse = json_decode($response, true);
echo "Parsed Structure:\n";
print_r($parsedResponse);
echo "\n";

// Web auth
echo "2. Web Authentication Response:\n";
$webData = [
    'email' => 'admin@web.test',
    'password' => 'password',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl.'/api/gibran/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$parsedResponse = json_decode($response, true);
echo "Parsed Structure:\n";
print_r($parsedResponse);
echo "\n";
