<?php

echo "=== DIAGNOSTIC: ACTUAL SYSTEM FAILURES ===\n";
echo "Analyzing real problems, not just running tests\n\n";

$baseUrl = 'http://127.0.0.1:8000';
$errors = [];
$results = [];

// Test 1: Authentication and Token Generation
echo "🔑 Testing Authentication System...\n";
$authTest = testAuthentication($baseUrl);
if (!$authTest['success']) {
    $errors[] = "Authentication failure: " . $authTest['error'];
} else {
    $token = $authTest['token'];
    echo "  ✅ Authentication working, token acquired\n";
}

// Test 2: Data Synchronization - Create, Read, Update operations
echo "\n🔄 Testing Data Synchronization...\n";
if (isset($token)) {
    $syncTest = testDataSynchronization($baseUrl, $token);
    if (!$syncTest['success']) {
        $errors[] = "Data sync failure: " . $syncTest['error'];
    } else {
        echo "  ✅ Data synchronization working\n";
    }
} else {
    $errors[] = "Cannot test data sync - authentication failed";
}

// Test 3: File Upload Functionality
echo "\n📁 Testing File Upload System...\n";
if (isset($token)) {
    $fileTest = testFileUpload($baseUrl, $token);
    if (!$fileTest['success']) {
        $errors[] = "File upload failure: " . $fileTest['error'];
    } else {
        echo "  ✅ File upload working\n";
    }
} else {
    $errors[] = "Cannot test file upload - authentication failed";
}

// Test 4: Performance Benchmarking
echo "\n⚡ Testing Performance Benchmarks...\n";
if (isset($token)) {
    $perfTest = testPerformance($baseUrl, $token);
    if ($perfTest['avg_time'] > 200) {
        $errors[] = "Performance failure: {$perfTest['avg_time']}ms average (target <200ms)";
    } else {
        echo "  ✅ Performance benchmarks met: {$perfTest['avg_time']}ms average\n";
    }
} else {
    $errors[] = "Cannot test performance - authentication failed";
}

// Test 5: Database Integrity
echo "\n🗄️ Testing Database Integrity...\n";
$dbTest = testDatabaseIntegrity();
if (!$dbTest['success']) {
    $errors[] = "Database integrity failure: " . $dbTest['error'];
} else {
    echo "  ✅ Database integrity verified\n";
}

// Final Results
echo "\n" . str_repeat("=", 50) . "\n";
echo "DIAGNOSTIC RESULTS:\n";

if (empty($errors)) {
    echo "✅ ALL TESTS PASSED - System is working correctly\n";
} else {
    echo "❌ FOUND " . count($errors) . " CRITICAL ISSUES:\n\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n🔧 THESE ISSUES NEED TO BE FIXED IN THE CODE\n";
}

// ============= TEST FUNCTIONS =============

function testAuthentication($baseUrl)
{
    try {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        // Try to login with existing user or create one
        $response = makeRequest('POST', $baseUrl . '/api/v1/auth/login', $data);

        if (isset($response['access_token'])) {
            return ['success' => true, 'token' => $response['access_token']];
        }

        // If login fails, try registration
        $regData = array_merge($data, [
            'name' => 'Test User',
            'password_confirmation' => 'password123'
        ]);

        $regResponse = makeRequest('POST', $baseUrl . '/api/v1/auth/register', $regData);

        if (isset($regResponse['access_token'])) {
            return ['success' => true, 'token' => $regResponse['access_token']];
        }

        return ['success' => false, 'error' => 'Both login and registration failed'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function testDataSynchronization($baseUrl, $token)
{
    try {
        // Test creating a disaster report
        $reportData = [
            'title' => 'Test Disaster Report',
            'description' => 'Testing data synchronization',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Jakarta',
            'address' => 'Test Address'
        ];

        $createResponse = makeRequest('POST', $baseUrl . '/api/v1/reports', $reportData, $token);

        if (!isset($createResponse['id'])) {
            return ['success' => false, 'error' => 'Failed to create report: ' . json_encode($createResponse)];
        }

        $reportId = $createResponse['id'];

        // Test reading the report
        $readResponse = makeRequest('GET', $baseUrl . '/api/v1/reports/' . $reportId, [], $token);

        if (!isset($readResponse['id'])) {
            return ['success' => false, 'error' => 'Failed to read created report'];
        }

        // Test updating the report
        $updateData = ['title' => 'Updated Test Report'];
        $updateResponse = makeRequest('PUT', $baseUrl . '/api/v1/reports/' . $reportId, $updateData, $token);

        if (!$updateResponse) {
            return ['success' => false, 'error' => 'Failed to update report'];
        }

        return ['success' => true, 'report_id' => $reportId];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function testFileUpload($baseUrl, $token)
{
    try {
        // Create a temporary test file
        $testFile = tempnam(sys_get_temp_dir(), 'test_upload');
        file_put_contents($testFile, 'Test file content for upload verification');

        // Test file upload endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/files/avatar');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'avatar' => new CURLFile($testFile, 'image/jpeg', 'test.jpg')
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        unlink($testFile); // Clean up

        if ($httpCode === 200) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "HTTP $httpCode: $response"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function testPerformance($baseUrl, $token)
{
    $times = [];
    $testEndpoints = [
        '/api/v1/health',
        '/api/v1/auth/me',
        '/api/v1/reports',
        '/api/v1/notifications'
    ];

    foreach ($testEndpoints as $endpoint) {
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            makeRequest('GET', $baseUrl . $endpoint, [], $token);
            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }
    }

    $avgTime = array_sum($times) / count($times);

    return [
        'avg_time' => round($avgTime, 2),
        'min_time' => round(min($times), 2),
        'max_time' => round(max($times), 2)
    ];
}

function testDatabaseIntegrity()
{
    try {
        // Simple connection test (will expand based on actual issues found)
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=astacala_rescue', 'root', '');

        // Test basic table structure
        $tables = ['users', 'disaster_reports', 'notifications'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            if ($stmt === false) {
                return ['success' => false, 'error' => "Cannot access table: $table"];
            }
        }

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function makeRequest($method, $url, $data = [], $token = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Request failed: $url");
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new Exception("HTTP $httpCode: " . ($decoded['message'] ?? $response));
    }

    return $decoded;
}
