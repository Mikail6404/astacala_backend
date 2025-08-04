<?php

/**
 * Phase 3 File Upload Integration Test
 * Testing file upload endpoints with authentication
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 File Upload Integration Test ===\n\n";

// Step 1: Authenticate and get token
echo "1. Authenticating to get Bearer token:\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if (!$testUser) {
    echo "❌ Test user not found!\n";
    exit(1);
}

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

echo "Login response (HTTP $httpCode): $loginResponse\n";

$loginData = json_decode($loginResponse, true);
if (!$loginData || !isset($loginData['data']['tokens']['accessToken'])) {
    echo "❌ Failed to get authentication token\n";
    exit(1);
}

$token = $loginData['data']['tokens']['accessToken'];
echo "✅ Bearer token obtained: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Get or create a disaster report for file upload
echo "2. Getting disaster report for file upload:\n";
$report = App\Models\DisasterReport::where('reported_by', $testUser->id)->first();
if (!$report) {
    echo "No user reports found, creating test report...\n";
    $report = App\Models\DisasterReport::create([
        'title' => 'User File Upload Test',
        'description' => 'Test report for file upload integration',
        'disaster_type' => 'FLOOD',
        'location_name' => 'Test Location',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'severity_level' => 'MEDIUM',
        'status' => 'PENDING',
        'reported_by' => $testUser->id,
        'team_name' => 'Test Team'
    ]);
}

echo "✅ Using report ID: {$report->id} - {$report->title}\n\n";

// Step 3: Create a test image file
echo "3. Creating test image for upload:\n";
$testImagePath = __DIR__ . '/test_upload.jpg';
$imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA9eAA==');
file_put_contents($testImagePath, $imageData);
echo "✅ Test image created: $testImagePath\n\n";

// Step 4: Test file upload endpoint
echo "4. Testing file upload to disaster report:\n";
$uploadUrl = "http://127.0.0.1:8000/api/v1/files/disasters/{$report->id}/images";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $uploadUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

// Prepare file for upload
$cfile = curl_file_create($testImagePath, 'image/jpeg', 'test_upload.jpg');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'images[]' => $cfile,
    'title' => 'Test Image Upload',
    'description' => 'Phase 3 file upload integration test'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$uploadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Upload response (HTTP $httpCode): $uploadResponse\n\n";

// Step 5: Test file retrieval
echo "5. Testing file retrieval:\n";
$retrievalUrl = "http://127.0.0.1:8000/api/v1/files/disasters/{$report->id}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $retrievalUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$retrievalResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Retrieval response (HTTP $httpCode): $retrievalResponse\n\n";

// Clean up test file
unlink($testImagePath);
echo "✅ Test file cleaned up\n";

echo "=== File Upload Integration Test Complete ===\n";
