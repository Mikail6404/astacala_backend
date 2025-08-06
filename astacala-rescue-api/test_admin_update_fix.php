<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing TICKET #005 Fix: Admin User Update by ID ===\n\n";

// Step 1: Get admin user and authenticate
echo "1. Testing Admin Authentication:\n";

$adminUser = App\Models\User::where('role', 'ADMIN')->first();
if (!$adminUser) {
    echo "❌ No admin user found\n";
    exit(1);
}

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';
$loginData = [
    'email' => $adminUser->email,
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
curl_close($ch);

$loginResult = json_decode($loginResponse, true);
if (!$loginResult || !isset($loginResult['data']['tokens']['accessToken'])) {
    echo "❌ Admin authentication failed (HTTP $httpCode)\n";
    echo "Response: $loginResponse\n";
    exit(1);
}

$adminToken = $loginResult['data']['tokens']['accessToken'];
echo "✅ Admin authentication successful\n\n";

// Step 2: Create a test user to update
echo "2. Creating a test user to update:\n";

$createUserUrl = 'http://127.0.0.1:8000/api/v1/users/create-admin';
$newUserData = [
    'name' => 'Test Update User',
    'email' => 'test_update_' . time() . '@test.com',
    'password' => 'password',
    'phone' => '081234567890',
    'organization' => 'Test Organization'
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
if (!$createResult || !isset($createResult['data']['id'])) {
    echo "❌ User creation failed (HTTP $httpCode)\n";
    echo "Response: $createResponse\n";
    exit(1);
}

$testUserId = $createResult['data']['id'];
echo "✅ Test user created with ID: $testUserId\n\n";

// Step 3: Test the NEW admin update endpoint
echo "3. Testing NEW admin user update endpoint:\n";

$updateUrl = "http://127.0.0.1:8000/api/v1/users/$testUserId";
$updateData = [
    'name' => 'Updated Test User Name',
    'phone' => '087654321098',
    'organization' => 'Updated Test Organization',
    'address' => 'Updated Test Address',
    'birth_date' => '1990-05-15',
    'place_of_birth' => 'Jakarta',
    'member_number' => 'UPD001'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $updateUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
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
$updateWorking = $httpCode === 200 && $updateResult && isset($updateResult['success']) && $updateResult['success'];

echo "   - Update User by ID: " . ($updateWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";

if ($updateWorking) {
    echo "   - Updated user data:\n";
    $userData = $updateResult['data'];
    echo "     * Name: {$userData['name']}\n";
    echo "     * Phone: {$userData['phone']}\n";
    echo "     * Organization: {$userData['organization']}\n";
    echo "     * Address: {$userData['address']}\n";
    echo "     * Birth Date: {$userData['birth_date']}\n";
    echo "     * Place of Birth: {$userData['place_of_birth']}\n";
    echo "     * Member Number: {$userData['member_number']}\n";
} else {
    echo "   - Error: " . substr($updateResponse, 0, 300) . "...\n";
}

// Step 4: Test user deactivation (delete)
echo "\n4. Testing user deactivation (delete):\n";

$deactivateUrl = "http://127.0.0.1:8000/api/v1/users/$testUserId/status";
$deactivateData = [
    'is_active' => false
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $deactivateUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($deactivateData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $adminToken,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$deactivateResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$deactivateResult = json_decode($deactivateResponse, true);
$deactivateWorking = $httpCode === 200 && $deactivateResult && isset($deactivateResult['success']) && $deactivateResult['success'];

echo "   - Deactivate User: " . ($deactivateWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";

if (!$deactivateWorking) {
    echo "   - Error: " . substr($deactivateResponse, 0, 200) . "...\n";
}

echo "\n=== SUMMARY ===\n";
echo "✅ Admin Authentication: WORKING\n";
echo "✅ User Creation: WORKING\n";
echo ($updateWorking ? "✅" : "❌") . " User Update by ID: " . ($updateWorking ? "WORKING" : "FAILED") . "\n";
echo ($deactivateWorking ? "✅" : "❌") . " User Deactivation: " . ($deactivateWorking ? "WORKING" : "FAILED") . "\n";

if ($updateWorking && $deactivateWorking) {
    echo "\n🎉 TICKET #005 Backend Fix: ALL TESTS PASSED!\n";
    echo "The new admin user update endpoint is working correctly.\n";
} else {
    echo "\n❌ Some tests failed. Please check the error messages above.\n";
}
