<?php

echo "🔍 DEBUG TEST - Authentication Response\n";
echo "=====================================\n";

$baseUrl = 'http://localhost:8000';
$testUser = [
    'email' => 'test@astacala.com',
    'password' => 'password123'
];

function makeHttpRequest($url, $method = 'GET', $data = [], $headers = [])
{
    $ch = curl_init();

    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true),
        'error' => $error
    ];
}

echo "Testing authentication...\n";

$authResponse = makeHttpRequest("$baseUrl/api/v1/auth/login", 'POST', $testUser);

echo "HTTP Status: {$authResponse['status']}\n";
echo "Success: " . ($authResponse['success'] ? 'YES' : 'NO') . "\n";
echo "Response Body: {$authResponse['body']}\n";
echo "Parsed Data: " . print_r($authResponse['data'], true) . "\n";

if ($authResponse['data']) {
    echo "Has data: YES\n";
    if (isset($authResponse['data']['tokens'])) {
        echo "Has tokens: YES\n";
        if (isset($authResponse['data']['tokens']['accessToken'])) {
            echo "Has accessToken: YES\n";
            echo "Token: " . $authResponse['data']['tokens']['accessToken'] . "\n";
        } else {
            echo "Has accessToken: NO\n";
            echo "Available keys in tokens: " . implode(', ', array_keys($authResponse['data']['tokens'])) . "\n";
        }
    } else {
        echo "Has tokens: NO\n";
        echo "Available keys in data: " . implode(', ', array_keys($authResponse['data'])) . "\n";
    }
} else {
    echo "Has data: NO\n";
}
