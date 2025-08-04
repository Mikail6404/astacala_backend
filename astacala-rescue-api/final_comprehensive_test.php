<?php

/**
 * Final Comprehensive Test - Post Gap Resolution
 * Testing all fixes implemented to resolve technical debt
 */

echo "🔧 FINAL COMPREHENSIVE TEST - POST GAP RESOLUTION\n";
echo "=================================================\n";
echo "Testing all implemented fixes...\n\n";

$baseUrl = 'http://localhost:8000';
$testResults = [];

// Test user credentials
$testUser = [
    'email' => 'test@astacala.com',
    'password' => 'password123'
];

function makeHttpRequest($url, $method = 'GET', $data = [], $headers = [])
{
    $ch = curl_init();

    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true),
        'error' => $error
    ];
}

function logTest($testName, $success, $details = '')
{
    global $testResults;
    $status = $success ? '✅ PASS' : '❌ FAIL';
    echo "$status $testName\n";
    if ($details) {
        echo "   📝 $details\n";
    }
    $testResults[$testName] = ['success' => $success, 'details' => $details];
}

// 1. AUTHENTICATION TESTS
echo "🔐 AUTHENTICATION SYSTEM TESTS\n";
echo "------------------------------\n";

$authResponse = makeHttpRequest("$baseUrl/api/v1/auth/login", 'POST', $testUser);
$token = null;

if ($authResponse['success'] && isset($authResponse['data']['data']['tokens']['accessToken'])) {
    $token = $authResponse['data']['data']['tokens']['accessToken'];
    logTest('Authentication', true, "Token obtained");
} else {
    logTest('Authentication', false, "Failed to get token");
}

echo "\n";

// 2. CORE FUNCTIONALITY TESTS
echo "📊 CORE FUNCTIONALITY TESTS\n";
echo "---------------------------\n";

if ($token) {
    // Test report creation
    $reportData = [
        'title' => 'Final Test Report',
        'description' => 'Testing post-gap resolution',
        'disasterType' => 'FIRE',
        'severityLevel' => 'MEDIUM',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'locationName' => 'Final Test Location',
        'incidentTimestamp' => '2025-08-03T20:00:00Z',
        'estimatedAffected' => 25
    ];

    $createResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'POST', $reportData, ["Authorization: Bearer $token"]);
    $reportId = null;

    if ($createResponse['success'] && isset($createResponse['data']['data']['reportId'])) {
        $reportId = $createResponse['data']['data']['reportId'];
        logTest('Report Creation', true, "Report ID: $reportId");
    } else {
        logTest('Report Creation', false, "Failed to create report");
    }

    // Test report listing
    $listResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'GET', [], ["Authorization: Bearer $token"]);
    logTest('Report Listing', $listResponse['success']);

    // Test user profile
    $profileResponse = makeHttpRequest("$baseUrl/api/v1/users/profile", 'GET', [], ["Authorization: Bearer $token"]);
    logTest('User Profile', $profileResponse['success']);
}

echo "\n";

// 3. FIXED FUNCTIONALITY TESTS
echo "🔧 PREVIOUSLY FAILING TESTS (NOW FIXED)\n";
echo "---------------------------------------\n";

if ($token) {
    // Test file listing (FIXED - added listFiles method and route)
    if ($reportId) {
        $fileListResponse = makeHttpRequest("$baseUrl/api/v1/files/disasters/$reportId", 'GET', [], ["Authorization: Bearer $token"]);
        logTest(
            'File Listing (FIXED)',
            $fileListResponse['success'],
            $fileListResponse['success'] ? 'File listing endpoint now working' : 'File listing still failing'
        );
    }

    // Test user reports - using working endpoint
    $userReportsResponse = makeHttpRequest("$baseUrl/api/v1/users/reports", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'User Reports (WORKING ENDPOINT)',
        $userReportsResponse['success'],
        $userReportsResponse['success'] ? 'Using /api/v1/users/reports instead of /api/v1/reports/my-reports' : 'User reports failed'
    );

    // Test my-reports endpoint (NEEDS INVESTIGATION)
    $myReportsResponse = makeHttpRequest("$baseUrl/api/v1/reports/my-reports", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'My Reports Endpoint (INVESTIGATE)',
        $myReportsResponse['success'],
        !$myReportsResponse['success'] ? 'Still failing - needs further investigation' : 'Now working'
    );
}

echo "\n";

// 4. REMAINING GAPS TO ADDRESS
echo "⚠️ REMAINING GAPS TO ADDRESS\n";
echo "----------------------------\n";

if ($token) {
    // Test notification system
    $notificationsResponse = makeHttpRequest("$baseUrl/api/v1/notifications", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Notifications',
        $notificationsResponse['success'],
        !$notificationsResponse['success'] ? 'Notification system needs implementation' : 'Working'
    );

    // Test admin features
    $adminStatsResponse = makeHttpRequest("$baseUrl/api/v1/admin/statistics", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Admin Statistics',
        $adminStatsResponse['success'],
        !$adminStatsResponse['success'] ? 'Admin features need implementation' : 'Working'
    );

    // Test location-based filtering
    $locationResponse = makeHttpRequest("$baseUrl/api/v1/reports/nearby?latitude=-6.2088&longitude=106.8456&radius=10", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Location Filtering',
        $locationResponse['success'],
        !$locationResponse['success'] ? 'Location-based filtering needs implementation' : 'Working'
    );
}

echo "\n";

// SUMMARY
echo "📋 FINAL TEST SUMMARY\n";
echo "====================\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $testName => $result) {
    if ($result['success']) {
        $passed++;
    } else {
        $failed++;
    }
}

$total = $passed + $failed;
$successRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "🎯 Test Results: $passed passed, $failed failed\n";
echo "📊 Success Rate: $successRate%\n\n";

echo "🎯 TECHNICAL DEBT STATUS:\n";

if ($successRate >= 90) {
    echo "✅ EXCELLENT: Technical debt significantly reduced!\n";
    echo "   Ready for Phase 4 advanced features.\n";
} elseif ($successRate >= 75) {
    echo "🟡 GOOD: Major progress made, minor gaps remain.\n";
    echo "   Continue addressing remaining issues.\n";
} elseif ($successRate >= 60) {
    echo "🟠 MODERATE: Solid foundation, more work needed.\n";
    echo "   Focus on high-priority gaps.\n";
} else {
    echo "🔴 NEEDS WORK: Significant gaps remain.\n";
    echo "   Continue systematic gap resolution.\n";
}

echo "\n🔄 NEXT ACTIONS:\n";
echo "1. Complete file upload system implementation\n";
echo "2. Investigate my-reports endpoint issue\n";
echo "3. Implement remaining notification features\n";
echo "4. Add admin dashboard functionality\n";
echo "5. Implement location-based filtering\n";

echo "\n✅ Technical debt prevention test complete!\n";
