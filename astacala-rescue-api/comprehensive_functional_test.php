<?php

echo "=== FIXED AUTHENTICATION TEST ===\n";

$baseUrl = 'http://127.0.0.1:8000';

// Test with actual user from database
$credentials = [
    'email' => 'volunteer@mobile.test',
    'password' => 'password123',
];

echo "🔑 Testing login with existing user: {$credentials['email']}\n";

try {
    $response = makeRequest('POST', $baseUrl.'/api/v1/auth/login', $credentials);

    // FIX: Correct token extraction from response structure
    $token = null;
    if (isset($response['data']['tokens']['accessToken'])) {
        $token = $response['data']['tokens']['accessToken'];
        echo '✅ Login successful! Token: '.substr($token, 0, 20)."...\n\n";
    } elseif (isset($response['access_token'])) {
        $token = $response['access_token'];
        echo '✅ Login successful! Token: '.substr($token, 0, 20)."...\n\n";
    } else {
        echo '❌ Login failed - no token in response: '.json_encode($response)."\n";

        return;
    }

    // Now test all functionality with working auth
    $results = testAllFunctionality($baseUrl, $token);

    // Final assessment
    echo "\n".str_repeat('=', 60)."\n";
    echo "FINAL RESULTS SUMMARY:\n";
    echo str_repeat('=', 60)."\n";

    $passCount = 0;
    $totalTests = count($results);

    foreach ($results as $test => $passed) {
        $status = $passed ? '✅ PASS' : '❌ FAIL';
        echo "$status $test\n";
        if ($passed) {
            $passCount++;
        }
    }

    $successRate = round(($passCount / $totalTests) * 100, 1);
    echo "\nSUCCESS RATE: $passCount/$totalTests ($successRate%)\n";

    if ($successRate >= 80) {
        echo "🎉 SYSTEM STATUS: GOOD - Meeting academic standards\n";
    } elseif ($successRate >= 60) {
        echo "⚠️ SYSTEM STATUS: ACCEPTABLE - Needs minor improvements\n";
    } else {
        echo "❌ SYSTEM STATUS: POOR - Requires significant fixes\n";
    }
} catch (Exception $e) {
    echo '❌ Login error: '.$e->getMessage()."\n";
}

function testAllFunctionality($baseUrl, $token)
{
    $results = [];

    // Test 1: Authentication
    $results['Authentication'] = true; // Already passed if we got here

    // Test 2: Data Synchronization - Create, Read, Update
    echo "🔄 Testing Data Synchronization...\n";
    try {
        $reportData = [
            'title' => 'Comprehensive Test Report',
            'description' => 'Testing complete CRUD operations',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'location_name' => 'Jakarta Test',
            'address' => 'Test Address 123',
        ];

        // CREATE
        $createResponse = makeRequest('POST', $baseUrl.'/api/v1/reports', $reportData, $token);
        $reportId = $createResponse['id'] ?? $createResponse['data']['id'] ?? null;

        if (! $reportId) {
            throw new Exception('Failed to create report: '.json_encode($createResponse));
        }

        echo "  ✅ CREATE: Report ID $reportId created\n";

        // READ
        $readResponse = makeRequest('GET', $baseUrl.'/api/v1/reports/'.$reportId, [], $token);
        $readId = $readResponse['id'] ?? $readResponse['data']['id'] ?? null;

        if ($readId != $reportId) {
            throw new Exception('Failed to read report back');
        }

        echo "  ✅ READ: Report successfully retrieved\n";

        // UPDATE
        $updateData = ['title' => 'Updated Comprehensive Test Report'];
        $updateResponse = makeRequest('PUT', $baseUrl.'/api/v1/reports/'.$reportId, $updateData, $token);

        echo "  ✅ UPDATE: Report successfully updated\n";

        $results['Data Synchronization'] = true;
    } catch (Exception $e) {
        echo '  ❌ Data sync failed: '.$e->getMessage()."\n";
        $results['Data Synchronization'] = false;
    }

    // Test 3: Performance Benchmarks
    echo "\n⚡ Testing Performance...\n";
    try {
        $times = [];
        $testEndpoints = [
            '/api/v1/health',
            '/api/v1/auth/me',
            '/api/v1/reports',
            '/api/v1/notifications',
        ];

        foreach ($testEndpoints as $endpoint) {
            for ($i = 0; $i < 3; $i++) {
                $start = microtime(true);
                makeRequest('GET', $baseUrl.$endpoint, [], $token);
                $end = microtime(true);
                $times[] = ($end - $start) * 1000;
            }
        }

        $avgTime = array_sum($times) / count($times);
        echo '  📊 Average response time: '.round($avgTime, 2)."ms\n";

        $results['Performance <200ms'] = $avgTime < 200;
        if ($avgTime < 200) {
            echo "  ✅ Performance target met\n";
        } else {
            echo "  ❌ Performance target not met (target: <200ms)\n";
        }
    } catch (Exception $e) {
        echo '  ❌ Performance test failed: '.$e->getMessage()."\n";
        $results['Performance <200ms'] = false;
    }

    // Test 4: File Upload
    echo "\n📁 Testing File Upload...\n";
    try {
        $testFile = tempnam(sys_get_temp_dir(), 'test_upload');
        file_put_contents($testFile, 'Test file content for upload verification');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl.'/api/v1/files/avatar');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'avatar' => new CURLFile($testFile, 'image/jpeg', 'test.jpg'),
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        unlink($testFile);

        $results['File Upload'] = $httpCode === 200;
        if ($httpCode === 200) {
            echo "  ✅ File upload successful\n";
        } else {
            echo "  ❌ File upload failed: HTTP $httpCode\n";
            echo "  Response: $response\n";
        }
    } catch (Exception $e) {
        echo '  ❌ File upload error: '.$e->getMessage()."\n";
        $results['File Upload'] = false;
    }

    // Test 5: Real-time Features
    echo "\n⚡ Testing Real-time Features...\n";
    try {
        // Test notification endpoints
        $notifResponse = makeRequest('GET', $baseUrl.'/api/v1/notifications', [], $token);

        // Test forum endpoints
        $forumResponse = makeRequest('GET', $baseUrl.'/api/v1/forum', [], $token);

        echo "  ✅ Real-time endpoints accessible\n";
        $results['Real-time Features'] = true;
    } catch (Exception $e) {
        echo '  ❌ Real-time features failed: '.$e->getMessage()."\n";
        $results['Real-time Features'] = false;
    }

    // Test 6: Security Standards
    echo "\n🔒 Testing Security Standards...\n";
    try {
        // Test protected endpoint without token
        try {
            makeRequest('GET', $baseUrl.'/api/v1/reports', [], null);
            echo "  ❌ Security failure: Protected endpoint accessible without token\n";
            $results['Security Standards'] = false;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
                echo "  ✅ Protected endpoints properly secured\n";
                $results['Security Standards'] = true;
            } else {
                throw $e;
            }
        }
    } catch (Exception $e) {
        echo '  ❌ Security test failed: '.$e->getMessage()."\n";
        $results['Security Standards'] = false;
    }

    return $results;
}

function makeRequest($method, $url, $data = [], $token = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if ($method === 'POST' || $method === 'PUT') {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Request failed: $url");
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new Exception("HTTP $httpCode: ".($decoded['message'] ?? $response));
    }

    return $decoded;
}
