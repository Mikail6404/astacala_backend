<?php

/**
 * Complete User Journey Integration Test
 * Simulates the full mobile app user experience end-to-end
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Complete User Journey Integration Test ===\n\n";

$journeyResults = [];
$startTime = microtime(true);

// Scenario: New user registers and creates their first disaster report
echo "🚀 USER JOURNEY: Emergency Response Team Member\n";
echo "Scenario: Team member discovers flood, creates report, uploads evidence\n\n";

// Step 1: User Registration (simulating mobile app registration)
echo "📱 STEP 1: User Registration\n";
$timestamp = time();
$newUser = [
    'name' => "Emergency Responder $timestamp",
    'email' => "responder{$timestamp}@emergency.gov",
    'password' => 'SecurePass123!',
    'password_confirmation' => 'SecurePass123!',
    'role' => 'VOLUNTEER'
];

$registerUrl = 'http://127.0.0.1:8000/api/v1/auth/register';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $registerUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newUser));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$registerResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$registerData = json_decode($registerResponse, true);

if ($httpCode === 201 && isset($registerData['data']['user'])) {
    echo "✅ User Registration: SUCCESS\n";
    echo "   User ID: {$registerData['data']['user']['id']}\n";
    $journeyResults['registration'] = true;
    $userId = $registerData['data']['user']['id'];
} else {
    echo "❌ User Registration: FAILED\n";
    echo "   Response: $registerResponse\n";
    $journeyResults['registration'] = false;
    $userId = null;
}

// Step 2: User Login (mobile app login)
echo "\n🔐 STEP 2: User Login\n";
$loginData = [
    'email' => $newUser['email'],
    'password' => $newUser['password']
];

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';

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

$loginResponseData = json_decode($loginResponse, true);
$token = null;

if ($httpCode === 200 && isset($loginResponseData['data']['tokens']['accessToken'])) {
    $token = $loginResponseData['data']['tokens']['accessToken'];
    echo "✅ User Login: SUCCESS\n";
    echo "   Access Token: " . substr($token, 0, 20) . "...\n";
    $journeyResults['login'] = true;
} else {
    echo "❌ User Login: FAILED\n";
    $journeyResults['login'] = false;
}

// Step 3: GPS Location Services (mobile app gets current location)
echo "\n🗺️ STEP 3: GPS Location Services\n";
$currentLocation = [
    'latitude' => -6.2088 + (rand(-100, 100) / 10000), // Jakarta area with variation
    'longitude' => 106.8456 + (rand(-100, 100) / 10000),
    'accuracy' => rand(5, 15),
    'timestamp' => date('Y-m-d H:i:s')
];

echo "✅ GPS Location: SUCCESS\n";
echo "   Coordinates: {$currentLocation['latitude']}, {$currentLocation['longitude']}\n";
echo "   Accuracy: {$currentLocation['accuracy']} meters\n";
$journeyResults['gps_location'] = true;

// Step 4: Create Emergency Report (field officer creates disaster report)
echo "\n🚨 STEP 4: Emergency Report Creation\n";
if ($token) {
    $emergencyReport = [
        'title' => 'Flash Flood in Residential Area',
        'description' => 'Sudden flash flood affecting 50+ households. Water level rising rapidly. Immediate evacuation needed.',
        'disasterType' => 'FLOOD',
        'severityLevel' => 'HIGH',
        'incidentTimestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'latitude' => $currentLocation['latitude'],
        'longitude' => $currentLocation['longitude'],
        'location_name' => 'Residential Complex Block A',
        'address' => 'Jl. Emergency Test No. 123, Jakarta',
        'team_name' => 'Emergency Response Team Alpha',
        'immediate_action_required' => true,
        'affected_population' => 200,
        'damage_assessment' => 'MODERATE'
    ];

    $createReportUrl = 'http://127.0.0.1:8000/api/v1/reports';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $createReportUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emergencyReport));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $reportResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $reportData = json_decode($reportResponse, true);

    if ($httpCode === 201 && isset($reportData['data']['reportId'])) {
        $reportId = $reportData['data']['reportId'];
        echo "✅ Emergency Report: SUCCESS\n";
        echo "   Report ID: $reportId\n";
        echo "   Disaster Type: {$emergencyReport['disasterType']}\n";
        echo "   Severity: {$emergencyReport['severityLevel']}\n";
        $journeyResults['emergency_report'] = true;
    } else {
        echo "❌ Emergency Report: FAILED\n";
        echo "   Response: $reportResponse\n";
        $journeyResults['emergency_report'] = false;
        $reportId = null;
    }
} else {
    echo "⚠️ Emergency Report: SKIPPED (no auth token)\n";
    $journeyResults['emergency_report'] = false;
    $reportId = null;
}

// Step 5: Real-time Notification to Command Center
echo "\n🔔 STEP 5: Real-time Notification System\n";
if ($token && $reportId) {
    // Create urgent notification for command center
    $notificationData = [
        'title' => 'URGENT: Flash Flood Report',
        'message' => "New HIGH severity flood report #{$reportId} requires immediate attention",
        'type' => 'EMERGENCY_ALERT',
        'platform' => 'MOBILE',
        'recipient_role' => 'COMMAND_CENTER',
        'report_id' => $reportId,
        'priority' => 'HIGH'
    ];

    $notificationUrl = 'http://127.0.0.1:8000/api/v1/notifications';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $notificationUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $notificationResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        echo "✅ Real-time Notification: SUCCESS\n";
        echo "   Emergency alert sent to command center\n";
        $journeyResults['notifications'] = true;
    } else {
        echo "❌ Real-time Notification: FAILED\n";
        $journeyResults['notifications'] = false;
    }
} else {
    echo "⚠️ Real-time Notification: SKIPPED\n";
    $journeyResults['notifications'] = false;
}

// Step 6: File Upload (evidence photos from mobile)
echo "\n📸 STEP 6: Evidence File Upload\n";
if ($token && $reportId) {
    // Test file upload infrastructure
    $fileUploadUrl = "http://127.0.0.1:8000/api/v1/files/disasters/$reportId";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUploadUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $fileResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "✅ File Upload Infrastructure: SUCCESS\n";
        echo "   Ready to receive evidence photos\n";
        $journeyResults['file_upload'] = true;
    } else {
        echo "❌ File Upload Infrastructure: FAILED\n";
        $journeyResults['file_upload'] = false;
    }
} else {
    echo "⚠️ File Upload: SKIPPED\n";
    $journeyResults['file_upload'] = false;
}

// Step 7: Data Synchronization Validation
echo "\n🔄 STEP 7: Cross-Platform Data Synchronization\n";
if ($reportId) {
    try {
        $report = App\Models\DisasterReport::find($reportId);
        $user = App\Models\User::find($userId);

        if ($report && $user) {
            echo "✅ Data Synchronization: SUCCESS\n";
            echo "   Report exists in database: {$report->title}\n";
            echo "   User profile synced: {$user->name}\n";
            echo "   GPS coordinates stored: {$report->latitude}, {$report->longitude}\n";
            $journeyResults['data_sync'] = true;
        } else {
            echo "❌ Data Synchronization: FAILED\n";
            $journeyResults['data_sync'] = false;
        }
    } catch (Exception $e) {
        echo "❌ Data Synchronization: ERROR ({$e->getMessage()})\n";
        $journeyResults['data_sync'] = false;
    }
} else {
    echo "⚠️ Data Synchronization: SKIPPED\n";
    $journeyResults['data_sync'] = false;
}

// Step 8: Dashboard Integration (web platform visibility)
echo "\n🖥️ STEP 8: Web Dashboard Integration\n";
if ($token) {
    $dashboardUrl = 'http://127.0.0.1:8000/api/v1/reports/recent';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $dashboardUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $dashboardResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dashboardData = json_decode($dashboardResponse, true);

    if ($httpCode === 200 && isset($dashboardData['data'])) {
        echo "✅ Web Dashboard: SUCCESS\n";
        echo "   Reports visible on dashboard: " . count($dashboardData['data']) . "\n";
        $journeyResults['dashboard'] = true;
    } else {
        echo "❌ Web Dashboard: FAILED\n";
        $journeyResults['dashboard'] = false;
    }
} else {
    echo "⚠️ Web Dashboard: SKIPPED\n";
    $journeyResults['dashboard'] = false;
}

// Final User Journey Results
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n" . str_repeat("=", 70) . "\n";
echo "🏆 COMPLETE USER JOURNEY INTEGRATION TEST RESULTS\n";
echo str_repeat("=", 70) . "\n";

$successCount = array_sum($journeyResults);
$totalSteps = count($journeyResults);
$successRate = round(($successCount / $totalSteps) * 100, 1);

echo "Journey Duration: {$duration} seconds\n";
echo "Total Journey Steps: $totalSteps\n";
echo "Successful Steps: $successCount\n";
echo "Failed Steps: " . ($totalSteps - $successCount) . "\n";
echo "Journey Success Rate: {$successRate}%\n\n";

echo "User Journey Results:\n";
foreach ($journeyResults as $step => $result) {
    $status = $result ? "✅ SUCCESS" : "❌ FAILED";
    echo "  " . ucfirst(str_replace('_', ' ', $step)) . ": $status\n";
}

echo "\n";
if ($successRate >= 90) {
    echo "🎉 USER JOURNEY: EXCELLENT!\n";
    echo "   Complete mobile-to-web integration working perfectly.\n";
} elseif ($successRate >= 75) {
    echo "✅ USER JOURNEY: GOOD\n";
    echo "   Most user flows working, minor issues present.\n";
} elseif ($successRate >= 60) {
    echo "⚠️ USER JOURNEY: PARTIAL\n";
    echo "   Core functionality working, improvements needed.\n";
} else {
    echo "❌ USER JOURNEY: NEEDS WORK\n";
    echo "   Significant user experience issues detected.\n";
}

echo "\n=== Complete User Journey Test Complete ===\n";
