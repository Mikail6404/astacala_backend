<?php

/**
 * Phase 3 End-to-End Integration Test
 * Complete cross-platform integration validation
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 End-to-End Integration Test ===\n\n";

$testResults = [];
$startTime = microtime(true);

// Step 1: Complete User Authentication Flow
echo "🔐 STEP 1: Authentication Integration Test\n";
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginData = json_decode($loginResponse, true);
$token = $loginData['data']['tokens']['accessToken'];

if ($httpCode === 200 && $token) {
    echo "✅ Authentication: PASS\n";
    $testResults['authentication'] = true;
} else {
    echo "❌ Authentication: FAIL\n";
    $testResults['authentication'] = false;
}

// Step 2: User Profile Management Integration
echo "\n👤 STEP 2: User Management Integration Test\n";
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

if ($httpCode === 200) {
    echo "✅ User Profile: PASS\n";
    $testResults['user_management'] = true;
} else {
    echo "❌ User Profile: FAIL\n";
    $testResults['user_management'] = false;
}

// Step 3: GPS Report Creation Integration
echo "\n🗺️ STEP 3: GPS Report Creation Integration Test\n";
$createReportUrl = 'http://127.0.0.1:8000/api/v1/reports';

$reportData = [
    'title' => 'E2E Integration Test Report',
    'description' => 'End-to-end integration test for Phase 3',
    'disasterType' => 'FLOOD',
    'severityLevel' => 'HIGH',
    'incidentTimestamp' => date('Y-m-d H:i:s'),
    'latitude' => -6.2088,
    'longitude' => 106.8456,
    'location_name' => 'E2E Test Location',
    'address' => 'Integration Test Address',
    'team_name' => 'E2E Test Team'
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

$responseData = json_decode($createResponse, true);
$reportId = null;

if ($httpCode === 201 && isset($responseData['data']['reportId'])) {
    $reportId = $responseData['data']['reportId'];
    echo "✅ GPS Report Creation: PASS (Report ID: $reportId)\n";
    $testResults['gps_report'] = true;
} else {
    echo "❌ GPS Report Creation: FAIL\n";
    $testResults['gps_report'] = false;
}

// Step 4: File Upload Integration (Test the infrastructure)
echo "\n📁 STEP 4: File Upload Infrastructure Test\n";
if ($reportId) {
    $fileRetrievalUrl = "http://127.0.0.1:8000/api/v1/files/disasters/$reportId";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileRetrievalUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $fileResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "✅ File Upload Infrastructure: PASS\n";
        $testResults['file_upload'] = true;
    } else {
        echo "❌ File Upload Infrastructure: FAIL\n";
        $testResults['file_upload'] = false;
    }
} else {
    echo "⚠️ File Upload Infrastructure: SKIP (no report created)\n";
    $testResults['file_upload'] = false;
}

// Step 5: Notification System Integration
echo "\n🔔 STEP 5: Notification System Integration Test\n";
$notificationsUrl = 'http://127.0.0.1:8000/api/v1/notifications';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $notificationsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$notificationsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Notification System: PASS\n";
    $testResults['notifications'] = true;
} else {
    echo "❌ Notification System: FAIL\n";
    $testResults['notifications'] = false;
}

// Step 6: Database Consistency Validation
echo "\n🗄️ STEP 6: Database Consistency Integration Test\n";
try {
    $userCount = App\Models\User::count();
    $reportCount = App\Models\DisasterReport::count();
    $notificationCount = App\Models\Notification::count();

    if ($userCount > 0 && $reportCount > 0) {
        echo "✅ Database Consistency: PASS\n";
        echo "   Users: $userCount, Reports: $reportCount, Notifications: $notificationCount\n";
        $testResults['database'] = true;
    } else {
        echo "❌ Database Consistency: FAIL\n";
        $testResults['database'] = false;
    }
} catch (Exception $e) {
    echo "❌ Database Consistency: FAIL (Exception: {$e->getMessage()})\n";
    $testResults['database'] = false;
}

// Step 7: Cross-Platform Data Flow Validation
echo "\n🔄 STEP 7: Cross-Platform Data Flow Test\n";
if ($reportId) {
    $report = App\Models\DisasterReport::find($reportId);
    if ($report && $report->latitude && $report->longitude) {
        echo "✅ Cross-Platform Data Flow: PASS\n";
        echo "   Report exists in database with GPS coordinates\n";
        $testResults['cross_platform'] = true;
    } else {
        echo "❌ Cross-Platform Data Flow: FAIL\n";
        $testResults['cross_platform'] = false;
    }
} else {
    echo "⚠️ Cross-Platform Data Flow: SKIP (no report created)\n";
    $testResults['cross_platform'] = false;
}

// Final Results Summary
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏆 PHASE 3 END-TO-END INTEGRATION TEST RESULTS\n";
echo str_repeat("=", 60) . "\n";

$passCount = array_sum($testResults);
$totalTests = count($testResults);
$passRate = round(($passCount / $totalTests) * 100, 1);

echo "Test Duration: {$duration} seconds\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passCount\n";
echo "Failed: " . ($totalTests - $passCount) . "\n";
echo "Success Rate: {$passRate}%\n\n";

echo "Test Results:\n";
foreach ($testResults as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "  " . ucfirst(str_replace('_', ' ', $test)) . ": $status\n";
}

echo "\n";
if ($passRate >= 85) {
    echo "🎉 PHASE 3 INTEGRATION: SUCCESS!\n";
    echo "   Cross-platform integration is working correctly.\n";
} elseif ($passRate >= 70) {
    echo "⚠️ PHASE 3 INTEGRATION: PARTIAL SUCCESS\n";
    echo "   Most components working, some issues need attention.\n";
} else {
    echo "❌ PHASE 3 INTEGRATION: NEEDS WORK\n";
    echo "   Significant issues detected, review required.\n";
}

echo "\n=== End-to-End Integration Test Complete ===\n";
