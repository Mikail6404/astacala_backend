<?php

/**
 * Standalone Comprehensive Gap Analysis Test
 * Identifies incomplete functionality to avoid technical debt
 * Tests ALL claimed features using simple HTTP requests
 */

echo "🔍 COMPREHENSIVE GAP ANALYSIS TEST\n";
echo "==================================\n";
echo "Testing ALL claimed functionality to identify gaps...\n\n";

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

    // Default headers
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    // Merge with custom headers
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
    if ($details && !$success) {
        echo "   📝 $details\n";
    }
    $testResults[$testName] = ['success' => $success, 'details' => $details];
}

// 1. AUTHENTICATION SYSTEM TESTS
echo "🔐 AUTHENTICATION SYSTEM TESTS\n";
echo "------------------------------\n";

// Test login
$authResponse = makeHttpRequest("$baseUrl/api/v1/auth/login", 'POST', $testUser);
$token = null;

if ($authResponse['success'] && isset($authResponse['data']['data']['tokens']['accessToken'])) {
    $token = $authResponse['data']['data']['tokens']['accessToken'];
    logTest('User Authentication', true);
    echo "   🔑 Token: " . substr($token, 0, 20) . "...\n";
} else {
    $errorMsg = $authResponse['error'] ?: ($authResponse['data']['message'] ?? 'Unknown error');
    logTest('User Authentication', false, "Login failed: $errorMsg (Status: {$authResponse['status']})");
}

// Test token validation
if ($token) {
    $userResponse = makeHttpRequest("$baseUrl/api/v1/auth/user", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Token Validation',
        $userResponse['success'],
        !$userResponse['success'] ? "Token validation failed (Status: {$userResponse['status']})" : ''
    );

    if ($userResponse['success']) {
        echo "   👤 User: " . ($userResponse['data']['data']['user']['name'] ?? 'Unknown') . "\n";
    }
}

echo "\n";

// 2. DISASTER REPORTING TESTS
echo "📊 DISASTER REPORTING TESTS\n";
echo "---------------------------\n";

if ($token) {
    // Test report creation
    $reportData = [
        'type' => 'earthquake',
        'severity' => 'high',
        'title' => 'Gap Analysis Test Report',
        'description' => 'Testing for completeness',
        'location' => 'Test Location',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'status' => 'pending'
    ];

    $createResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'POST', $reportData, ["Authorization: Bearer $token"]);
    $reportId = null;

    if ($createResponse['success'] && isset($createResponse['data']['data']['report']['id'])) {
        $reportId = $createResponse['data']['data']['report']['id'];
        logTest('Report Creation', true);
        echo "   🆔 Report ID: $reportId\n";
    } else {
        $errorMsg = $createResponse['data']['message'] ?? 'Failed to create report';
        logTest('Report Creation', false, "$errorMsg (Status: {$createResponse['status']})");
    }

    // Test report listing
    $listResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Report Listing',
        $listResponse['success'],
        !$listResponse['success'] ? "List failed (Status: {$listResponse['status']})" : ''
    );

    // Test report details
    if ($reportId) {
        $detailResponse = makeHttpRequest("$baseUrl/api/v1/reports/$reportId", 'GET', [], ["Authorization: Bearer $token"]);
        logTest(
            'Report Details',
            $detailResponse['success'],
            !$detailResponse['success'] ? "Details failed (Status: {$detailResponse['status']})" : ''
        );

        // Test report update
        $updateData = ['status' => 'investigating'];
        $updateResponse = makeHttpRequest("$baseUrl/api/v1/reports/$reportId", 'PUT', $updateData, ["Authorization: Bearer $token"]);
        logTest(
            'Report Updates',
            $updateResponse['success'],
            !$updateResponse['success'] ? "Update failed (Status: {$updateResponse['status']})" : ''
        );
    }
}

echo "\n";

// 3. FILE UPLOAD TESTS
echo "📁 FILE UPLOAD TESTS\n";
echo "-------------------\n";

if ($token && $reportId) {
    // Test image upload endpoint availability
    $uploadResponse = makeHttpRequest("$baseUrl/api/v1/files/disasters/$reportId/images", 'POST', [], ["Authorization: Bearer $token"]);

    // Since we can't easily test actual file upload, we check if endpoint exists with proper validation
    if ($uploadResponse['status'] === 422 || ($uploadResponse['status'] === 400 && strpos($uploadResponse['body'], 'required') !== false)) {
        logTest('Image Upload Endpoint', true, 'Endpoint exists with proper validation');
    } else {
        logTest('Image Upload Endpoint', false, "Endpoint missing or misconfigured (Status: {$uploadResponse['status']})");
    }

    // Test document upload endpoint
    $docUploadResponse = makeHttpRequest("$baseUrl/api/v1/files/disasters/$reportId/documents", 'POST', [], ["Authorization: Bearer $token"]);

    if ($docUploadResponse['status'] === 422 || ($docUploadResponse['status'] === 400 && strpos($docUploadResponse['body'], 'required') !== false)) {
        logTest('Document Upload Endpoint', true, 'Endpoint exists with proper validation');
    } else {
        logTest('Document Upload Endpoint', false, "Endpoint missing or misconfigured (Status: {$docUploadResponse['status']})");
    }

    // Test file listing
    $fileListResponse = makeHttpRequest("$baseUrl/api/v1/files/disasters/$reportId", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'File Listing',
        $fileListResponse['success'],
        !$fileListResponse['success'] ? "File listing failed (Status: {$fileListResponse['status']})" : ''
    );
} else {
    logTest('File Upload Tests', false, 'No report ID available for testing (depends on report creation)');
}

echo "\n";

// 4. USER MANAGEMENT TESTS
echo "👥 USER MANAGEMENT TESTS\n";
echo "-----------------------\n";

if ($token) {
    // Test user profile
    $profileResponse = makeHttpRequest("$baseUrl/api/v1/auth/user", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'User Profile Retrieval',
        $profileResponse['success'],
        !$profileResponse['success'] ? "Profile retrieval failed (Status: {$profileResponse['status']})" : ''
    );

    // Test profile update
    $profileUpdateData = ['name' => 'Updated Test User'];
    $profileUpdateResponse = makeHttpRequest("$baseUrl/api/v1/auth/user", 'PUT', $profileUpdateData, ["Authorization: Bearer $token"]);
    logTest(
        'User Profile Update',
        $profileUpdateResponse['success'],
        !$profileUpdateResponse['success'] ? "Profile update failed (Status: {$profileUpdateResponse['status']})" : ''
    );

    // Test user role management (if endpoint exists)
    $roleResponse = makeHttpRequest("$baseUrl/api/v1/users/roles", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Role Management',
        $roleResponse['success'],
        !$roleResponse['success'] ? "Role management endpoint may not exist (Status: {$roleResponse['status']})" : ''
    );

    // Test user list (admin functionality)
    $usersResponse = makeHttpRequest("$baseUrl/api/v1/users", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'User Management List',
        $usersResponse['success'],
        !$usersResponse['success'] ? "User listing endpoint may not exist (Status: {$usersResponse['status']})" : ''
    );
}

echo "\n";

// 5. NOTIFICATION SYSTEM TESTS
echo "🔔 NOTIFICATION SYSTEM TESTS\n";
echo "---------------------------\n";

if ($token) {
    // Test notification endpoints
    $notificationsResponse = makeHttpRequest("$baseUrl/api/v1/notifications", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Notification Listing',
        $notificationsResponse['success'],
        !$notificationsResponse['success'] ? "Notification system may not be implemented (Status: {$notificationsResponse['status']})" : ''
    );

    // Test notification sending
    $sendNotificationData = [
        'title' => 'Test Notification',
        'message' => 'Testing notification system',
        'type' => 'info'
    ];
    $sendResponse = makeHttpRequest("$baseUrl/api/v1/notifications", 'POST', $sendNotificationData, ["Authorization: Bearer $token"]);
    logTest(
        'Notification Sending',
        $sendResponse['success'],
        !$sendResponse['success'] ? "Notification sending may not be implemented (Status: {$sendResponse['status']})" : ''
    );

    // Test FCM token registration
    $fcmData = ['fcm_token' => 'test_fcm_token_12345'];
    $fcmResponse = makeHttpRequest("$baseUrl/api/v1/auth/fcm-token", 'POST', $fcmData, ["Authorization: Bearer $token"]);
    logTest(
        'FCM Token Registration',
        $fcmResponse['success'],
        !$fcmResponse['success'] ? "FCM integration may not be implemented (Status: {$fcmResponse['status']})" : ''
    );
}

echo "\n";

// 6. GPS AND MAPPING TESTS
echo "🗺️ GPS AND MAPPING TESTS\n";
echo "------------------------\n";

if ($token) {
    // Test location-based report filtering
    $locationQuery = [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'radius' => 10
    ];
    $locationResponse = makeHttpRequest("$baseUrl/api/v1/reports/nearby?" . http_build_query($locationQuery), 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Location-based Filtering',
        $locationResponse['success'],
        !$locationResponse['success'] ? "Location-based filtering may not be implemented (Status: {$locationResponse['status']})" : ''
    );

    // Test GPS data validation
    $gpsData = [
        'type' => 'flood',
        'severity' => 'medium',
        'title' => 'GPS Test Report',
        'description' => 'Testing GPS functionality',
        'location' => 'GPS Test Location',
        'latitude' => 'invalid_lat',  // Intentionally invalid
        'longitude' => 106.8456,
        'status' => 'pending'
    ];
    $gpsValidationResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'POST', $gpsData, ["Authorization: Bearer $token"]);

    // Should fail validation for invalid latitude
    if (!$gpsValidationResponse['success'] && $gpsValidationResponse['status'] === 422) {
        logTest('GPS Data Validation', true, 'Proper validation for invalid coordinates');
    } else {
        logTest('GPS Data Validation', false, 'GPS validation may be incomplete');
    }
}

echo "\n";

// 7. ADMIN DASHBOARD TESTS
echo "⚙️ ADMIN DASHBOARD TESTS\n";
echo "-----------------------\n";

if ($token) {
    // Test statistics endpoint
    $statsResponse = makeHttpRequest("$baseUrl/api/v1/admin/statistics", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Admin Statistics',
        $statsResponse['success'],
        !$statsResponse['success'] ? "Admin statistics endpoint may not exist (Status: {$statsResponse['status']})" : ''
    );

    // Test disaster type management
    $typesResponse = makeHttpRequest("$baseUrl/api/v1/admin/disaster-types", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Disaster Type Management',
        $typesResponse['success'],
        !$typesResponse['success'] ? "Disaster type management may not be implemented (Status: {$typesResponse['status']})" : ''
    );

    // Test system configuration
    $configResponse = makeHttpRequest("$baseUrl/api/v1/admin/config", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'System Configuration',
        $configResponse['success'],
        !$configResponse['success'] ? "System configuration management may not exist (Status: {$configResponse['status']})" : ''
    );
}

echo "\n";

// 8. PERFORMANCE AND RELIABILITY TESTS
echo "⚡ PERFORMANCE TESTS\n";
echo "------------------\n";

if ($token) {
    // Test API response times
    $start = microtime(true);
    $perfResponse = makeHttpRequest("$baseUrl/api/v1/reports", 'GET', [], ["Authorization: Bearer $token"]);
    $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds

    $performanceOk = $responseTime < 500; // Should be under 500ms
    logTest(
        'API Response Time',
        $performanceOk,
        $performanceOk ? "Response time: " . round($responseTime, 2) . "ms" : "Slow response: " . round($responseTime, 2) . "ms"
    );

    // Test pagination
    $paginationResponse = makeHttpRequest("$baseUrl/api/v1/reports?page=1&per_page=5", 'GET', [], ["Authorization: Bearer $token"]);
    logTest(
        'Pagination Support',
        $paginationResponse['success'],
        !$paginationResponse['success'] ? "Pagination failed (Status: {$paginationResponse['status']})" : ''
    );

    // Test data integrity
    if ($reportId) {
        $integrityResponse = makeHttpRequest("$baseUrl/api/v1/reports/$reportId", 'GET', [], ["Authorization: Bearer $token"]);
        $hasRequiredFields = isset($integrityResponse['data']['data']['report']['id']) &&
            isset($integrityResponse['data']['data']['report']['title']) &&
            isset($integrityResponse['data']['data']['report']['created_at']);
        logTest(
            'Data Integrity',
            $hasRequiredFields,
            !$hasRequiredFields ? 'Missing required fields in response' : ''
        );
    }
}

echo "\n";

// COMPREHENSIVE SUMMARY
echo "📋 COMPREHENSIVE GAP ANALYSIS SUMMARY\n";
echo "====================================\n";

$passed = 0;
$failed = 0;
$gaps = [];

foreach ($testResults as $testName => $result) {
    if ($result['success']) {
        $passed++;
    } else {
        $failed++;
        $gaps[] = [
            'test' => $testName,
            'details' => $result['details']
        ];
    }
}

$total = $passed + $failed;
$successRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "🎯 Test Results: $passed passed, $failed failed\n";
echo "📊 Success Rate: $successRate%\n\n";

if (!empty($gaps)) {
    echo "❌ IDENTIFIED GAPS (Need Implementation):\n";
    echo "---------------------------------------\n";
    foreach ($gaps as $gap) {
        echo "• {$gap['test']}\n";
        if ($gap['details']) {
            echo "  └── {$gap['details']}\n";
        }
    }
    echo "\n";

    echo "🔧 TECHNICAL DEBT PREVENTION PLAN:\n";
    echo "---------------------------------\n";
    echo "These gaps must be addressed to avoid technical debt:\n";

    $priorities = [
        'high' => [],
        'medium' => [],
        'low' => []
    ];

    foreach ($gaps as $gap) {
        $testName = $gap['test'];

        // Categorize by priority
        if (
            strpos($testName, 'Authentication') !== false ||
            strpos($testName, 'Report') !== false ||
            strpos($testName, 'User Profile') !== false
        ) {
            $priorities['high'][] = $gap;
        } elseif (
            strpos($testName, 'File') !== false ||
            strpos($testName, 'User Management') !== false ||
            strpos($testName, 'Performance') !== false
        ) {
            $priorities['medium'][] = $gap;
        } else {
            $priorities['low'][] = $gap;
        }
    }

    foreach ($priorities as $priority => $items) {
        if (!empty($items)) {
            echo "\n🔴 " . strtoupper($priority) . " PRIORITY:\n";
            foreach ($items as $item) {
                echo "  • {$item['test']}\n";
            }
        }
    }

    echo "\n🎯 IMMEDIATE ACTION ITEMS:\n";
    echo "1. Implement missing endpoints\n";
    echo "2. Add proper validation where needed\n";
    echo "3. Create missing controllers/services\n";
    echo "4. Update API routes\n";
    echo "5. Re-run this test after each completion\n";
} else {
    echo "🎉 NO GAPS IDENTIFIED! All tested functionality is working.\n";
}

echo "\n📈 NEXT ACTIONS:\n";
echo "1. Address high-priority gaps immediately\n";
echo "2. Plan medium-priority implementations\n";
echo "3. Schedule low-priority enhancements\n";
echo "4. Re-run this test after each completion\n";

echo "\n✅ Gap analysis complete!\n";
