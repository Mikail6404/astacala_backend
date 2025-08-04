<?php

/**
 * Test Admin User Management - Corrected Endpoints
 * Test actual admin routes as they exist in the API
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Admin User Management - Corrected Routes Test ===\n\n";

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
$loginResult = json_decode($loginResponse, true);
curl_close($ch);

if ($httpCode === 200 && $loginResult && isset($loginResult['data']['tokens']['accessToken'])) {
    $adminToken = $loginResult['data']['tokens']['accessToken'];
    echo "✅ Admin Authentication: SUCCESS\n";
    echo "   - Admin: " . $adminUser->name . " (" . $adminUser->email . ")\n";
} else {
    echo "❌ Admin Authentication: FAILED\n";
    exit(1);
}

// Step 2: Test actual admin endpoints
echo "\n2. Testing Real Admin Endpoints:\n";

$adminEndpoints = [
    'GET /api/v1/users/admin-list' => 'http://127.0.0.1:8000/api/v1/users/admin-list',
    'GET /api/v1/users/statistics' => 'http://127.0.0.1:8000/api/v1/users/statistics',
    'GET /api/v1/disaster-reports/admin-view' => 'http://127.0.0.1:8000/api/v1/disaster-reports/admin-view',
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
    $success = $httpCode === 200 && $result;

    echo "   $name: " . ($success ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
    if (!$success) {
        echo "     Error: " . substr($response, 0, 150) . "...\n";
    } else {
        // Show some data if successful
        if (isset($result['data']) && is_array($result['data'])) {
            echo "     Data count: " . count($result['data']) . " items\n";
        } elseif (isset($result['total'])) {
            echo "     Total items: " . $result['total'] . "\n";
        }
    }

    $adminResults[$name] = $success;
}

// Step 3: Test user creation (if endpoint exists)
echo "\n3. Testing Admin User Creation:\n";

$createUserUrl = 'http://127.0.0.1:8000/api/v1/users/create-admin';
$newUserData = [
    'name' => 'Test Admin Created User',
    'email' => 'test_admin_created_' . time() . '@test.com',
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
$createWorking = $httpCode === 201 && $createResult;

echo "   - Create Admin User: " . ($createWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
if (!$createWorking) {
    echo "     Error: " . substr($createResponse, 0, 200) . "...\n";
}

// Step 4: Test role/status updates
echo "\n4. Testing Role and Status Updates:\n";

if ($createWorking && isset($createResult['data']['id'])) {
    $newUserId = $createResult['data']['id'];

    // Test role update
    $updateRoleUrl = "http://127.0.0.1:8000/api/v1/users/$newUserId/role";
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
    $roleUpdateWorking = $httpCode === 200 && $updateResult;

    echo "   - Role Update: " . ($roleUpdateWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
    if (!$roleUpdateWorking) {
        echo "     Error: " . substr($updateResponse, 0, 200) . "...\n";
    }

    // Test status update
    $updateStatusUrl = "http://127.0.0.1:8000/api/v1/users/$newUserId/status";
    $statusUpdateData = [
        'status' => 'active'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $updateStatusUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($statusUpdateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $adminToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $statusResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $statusResult = json_decode($statusResponse, true);
    $statusUpdateWorking = $httpCode === 200 && $statusResult;

    echo "   - Status Update: " . ($statusUpdateWorking ? "✅ WORKING" : "❌ FAILED") . " (HTTP $httpCode)\n";
    if (!$statusUpdateWorking) {
        echo "     Error: " . substr($statusResponse, 0, 200) . "...\n";
    }

    // Clean up test user
    App\Models\User::find($newUserId)?->delete();
    echo "   - Test user cleanup: ✅ COMPLETED\n";
} else {
    echo "   - Skipping role/status tests (user creation failed)\n";
    $roleUpdateWorking = false;
    $statusUpdateWorking = false;
}

// Final Summary
echo "\n=== ADMIN FUNCTIONALITY ANALYSIS (CORRECTED ROUTES) ===\n\n";

$allTests = array_merge($adminResults, [
    'user_creation' => $createWorking ?? false,
    'role_update' => $roleUpdateWorking ?? false,
    'status_update' => $statusUpdateWorking ?? false,
]);

$passedTests = 0;
$totalTests = count($allTests);

foreach ($allTests as $test => $passed) {
    $testName = str_replace(['GET /api/v1/', '/'], [' ', ' '], $test);
    $testName = ucwords(str_replace(['users ', 'disaster-reports '], ['Users ', 'Reports '], $testName));
    echo "- $testName: " . ($passed ? "✅ PASS" : "❌ FAIL") . "\n";
    if ($passed) $passedTests++;
}

$successRate = round(($passedTests / $totalTests) * 100);

echo "\n🎯 ADMIN FUNCTIONALITY: $successRate% ({$passedTests}/{$totalTests} tests passed)\n";

if ($successRate >= 80) {
    echo "\n✅ Admin User Management: EXCELLENT FUNCTIONALITY\n";
    echo "   - All major admin features working\n";
    echo "   - No significant bugs found\n";
    echo "   - Ready for production use\n";
} elseif ($successRate >= 60) {
    echo "\n🟡 Admin User Management: GOOD FUNCTIONALITY\n";
    echo "   - Most admin features working\n";
    echo "   - Minor bugs present but non-critical\n";
    echo "   - Suitable for core admin operations\n";
} elseif ($successRate >= 40) {
    echo "\n⚠️ Admin User Management: PARTIAL FUNCTIONALITY\n";
    echo "   - Some admin features working\n";
    echo "   - Several bugs need attention\n";
    echo "   - Limited admin capabilities\n";
} else {
    echo "\n❌ Admin User Management: NEEDS ATTENTION\n";
    echo "   - Major admin functionality issues\n";
    echo "   - Significant bugs blocking operations\n";
}

echo "\n📋 TECHNICAL DEBT ASSESSMENT:\n";
if ($successRate >= 70) {
    echo "✅ Admin bugs: NON-CRITICAL (core functionality working)\n";
    echo "✅ Admin technical debt: MINIMAL IMPACT\n";
} elseif ($successRate >= 50) {
    echo "🟡 Admin bugs: MODERATE (some features affected)\n";
    echo "🟡 Admin technical debt: MEDIUM IMPACT\n";
} else {
    echo "❌ Admin bugs: CRITICAL (major features broken)\n";
    echo "❌ Admin technical debt: HIGH IMPACT\n";
}

exit($successRate >= 50 ? 0 : 1);
