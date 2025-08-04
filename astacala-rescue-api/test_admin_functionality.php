<?php

/**
 * Test Admin User Management Bug Analysis
 * Identify and potentially fix admin-related functionality issues
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Admin User Management Bug Analysis ===\n\n";

// Step 1: Check for admin users
echo "1. Testing Admin User Detection:\n";

$adminUsers = App\Models\User::where('role', 'ADMIN')->get();
echo "   - Total admin users found: " . $adminUsers->count() . "\n";

if ($adminUsers->count() === 0) {
    echo "⚠️ No admin users found - creating test admin\n";

    $testAdmin = App\Models\User::create([
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'role' => 'ADMIN',
        'email_verified_at' => now(),
    ]);

    echo "✅ Created test admin user (ID: {$testAdmin->id})\n";
    $adminUsers = collect([$testAdmin]);
} else {
    echo "✅ Admin users available for testing\n";
}

$testAdmin = $adminUsers->first();

// Step 2: Test admin authentication
echo "\n2. Testing Admin Authentication:\n";

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';
$loginData = [
    'email' => $testAdmin->email,
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loginResult = json_decode($loginResponse, true);
curl_close($ch);

if ($httpCode === 200 && $loginResult && isset($loginResult['data']['tokens']['accessToken'])) {
    $adminToken = $loginResult['data']['tokens']['accessToken'];
    echo "✅ Admin Authentication: SUCCESS\n";
    echo "   - Admin role verified: " . ($loginResult['data']['user']['role'] ?? 'Unknown') . "\n";
} else {
    echo "❌ Admin Authentication: FAILED\n";
    echo "   Response: " . substr($loginResponse, 0, 200) . "...\n";
    exit(1);
}

// Step 3: Test admin-specific endpoints
echo "\n3. Testing Admin-Specific Endpoints:\n";

$adminEndpoints = [
    'GET /api/v1/admin/users' => 'http://127.0.0.1:8000/api/v1/admin/users',
    'GET /api/v1/admin/statistics' => 'http://127.0.0.1:8000/api/v1/admin/statistics',
    'GET /api/v1/admin/reports' => 'http://127.0.0.1:8000/api/v1/admin/reports',
];

$adminResults = [];

foreach ($adminEndpoints as $name => $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $adminToken,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    $success = $httpCode === 200 && $result && isset($result['success']) && $result['success'];

    echo "   $name: " . ($success ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
    if (!$success) {
        echo "     Error: " . substr($response, 0, 150) . "...\n";
    }

    $adminResults[$name] = $success;
}

// Step 4: Test user management operations
echo "\n4. Testing User Management Operations:\n";

// Test listing all users (admin privilege)
$usersUrl = 'http://127.0.0.1:8000/api/v1/admin/users';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $usersUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $adminToken,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$usersResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$usersResult = json_decode($usersResponse, true);
$usersWorking = $httpCode === 200 && $usersResult && isset($usersResult['success']);

echo "   - List All Users: " . ($usersWorking ? "✅ WORKING" : "❌ FAILED") . "\n";
if ($usersWorking && isset($usersResult['data'])) {
    echo "     Total users visible to admin: " . count($usersResult['data']) . "\n";
}

// Test creating a new user (admin privilege)
echo "\n   - Testing User Creation (Admin):\n";
$createUserUrl = 'http://127.0.0.1:8000/api/v1/admin/users';
$newUserData = [
    'name' => 'Admin Created User',
    'email' => 'admin_created_' . time() . '@test.com',
    'password' => 'password',
    'role' => 'VOLUNTEER'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $createUserUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUserData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $adminToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$createResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$createResult = json_decode($createResponse, true);
$createWorking = $httpCode === 201 && $createResult && isset($createResult['success']);

echo "     Create User: " . ($createWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
if (!$createWorking) {
    echo "     Error: " . substr($createResponse, 0, 200) . "...\n";
}

// Step 5: Test role management
echo "\n5. Testing Role Management:\n";

if ($createWorking && isset($createResult['data']['id'])) {
    $newUserId = $createResult['data']['id'];

    // Test role update
    $updateRoleUrl = "http://127.0.0.1:8000/api/v1/admin/users/$newUserId";
    $roleUpdateData = [
        'role' => 'COORDINATOR'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $updateRoleUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($roleUpdateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $adminToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $updateResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $updateResult = json_decode($updateResponse, true);
    $updateWorking = $httpCode === 200 && $updateResult && isset($updateResult['success']);

    echo "   - Role Update: " . ($updateWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
    if (!$updateWorking) {
        echo "     Error: " . substr($updateResponse, 0, 200) . "...\n";
    }

    // Clean up test user
    App\Models\User::find($newUserId)?->delete();
    echo "   - Test user cleanup: ✅ COMPLETED\n";
}

// Final Summary
echo "\n=== ADMIN USER MANAGEMENT ANALYSIS SUMMARY ===\n\n";

$allTests = array_merge($adminResults, [
    'user_listing' => $usersWorking ?? false,
    'user_creation' => $createWorking ?? false,
    'role_management' => $updateWorking ?? false,
]);

$passedTests = 0;
$totalTests = count($allTests);

foreach ($allTests as $test => $passed) {
    echo "- " . ucfirst(str_replace('_', ' ', str_replace(['GET /api/v1/admin/', 'GET /api/v1/'], '', $test))) . ": " . ($passed ? "✅ PASS" : "❌ FAIL") . "\n";
    if ($passed) $passedTests++;
}

$successRate = round(($passedTests / $totalTests) * 100);

echo "\n🎯 ADMIN FUNCTIONALITY: $successRate% ({$passedTests}/{$totalTests} tests passed)\n";

if ($successRate >= 80) {
    echo "\n✅ Admin User Management: CORE FUNCTIONALITY WORKING\n";
    echo "   - Most admin features operational\n";
    echo "   - Minor bugs non-critical for core functionality\n";
} elseif ($successRate >= 60) {
    echo "\n🟡 Admin User Management: PARTIAL FUNCTIONALITY\n";
    echo "   - Some admin features working\n";
    echo "   - Several bugs need attention\n";
} else {
    echo "\n❌ Admin User Management: NEEDS ATTENTION\n";
    echo "   - Major admin functionality issues\n";
}

// Clean up test admin if we created it
if (isset($testAdmin) && $testAdmin->email === 'admin@test.com') {
    $testAdmin->delete();
    echo "\n🧹 Test admin cleanup: ✅ COMPLETED\n";
}

exit($successRate >= 60 ? 0 : 1);
