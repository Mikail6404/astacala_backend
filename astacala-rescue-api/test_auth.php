<?php
// Simple authentication test
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AUTHENTICATION SYSTEM TEST ===\n";

// Test 1: Check if test users exist
echo "\n--- TEST 1: Check Test Users ---\n";
$testUser = App\Models\User::where('email', 'test@example.com')->first();
if ($testUser) {
    echo "✅ Test user found: {$testUser->name} ({$testUser->email})\n";
    echo "   Role: {$testUser->role}, Organization: {$testUser->organization}\n";
} else {
    echo "❌ Test user not found!\n";
    exit(1);
}

// Test 2: Test login API endpoint
echo "\n--- TEST 2: Test Login API ---\n";
$loginUrl = 'http://127.0.0.1:8000/api/auth/login';
$loginData = [
    'email' => 'test@example.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login Response (HTTP $httpCode):\n";
$responseData = json_decode($response, true);

$token = null;
if ($httpCode === 200 && $responseData['success'] === true) {
    // Handle nested token structure
    if (isset($responseData['data']['tokens']['accessToken'])) {
        $token = $responseData['data']['tokens']['accessToken'];
    } elseif (isset($responseData['access_token'])) {
        $token = $responseData['access_token'];
    }
}

if ($token) {
    echo "✅ LOGIN SUCCESSFUL!\n";
    echo "   Token: " . substr($token, 0, 20) . "...\n";
    echo "   User: {$responseData['data']['user']['name']} ({$responseData['data']['user']['email']})\n";

    // Test 3: Test authenticated request
    echo "\n--- TEST 3: Test Authenticated Request ---\n";
    $profileUrl = 'http://127.0.0.1:8000/api/users/profile';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profileUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $profileResponse = curl_exec($ch);
    $profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Profile Response (HTTP $profileHttpCode):\n";
    if ($profileHttpCode === 200) {
        $profileData = json_decode($profileResponse, true);
        echo "✅ AUTHENTICATED REQUEST SUCCESSFUL!\n";
        echo "   Profile: {$profileData['name']} ({$profileData['email']})\n";
    } else {
        echo "❌ Authenticated request failed: $profileResponse\n";
    }
} else {
    echo "❌ LOGIN FAILED!\n";
    echo "Response: $response\n";
}

echo "\n=== AUTHENTICATION TEST COMPLETE ===\n";
