<?php

// Check existing users and test authentication with known credentials
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Database User Analysis ===\n";

try {
    // Get all users
    $users = \Illuminate\Support\Facades\DB::table('users')
        ->select('id', 'name', 'email', 'role', 'created_at')
        ->get();

    echo 'Total users: '.$users->count()."\n\n";

    echo "User accounts:\n";
    foreach ($users->take(10) as $user) {
        echo "- ID: {$user->id}, Name: {$user->name}, Email: {$user->email}, Role: {$user->role}\n";
    }

    echo "\n=== Testing Known User Authentication ===\n";

    // Try to authenticate with a known user
    $testEmail = 'volunteer@mobile.test';
    $testPassword = 'TestPassword123!';

    $baseUrl = 'http://127.0.0.1:8000';

    // Test standard login
    $loginData = [
        'email' => $testEmail,
        'password' => $testPassword,
    ];

    echo "Testing standard API login...\n";
    $response = makeRequest('POST', '/api/v1/auth/login', $loginData);
    echo 'Response: '.json_encode($response, JSON_PRETTY_PRINT)."\n\n";

    // Test Gibran login
    echo "Testing Gibran API login...\n";
    $response = makeRequest('POST', '/api/gibran/auth/login', $loginData);
    echo 'Response: '.json_encode($response, JSON_PRETTY_PRINT)."\n";
} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
}

function makeRequest($method, $endpoint, $data = [])
{
    global $baseUrl;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl.$endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'data' => $responseData,
        'raw_response' => $response,
    ];
}
