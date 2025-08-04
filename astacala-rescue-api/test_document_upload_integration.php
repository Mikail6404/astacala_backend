<?php

/**
 * Phase 3 Document Upload Integration Test
 * Testing document upload # Step 4: Test document upload endpoint
echo "4. Testing document upload to disaster report:# Summary
echo "\n📋 TEST SUMMARY:\n";
echo "- Authentication: " . ($loginData && isset($loginData['success']) && $loginData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Document Upload: " . ($uploadData && isset($uploadData['success']) && $uploadData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- File Retrieval: " . ($retrievalData && isset($retrievalData['success']) && $retrievalData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Metadata Storage: " . (isset($metadata['documents']) && count($metadata['documents']) > 0 ? "✅ PASS" : "❌ FAIL") . "\n";

$allPassed = ($loginData && isset($loginData['success']) && $loginData['success']) && 
             ($uploadData && isset($uploadData['success']) && $uploadData['success']) && 
             ($retrievalData && isset($retrievalData['success']) && $retrievalData['success']) && 
             (isset($metadata['documents']) && count($metadata['documents']) > 0);adUrl = "http://127.0.0.1:8000/api/v1/files/disasters/{$report->id}/documents";points without GD dependency
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 Document Upload Integration Test ===\n\n";

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
echo "2. Getting disaster report for document upload:\n";
$report = App\Models\DisasterReport::where('reported_by', $testUser->id)->first();
if (!$report) {
    echo "No user reports found, creating test report...\n";
    $report = App\Models\DisasterReport::create([
        'title' => 'Document Upload Test',
        'description' => 'Test report for document upload integration',
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

// Step 3: Create a test document file
echo "3. Creating test document for upload:\n";
$testDocPath = __DIR__ . '/test_document.txt';
$documentContent = "Phase 3 Document Upload Integration Test\n";
$documentContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
$documentContent .= "Report ID: {$report->id}\n";
$documentContent .= "This is a test document to validate file upload functionality.\n";
$documentContent .= "The document upload system should handle this file without requiring GD extension.\n";
file_put_contents($testDocPath, $documentContent);
echo "✅ Test document created: $testDocPath (" . filesize($testDocPath) . " bytes)\n\n";

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

// Prepare document for upload
$cfile = curl_file_create($testDocPath, 'text/plain', 'test_document.txt');
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'document' => $cfile,
    'platform' => 'mobile',
    'document_type' => 'evidence'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$uploadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Upload response (HTTP $httpCode): $uploadResponse\n";

$uploadData = json_decode($uploadResponse, true);
if ($uploadData && isset($uploadData['success']) && $uploadData['success']) {
    echo "✅ Document upload successful!\n";
    echo "   - Filename: {$uploadData['data']['filename']}\n";
    echo "   - File size: {$uploadData['data']['file_size_human']}\n";
    echo "   - Document type: {$uploadData['data']['document_type']}\n";
    echo "   - Platform: {$uploadData['data']['platform']}\n\n";
} else {
    echo "❌ Document upload failed\n\n";
}

// Step 5: Test file retrieval to verify upload
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

echo "Retrieval response (HTTP $httpCode): $retrievalResponse\n";

$retrievalData = json_decode($retrievalResponse, true);
if ($retrievalData && isset($retrievalData['success']) && $retrievalData['success']) {
    echo "✅ File retrieval successful!\n";
    echo "   - Total documents: {$retrievalData['data']['total_documents']}\n";
    echo "   - Total files: {$retrievalData['data']['total_files']}\n\n";
} else {
    echo "❌ File retrieval failed\n\n";
}

// Step 6: Check report metadata for document
echo "6. Verifying document storage in report metadata:\n";
$report->refresh();
$metadata = $report->metadata ? json_decode($report->metadata, true) : [];
if (isset($metadata['documents']) && count($metadata['documents']) > 0) {
    echo "✅ Document found in report metadata:\n";
    $latestDoc = end($metadata['documents']);
    echo "   - Filename: {$latestDoc['filename']}\n";
    echo "   - File size: {$latestDoc['file_size']} bytes\n";
    echo "   - Type: {$latestDoc['type']}\n";
    echo "   - Platform: {$latestDoc['platform']}\n";
    echo "   - Uploaded by: User {$latestDoc['uploaded_by']}\n";
    echo "   - Upload time: {$latestDoc['uploaded_at']}\n\n";
} else {
    echo "❌ No documents found in report metadata\n\n";
}

// Clean up
echo "7. Cleaning up test files:\n";
if (file_exists($testDocPath)) {
    unlink($testDocPath);
    echo "✅ Test document cleaned up\n";
}

echo "\n=== Document Upload Integration Test Complete ===\n";

// Summary
echo "\n📋 TEST SUMMARY:\n";
echo "- Authentication: " . ($loginData && $loginData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Document Upload: " . ($uploadData && $uploadData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- File Retrieval: " . ($retrievalData && $retrievalData['success'] ? "✅ PASS" : "❌ FAIL") . "\n";
echo "- Metadata Storage: " . (isset($metadata['documents']) && count($metadata['documents']) > 0 ? "✅ PASS" : "❌ FAIL") . "\n";

$allPassed = ($loginData && $loginData['success']) &&
    ($uploadData && $uploadData['success']) &&
    ($retrievalData && $retrievalData['success']) &&
    (isset($metadata['documents']) && count($metadata['documents']) > 0);

echo "\n🎯 OVERALL RESULT: " . ($allPassed ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";

if ($allPassed) {
    echo "\n🚀 Phase 3 Document Upload Integration: VALIDATED AND WORKING!\n";
} else {
    echo "\n⚠️ Phase 3 Document Upload Integration: NEEDS ATTENTION\n";
}

exit($allPassed ? 0 : 1);
