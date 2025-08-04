<?php

/**
 * Debug User Role Access Control Issues
 * Investigating why role-based access is failing
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug User Role Access Control ===\n\n";

// Test basic report creation with detailed error reporting
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if (!$testUser) {
    echo "❌ Test user not found!\n";
    exit(1);
}

echo "1. Testing user: {$testUser->name} (Role: {$testUser->role})\n\n";

// Authenticate
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginResult = json_decode($loginResponse, true);
if (!$loginResult || !isset($loginResult['data']['tokens']['accessToken'])) {
    echo "❌ Authentication failed\n";
    exit(1);
}

$token = $loginResult['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Test report creation with detailed response
echo "2. Testing disaster report creation:\n";
$createReportUrl = 'http://127.0.0.1:8000/api/v1/reports';
$reportData = [
    'title' => 'Debug Test Report',
    'description' => 'Test report for debugging role access',
    'disaster_type' => 'FLOOD',
    'location_name' => 'Test Location',
    'latitude' => -6.2088,
    'longitude' => 106.8456,
    'severity_level' => 'MEDIUM',
    'team_name' => 'Debug Team'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $createReportUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reportData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$createResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $createResponse\n\n";

$createResult = json_decode($createResponse, true);
if ($httpCode === 201 && $createResult && isset($createResult['success']) && $createResult['success']) {
    echo "✅ Report creation successful!\n";
    $reportId = $createResult['data']['id'];
    echo "Created report ID: $reportId\n\n";
} else {
    echo "❌ Report creation failed\n";
    if ($createResult && isset($createResult['errors'])) {
        echo "Validation errors:\n";
        print_r($createResult['errors']);
    }
    echo "\n";
}

// Test admin user creation and access
echo "3. Testing admin user role:\n";
$adminUser = App\Models\User::where('email', 'admin@example.com')->first();
if (!$adminUser) {
    echo "Creating admin user...\n";
    $adminUser = App\Models\User::create([
        'name' => 'Debug Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin', // Note: lowercase to match middleware expectation
        'email_verified_at' => now()
    ]);
    echo "✅ Admin user created with role: {$adminUser->role}\n";
} else {
    echo "✅ Using existing admin user with role: {$adminUser->role}\n";
}

// Authenticate admin
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $adminUser->email,
    'password' => 'password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$adminLoginResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$adminLoginResult = json_decode($adminLoginResponse, true);
if (!$adminLoginResult || !isset($adminLoginResult['data']['tokens']['accessToken'])) {
    echo "❌ Admin authentication failed\n";
    exit(1);
}

$adminToken = $adminLoginResult['data']['tokens']['accessToken'];
echo "✅ Admin authentication successful\n\n";

// Test admin access
echo "4. Testing admin access to user management:\n";
$userManagementUrl = 'http://127.0.0.1:8000/api/v1/users/admin-list';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userManagementUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $adminToken,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$adminResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $adminResponse\n\n";

$adminResult = json_decode($adminResponse, true);
if ($httpCode === 200 && $adminResult && isset($adminResult['success']) && $adminResult['success']) {
    echo "✅ Admin access working!\n";
} else {
    echo "❌ Admin access failed\n";
}

// Check middleware registration
echo "5. Checking middleware configuration:\n";
$middlewareGroups = app('router')->getMiddlewareGroups();
$middleware = app('router')->getMiddleware();

echo "Available middleware:\n";
foreach ($middleware as $name => $class) {
    echo "  - $name: $class\n";
}

if (isset($middleware['role'])) {
    echo "✅ Role middleware is registered\n";
} else {
    echo "❌ Role middleware not found - this may be the issue!\n";
}

echo "\n=== Debug Complete ===\n";
