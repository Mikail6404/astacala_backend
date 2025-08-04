<?php

/**
 * Comprehensive Gap Analysis Test
 * Identifies incomplete functionality to avoid technical debt
 * Tests ALL claimed features to find what needs completion
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

function testApi($endpoint, $method = 'GET', $data = [], $token = null)
{
    global $baseUrl;

    $headers = ['Accept' => 'application/json'];
    if ($token) {
        $headers['Authorization'] = "Bearer $token";
    }

    try {
        $response = Http::withHeaders($headers);

        switch ($method) {
            case 'POST':
                $response = $response->post("$baseUrl$endpoint", $data);
                break;
            case 'GET':
                $response = $response->get("$baseUrl$endpoint");
                break;
            case 'PUT':
                $response = $response->put("$baseUrl$endpoint", $data);
                break;
            case 'DELETE':
                $response = $response->delete("$baseUrl$endpoint");
                break;
        }

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json(),
            'body' => $response->body()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'status' => 0
        ];
    }
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

// 1. AUTHENTICATION TESTS
echo "🔐 AUTHENTICATION SYSTEM TESTS\n";
echo "------------------------------\n";

// Test login
$authResponse = testApi('/api/v1/auth/login', 'POST', $testUser);
$token = null;

if ($authResponse['success'] && isset($authResponse['data']['token'])) {
    $token = $authResponse['data']['token'];
    logTest('User Authentication', true);
} else {
    logTest('User Authentication', false, 'Login failed: ' . ($authResponse['error'] ?? 'Unknown error'));
}

// Test token validation
if ($token) {
    $userResponse = testApi('/api/v1/auth/user', 'GET', [], $token);
    logTest(
        'Token Validation',
        $userResponse['success'],
        !$userResponse['success'] ? 'Token validation failed' : ''
    );
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

    $createResponse = testApi('/api/v1/reports', 'POST', $reportData, $token);
    $reportId = null;

    if ($createResponse['success'] && isset($createResponse['data']['report']['id'])) {
        $reportId = $createResponse['data']['report']['id'];
        logTest('Report Creation', true);
    } else {
        logTest('Report Creation', false, 'Failed to create report');
    }

    // Test report listing
    $listResponse = testApi('/api/v1/reports', 'GET', [], $token);
    logTest('Report Listing', $listResponse['success']);

    // Test report details
    if ($reportId) {
        $detailResponse = testApi("/api/v1/reports/$reportId", 'GET', [], $token);
        logTest('Report Details', $detailResponse['success']);

        // Test report update
        $updateData = ['status' => 'investigating'];
        $updateResponse = testApi("/api/v1/reports/$reportId", 'PUT', $updateData, $token);
        logTest('Report Updates', $updateResponse['success']);
    }
}

echo "\n";

// 3. FILE UPLOAD TESTS
echo "📁 FILE UPLOAD TESTS\n";
echo "-------------------\n";

if ($token && $reportId) {
    // Test image upload endpoint availability
    $uploadResponse = testApi("/api/v1/files/disasters/$reportId/images", 'POST', [], $token);

    // Since we can't easily test actual file upload in this script, 
    // we'll check if the endpoint exists and returns proper validation error
    if ($uploadResponse['status'] === 422) {
        logTest('Image Upload Endpoint', true, 'Endpoint exists with proper validation');
    } else {
        logTest('Image Upload Endpoint', false, 'Endpoint missing or misconfigured');
    }

    // Test document upload endpoint
    $docUploadResponse = testApi("/api/v1/files/disasters/$reportId/documents", 'POST', [], $token);

    if ($docUploadResponse['status'] === 422) {
        logTest('Document Upload Endpoint', true, 'Endpoint exists with proper validation');
    } else {
        logTest('Document Upload Endpoint', false, 'Endpoint missing or misconfigured');
    }

    // Test file listing
    $fileListResponse = testApi("/api/v1/files/disasters/$reportId", 'GET', [], $token);
    logTest('File Listing', $fileListResponse['success']);
} else {
    logTest('File Upload Tests', false, 'No report ID available for testing');
}

echo "\n";

// 4. USER MANAGEMENT TESTS
echo "👥 USER MANAGEMENT TESTS\n";
echo "-----------------------\n";

if ($token) {
    // Test user profile
    $profileResponse = testApi('/api/v1/auth/user', 'GET', [], $token);
    logTest('User Profile Retrieval', $profileResponse['success']);

    // Test profile update
    $profileUpdateData = ['name' => 'Updated Test User'];
    $profileUpdateResponse = testApi('/api/v1/auth/user', 'PUT', $profileUpdateData, $token);
    logTest('User Profile Update', $profileUpdateResponse['success']);

    // Test user role management (if endpoint exists)
    $roleResponse = testApi('/api/v1/users/roles', 'GET', [], $token);
    logTest(
        'Role Management',
        $roleResponse['success'],
        !$roleResponse['success'] ? 'Role management endpoint may not exist' : ''
    );

    // Test user list (admin functionality)
    $usersResponse = testApi('/api/v1/users', 'GET', [], $token);
    logTest(
        'User Management List',
        $usersResponse['success'],
        !$usersResponse['success'] ? 'User listing endpoint may not exist' : ''
    );
}

echo "\n";

// 5. NOTIFICATION SYSTEM TESTS
echo "🔔 NOTIFICATION SYSTEM TESTS\n";
echo "---------------------------\n";

if ($token) {
    // Test notification endpoints
    $notificationsResponse = testApi('/api/v1/notifications', 'GET', [], $token);
    logTest(
        'Notification Listing',
        $notificationsResponse['success'],
        !$notificationsResponse['success'] ? 'Notification system may not be implemented' : ''
    );

    // Test notification sending
    $sendNotificationData = [
        'title' => 'Test Notification',
        'message' => 'Testing notification system',
        'type' => 'info'
    ];
    $sendResponse = testApi('/api/v1/notifications', 'POST', $sendNotificationData, $token);
    logTest(
        'Notification Sending',
        $sendResponse['success'],
        !$sendResponse['success'] ? 'Notification sending may not be implemented' : ''
    );

    // Test FCM token registration
    $fcmData = ['fcm_token' => 'test_fcm_token_12345'];
    $fcmResponse = testApi('/api/v1/auth/fcm-token', 'POST', $fcmData, $token);
    logTest(
        'FCM Token Registration',
        $fcmResponse['success'],
        !$fcmResponse['success'] ? 'FCM integration may not be implemented' : ''
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
    $locationResponse = testApi('/api/v1/reports/nearby?' . http_build_query($locationQuery), 'GET', [], $token);
    logTest(
        'Location-based Filtering',
        $locationResponse['success'],
        !$locationResponse['success'] ? 'Location-based filtering may not be implemented' : ''
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
    $gpsValidationResponse = testApi('/api/v1/reports', 'POST', $gpsData, $token);

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
    $statsResponse = testApi('/api/v1/admin/statistics', 'GET', [], $token);
    logTest(
        'Admin Statistics',
        $statsResponse['success'],
        !$statsResponse['success'] ? 'Admin statistics endpoint may not exist' : ''
    );

    // Test disaster type management
    $typesResponse = testApi('/api/v1/admin/disaster-types', 'GET', [], $token);
    logTest(
        'Disaster Type Management',
        $typesResponse['success'],
        !$typesResponse['success'] ? 'Disaster type management may not be implemented' : ''
    );

    // Test system configuration
    $configResponse = testApi('/api/v1/admin/config', 'GET', [], $token);
    logTest(
        'System Configuration',
        $configResponse['success'],
        !$configResponse['success'] ? 'System configuration management may not exist' : ''
    );
}

echo "\n";

// 8. PERFORMANCE AND RELIABILITY TESTS
echo "⚡ PERFORMANCE TESTS\n";
echo "------------------\n";

if ($token) {
    // Test API response times
    $start = microtime(true);
    $perfResponse = testApi('/api/v1/reports', 'GET', [], $token);
    $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds

    $performanceOk = $responseTime < 500; // Should be under 500ms
    logTest(
        'API Response Time',
        $performanceOk,
        $performanceOk ? "Response time: {$responseTime}ms" : "Slow response: {$responseTime}ms"
    );

    // Test pagination
    $paginationResponse = testApi('/api/v1/reports?page=1&per_page=5', 'GET', [], $token);
    logTest('Pagination Support', $paginationResponse['success']);

    // Test data integrity
    if ($reportId) {
        $integrityResponse = testApi("/api/v1/reports/$reportId", 'GET', [], $token);
        $hasRequiredFields = isset($integrityResponse['data']['report']['id']) &&
            isset($integrityResponse['data']['report']['title']) &&
            isset($integrityResponse['data']['report']['created_at']);
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
            strpos($testName, 'Report') !== false
        ) {
            $priorities['high'][] = $gap;
        } elseif (
            strpos($testName, 'File') !== false ||
            strpos($testName, 'User') !== false
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
} else {
    echo "🎉 NO GAPS IDENTIFIED! All tested functionality is working.\n";
}

echo "\n📈 NEXT ACTIONS:\n";
echo "1. Address high-priority gaps immediately\n";
echo "2. Plan medium-priority implementations\n";
echo "3. Schedule low-priority enhancements\n";
echo "4. Re-run this test after each completion\n";

echo "\n✅ Gap analysis complete!\n";
