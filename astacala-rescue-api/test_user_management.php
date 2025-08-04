<?php

/**
 * Phase 3 User Management Synchronization Test
 * Testing cross-platform user management functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 User Management Synchronization Test ===\n\n";

// Step 1: Authenticate and get token
echo "1. Authenticating test user:\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';
$loginData = [
    'email' => $testUser->email,
    'password' => 'password',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
curl_close($ch);

$loginData = json_decode($loginResponse, true);
$token = $loginData['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Step 2: Test user profile retrieval
echo "2. Testing user profile retrieval:\n";
$profileUrl = 'http://127.0.0.1:8000/api/v1/users/profile';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profileUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$profileResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Profile response (HTTP $httpCode): $profileResponse\n\n";

// Step 3: Test user profile update
echo "3. Testing user profile update:\n";
$updateData = [
    'name' => 'Updated Test User',
    'phone' => '087654321098',
    'organization' => 'Phase 3 Test Organization'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profileUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$updateResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Update response (HTTP $httpCode): $updateResponse\n\n";

// Step 4: Verify update by retrieving profile again
echo "4. Verifying update by retrieving profile again:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $profileUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$verifyResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Verification response (HTTP $httpCode): $verifyResponse\n\n";

// Step 5: Test admin functionality (create admin user for testing)
echo "5. Testing admin functionality:\n";
$adminUser = App\Models\User::where('role', 'ADMIN')->first();
if (!$adminUser) {
    $adminUser = App\Models\User::create([
        'name' => 'Test Admin',
        'email' => 'admin@astacala.com',
        'password' => bcrypt('password'),
        'role' => 'ADMIN',
        'phone' => '081234567890',
        'is_active' => true
    ]);
    echo "✅ Admin user created for testing\n";
}

// Login as admin
$adminLoginData = [
    'email' => $adminUser->email,
    'password' => 'password',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($adminLoginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$adminLoginResponse = curl_exec($ch);
curl_close($ch);

$adminLoginData = json_decode($adminLoginResponse, true);
if (isset($adminLoginData['data']['tokens']['accessToken'])) {
    $adminToken = $adminLoginData['data']['tokens']['accessToken'];
    echo "✅ Admin authentication successful\n";

    // Test admin user list
    $adminListUrl = 'http://127.0.0.1:8000/api/v1/users/admin-list';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $adminListUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $adminToken,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $adminListResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Admin user list response (HTTP $httpCode): $adminListResponse\n\n";
} else {
    echo "❌ Admin authentication failed\n\n";
}

// Step 6: Test direct database synchronization
echo "6. Testing database synchronization:\n";
$dbUser = App\Models\User::find($testUser->id);
echo "Database user name: {$dbUser->name}\n";
echo "Database user phone: {$dbUser->phone}\n";
echo "Database user organization: " . ($dbUser->organization ?? 'None') . "\n";

echo "\n✅ User management synchronization test complete\n";
echo "=== User Management Test Complete ===\n";
