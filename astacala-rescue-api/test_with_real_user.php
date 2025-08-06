<?php

echo "=== TESTING WITH REAL USER CREDENTIALS ===\n";

$baseUrl = 'http://127.0.0.1:8000';

// Test with actual user from database
$credentials = [
    'email' => 'volunteer@mobile.test',
    'password' => 'password123'
];

echo "🔑 Testing login with existing user: {$credentials['email']}\n";

try {
    $response = makeRequest('POST', $baseUrl . '/api/v1/auth/login', $credentials);

    if (isset($response['access_token'])) {
        $token = $response['access_token'];
        echo "✅ Login successful! Token: " . substr($token, 0, 20) . "...\n\n";

        // Now test all the other functionality with working auth
        testWithValidToken($baseUrl, $token);
    } else {
        echo "❌ Login failed: " . json_encode($response) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Login error: " . $e->getMessage() . "\n";
}

function testWithValidToken($baseUrl, $token)
{
    echo "🔄 Testing Data Synchronization with valid token...\n";

    // Test creating a disaster report
    $reportData = [
        'title' => 'Real Test Disaster Report',
        'description' => 'Testing data synchronization with valid authentication',
        'disaster_type' => 'flood',
        'severity_level' => 'medium',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'location_name' => 'Jakarta',
        'address' => 'Test Address 123'
    ];

    try {
        $createResponse = makeRequest('POST', $baseUrl . '/api/v1/reports', $reportData, $token);

        if (isset($createResponse['id'])) {
            echo "  ✅ Created disaster report ID: {$createResponse['id']}\n";

            // Test reading the report
            $readResponse = makeRequest('GET', $baseUrl . '/api/v1/reports/' . $createResponse['id'], [], $token);

            if (isset($readResponse['id'])) {
                echo "  ✅ Successfully read back created report\n";

                // Test updating the report
                $updateData = ['title' => 'Updated Real Test Report'];
                $updateResponse = makeRequest('PUT', $baseUrl . '/api/v1/reports/' . $createResponse['id'], $updateData, $token);

                if ($updateResponse) {
                    echo "  ✅ Successfully updated report\n";
                } else {
                    echo "  ❌ Failed to update report\n";
                }
            } else {
                echo "  ❌ Failed to read created report\n";
            }
        } else {
            echo "  ❌ Failed to create report: " . json_encode($createResponse) . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Data sync error: " . $e->getMessage() . "\n";
    }

    // Test performance
    echo "\n⚡ Testing Performance...\n";
    $times = [];
    $testEndpoints = [
        '/api/v1/health',
        '/api/v1/auth/me',
        '/api/v1/reports'
    ];

    foreach ($testEndpoints as $endpoint) {
        $start = microtime(true);
        try {
            makeRequest('GET', $baseUrl . $endpoint, [], $token);
            $end = microtime(true);
            $times[] = ($end - $start) * 1000;
        } catch (Exception $e) {
            echo "  ❌ Error testing $endpoint: " . $e->getMessage() . "\n";
        }
    }

    if (!empty($times)) {
        $avgTime = array_sum($times) / count($times);
        echo "  📊 Average response time: " . round($avgTime, 2) . "ms\n";

        if ($avgTime < 200) {
            echo "  ✅ Performance target met (<200ms)\n";
        } else {
            echo "  ❌ Performance target NOT met (>200ms)\n";
        }
    }

    // Test file upload
    echo "\n📁 Testing File Upload...\n";
    try {
        $testFile = tempnam(sys_get_temp_dir(), 'test_upload');
        file_put_contents($testFile, 'Test file content for upload verification');

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

        unlink($testFile);

        if ($httpCode === 200) {
            echo "  ✅ File upload successful\n";
        } else {
            echo "  ❌ File upload failed: HTTP $httpCode - $response\n";
        }
    } catch (Exception $e) {
        echo "  ❌ File upload error: " . $e->getMessage() . "\n";
    }
}

function makeRequest($method, $url, $data = [], $token = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
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
        throw new Exception("HTTP $httpCode: " . ($decoded['message'] ?? $response));
    }

    return $decoded;
}
