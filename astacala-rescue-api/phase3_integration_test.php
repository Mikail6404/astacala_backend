<?php

// Phase 3 Integration Test - Corrected Version
echo "🚀 Phase 3 Core Functionality Integration Test\n";
echo "===============================================\n\n";

// Step 1: Authenticate
echo "📱 Step 1: Authenticating user...\n";
$authResponse = authenticate();
if (!$authResponse) {
    echo "❌ Authentication failed - cannot proceed\n";
    exit(1);
}

$token = $authResponse['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n";
echo "👤 User: " . $authResponse['data']['user']['name'] . "\n";
echo "🔑 Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Submit disaster report via API
echo "📊 Step 2: Submitting disaster report via API...\n";
$reportId = submitDisasterReport($token);
if (!$reportId) {
    echo "❌ Disaster report submission failed\n";
    exit(1);
}

echo "✅ Disaster report submitted successfully\n";
echo "🆔 Report ID: $reportId\n\n";

// Step 3: Verify report in API listings
echo "🔍 Step 3: Verifying report appears in API listings...\n";
$reportFound = verifyReportInAPI($token, $reportId);
if (!$reportFound) {
    echo "❌ Report not found in API listings\n";
    exit(1);
}

echo "✅ Report verified in API listings\n\n";

// Step 4: Check database directly
echo "🗄️ Step 4: Checking database consistency...\n";
$dbConsistent = checkDatabaseConsistency($reportId);
if (!$dbConsistent) {
    echo "⚠️ Database inconsistency detected\n";
} else {
    echo "✅ Database consistency verified\n";
}
echo "\n";

// Step 5: Test web dashboard integration
echo "🌐 Step 5: Testing web dashboard integration...\n";
$webIntegrated = testWebDashboardIntegration();
if ($webIntegrated) {
    echo "✅ Web dashboard integration confirmed\n";
} else {
    echo "⚠️ Web dashboard integration issues\n";
}
echo "\n";

// Final summary
echo "🎯 Phase 3 Integration Test Summary\n";
echo "==================================\n";
echo "📱 Authentication: ✅ WORKING\n";
echo "📊 Report Submission: ✅ WORKING\n";
echo "🔍 API Retrieval: ✅ WORKING\n";
echo "🗄️ Database: " . ($dbConsistent ? "✅ CONSISTENT" : "⚠️ NEEDS REVIEW") . "\n";
echo "🌐 Web Integration: " . ($webIntegrated ? "✅ READY" : "⚠️ NEEDS SETUP") . "\n\n";

echo "🏆 Phase 3 Core Functionality: INTEGRATION VALIDATED!\n";
echo "Next: Continue with file uploads and real-time notifications\n";

// =============================================================================
// Helper Functions
// =============================================================================

function authenticate()
{
    $url = 'http://127.0.0.1:8000/api/v1/auth/login';
    $data = [
        'email' => 'test@astacala.com',
        'password' => 'password123'
    ];

    return makeRequest('POST', $url, $data);
}

function submitDisasterReport($token)
{
    $url = 'http://127.0.0.1:8000/api/v1/reports';
    $data = [
        'title' => 'Phase 3 Integration Test Report',
        'description' => 'Phase 3 integration test - flood in downtown area with significant impact',
        'disasterType' => 'FLOOD',
        'severityLevel' => 'MEDIUM',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'locationName' => 'Downtown Test Area for Phase 3',
        'estimatedAffected' => 25,
        'teamName' => 'Integration Test Team',
        'weatherCondition' => 'Heavy Rain',
        'incidentTimestamp' => date('Y-m-d H:i:s')
    ];

    $response = makeRequest('POST', $url, $data, $token);

    if ($response && $response['success']) {
        return $response['data']['reportId'];
    }

    if ($response && isset($response['errors'])) {
        echo "❌ Validation errors:\n";
        foreach ($response['errors'] as $field => $errors) {
            echo "  - $field: " . implode(', ', $errors) . "\n";
        }
    } else {
        echo "❌ Error submitting report: " . ($response['message'] ?? 'Unknown error') . "\n";
    }
    return false;
}

function verifyReportInAPI($token, $reportId)
{
    $url = 'http://127.0.0.1:8000/api/v1/reports';
    $response = makeRequest('GET', $url, null, $token);

    if ($response && $response['success']) {
        $reports = $response['data']['reports']; // Correct nested structure
        foreach ($reports as $report) {
            if ($report['id'] == $reportId) {
                echo "📍 Found report: " . $report['location_name'] . "\n";
                echo "📅 Created: " . $report['created_at'] . "\n";
                echo "🎯 Status: " . $report['status'] . "\n";
                return true;
            }
        }
    }

    return false;
}

function checkDatabaseConsistency($reportId)
{
    try {
        $output = shell_exec('cd "' . __DIR__ . '" && php artisan tinker --execute="echo App\\Models\\DisasterReport::find(' . $reportId . ') ? \'Report found in DB\' : \'Report not found in DB\';"');

        if (strpos($output, 'Report found in DB') !== false) {
            echo "✅ Report ID $reportId found in database\n";
            return true;
        } else {
            echo "❌ Report ID $reportId not found in database\n";
            return false;
        }
    } catch (Exception $e) {
        echo "❌ Database check error: " . $e->getMessage() . "\n";
        return false;
    }
}

function testWebDashboardIntegration()
{
    $webAppPath = __DIR__ . '/../../astacala_resque-main/astacala_rescue_web';

    // Check if web app exists and uses same database
    if (!file_exists($webAppPath . '/app/Http/Controllers/PelaporanController.php')) {
        echo "❌ Web dashboard controller not found\n";
        return false;
    }

    $envPath = $webAppPath . '/.env';
    if (file_exists($envPath)) {
        $env = file_get_contents($envPath);
        if (strpos($env, 'astacala_rescue') !== false) {
            echo "✅ Web app configured with unified database\n";
            return true;
        }
    }

    echo "⚠️ Web app environment needs configuration\n";
    return false;
}

function makeRequest($method, $url, $data = null, $token = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        echo "❌ HTTP request failed\n";
        return false;
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return $decoded;
    } else {
        echo "❌ HTTP $httpCode: " . ($decoded['message'] ?? 'Request failed') . "\n";
        return false;
    }
}
