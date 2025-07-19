<?php

// Simple API test script for Astacala Rescue
echo "🧪 Testing Astacala Rescue API\n\n";

$baseUrl = 'http://127.0.0.1:8000/api';

// Test 1: Simple GET request to check if API is accessible
echo "=== Test 1: Check API Access ===\n";
$testUrl = $baseUrl . '/auth/register';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}'); // Empty JSON to test validation

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode == 422) {
    echo "✅ API is accessible and validation is working!\n\n";

    // Test 2: Proper registration
    echo "=== Test 2: User Registration ===\n";
    $uniqueEmail = 'test' . time() . '@astacala.org';
    $registerData = json_encode([
        'name' => 'Test User',
        'email' => $uniqueEmail,
        'password' => 'password123',
        'phone' => '+62812345678',
        'role' => 'VOLUNTEER'
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/register');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $registerData);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";

    $registerResult = json_decode($response, true);

    if (isset($registerResult['success']) && $registerResult['success']) {
        echo "✅ Registration successful!\n\n";

        // Test 3: Login
        echo "=== Test 3: User Login ===\n";
        $loginData = json_encode([
            'email' => $uniqueEmail,
            'password' => 'password123'
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "HTTP Code: $httpCode\n";
        echo "Response: $response\n\n";

        $loginResult = json_decode($response, true);

        if (isset($loginResult['data']['tokens']['accessToken'])) {
            $token = $loginResult['data']['tokens']['accessToken'];
            echo "✅ Login successful! Token: " . substr($token, 0, 30) . "...\n\n";

            // Test 4: Protected endpoint
            echo "=== Test 4: Protected Endpoint (/auth/me) ===\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/auth/me');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "HTTP Code: $httpCode\n";
            echo "Response: $response\n\n";

            if ($httpCode == 200) {
                echo "✅ Protected endpoint works! Authentication is working!\n";
            } else {
                echo "❌ Protected endpoint failed\n";
            }
        } else {
            echo "❌ Login failed\n";
        }
    } else {
        echo "❌ Registration failed\n";
        if (isset($registerResult['message'])) {
            echo "Error: " . $registerResult['message'] . "\n";
        }
    }
} else {
    echo "❌ API is not accessible\n";
}

echo "\n🏁 API testing completed!\n";
