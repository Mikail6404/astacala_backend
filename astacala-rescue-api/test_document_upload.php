<?php

/**
 * Phase 3 Document Upload Test (bypasses GD extension requirement)
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 Document Upload Test ===\n\n";

// Step 1: Authenticate and get token
echo "1. Authenticating to get Bearer token:\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
$token = null;

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
echo "✅ Bearer token obtained\n\n";

// Step 2: Get user's report
$report = App\Models\DisasterReport::where('reported_by', $testUser->id)->first();
echo "✅ Using report ID: {$report->id} - {$report->title}\n\n";

// Step 3: Create a test document file
echo "3. Creating test document for upload:\n";
$testDocPath = __DIR__ . '/test_document.txt';
file_put_contents($testDocPath, "This is a test document for Phase 3 file upload integration.\nCreated at: " . date('Y-m-d H:i:s'));
echo "✅ Test document created: $testDocPath\n\n";

// Step 4: Test document upload endpoint
echo "4. Testing document upload to disaster report:\n";
$uploadUrl = "http://127.0.0.1:8000/api/v1/files/disasters/{$report->id}/documents";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $uploadUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

// Prepare file for upload
$cfile = curl_file_create($testDocPath, 'text/plain', 'test_document.txt');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'document' => $cfile,
    'title' => 'Test Document Upload',
    'description' => 'Phase 3 document upload integration test'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$uploadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Upload response (HTTP $httpCode): $uploadResponse\n\n";

// Step 5: Test file retrieval again
echo "5. Testing file retrieval after upload:\n";
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
unlink($testDocPath);
echo "✅ Test document cleaned up\n";

echo "=== Document Upload Test Complete ===\n";
