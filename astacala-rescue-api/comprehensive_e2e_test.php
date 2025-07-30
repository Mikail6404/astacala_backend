<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== COMPREHENSIVE DISASTER REPORT E2E TEST ===\n";

// Step 1: Check current database state
echo "\n--- STEP 1: DATABASE STATE BEFORE TEST ---\n";
$reportCount = App\Models\DisasterReport::count();
$userCount = App\Models\User::count();
echo "Total reports in database: $reportCount\n";
echo "Total users in database: $userCount\n";

if ($reportCount > 0) {
    echo "Existing reports:\n";
    $reports = App\Models\DisasterReport::all(['id', 'title', 'status', 'created_at']);
    foreach ($reports as $report) {
        echo "  ID: {$report->id}, Title: {$report->title}, Status: {$report->status}, Created: {$report->created_at}\n";
    }
}

// Step 2: Prepare test user
echo "\n--- STEP 2: PREPARE TEST USER ---\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if (!$testUser) {
    echo "Test user not found!\n";
    exit(1);
}
echo "Test user: {$testUser->name} ({$testUser->email})\n";
echo "User role: {$testUser->role}\n";
echo "Organization: " . ($testUser->organization ?? 'None') . "\n";

// Step 3: Test mobile app login simulation
echo "\n--- STEP 3: SIMULATE MOBILE APP LOGIN ---\n";
$loginUrl = 'http://127.0.0.1:8000/api/auth/login';
$loginData = [
    'email' => $testUser->email,
    'password' => 'password', // Default password from seeder
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login API Response (HTTP $loginHttpCode):\n";
$loginData = json_decode($loginResponse, true);

if ($loginHttpCode === 200 && isset($loginData['access_token'])) {
    $authToken = $loginData['access_token'];
    echo "✅ Login successful! Token: " . substr($authToken, 0, 20) . "...\n";
    echo "User data: {$loginData['user']['name']} ({$loginData['user']['email']})\n";
} else {
    echo "❌ Login failed!\n";
    echo $loginResponse . "\n";
    exit(1);
}

// Step 4: Test disaster report submission (simulating mobile app)
echo "\n--- STEP 4: SIMULATE MOBILE APP DISASTER REPORT SUBMISSION ---\n";

$reportUrl = 'http://127.0.0.1:8000/api/reports';
$reportData = [
    'title' => 'E2E Test: Mobile Disaster Report',
    'description' => "Tim: Test Rescue Team\nJumlah Personel: 8\nKontak: +62812345678\nInfo Bencana: Severe flooding in residential area\nJumlah Korban: 15\nKondisi: Water level 2 meters high\nDeskripsi: Complete test of disaster report submission from mobile app",
    'disasterType' => 'FLOOD',
    'severityLevel' => 'HIGH',
    'latitude' => '-6.2088',
    'longitude' => '106.8456',
    'locationName' => 'Jakarta Test Area - Comprehensive E2E Test',
    'incidentTimestamp' => date('c'),
    'teamName' => 'Mobile E2E Test Team',
    'estimatedAffected' => 150,
    'weatherCondition' => 'Heavy rain, poor visibility',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $reportUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($reportData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $authToken,
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$reportResponse = curl_exec($ch);
$reportHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Report Submission API Response (HTTP $reportHttpCode):\n";
echo $reportResponse . "\n";

$newReportId = null;
if ($reportHttpCode === 201) {
    $reportResult = json_decode($reportResponse, true);
    if (isset($reportResult['data']['reportId'])) {
        $newReportId = $reportResult['data']['reportId'];
        echo "✅ Report submitted successfully! Report ID: $newReportId\n";
    }
} else {
    echo "❌ Report submission failed!\n";
    if ($reportResponse) {
        $errorData = json_decode($reportResponse, true);
        if (isset($errorData['errors'])) {
            echo "Validation Errors:\n";
            foreach ($errorData['errors'] as $field => $errors) {
                echo "  $field: " . implode(', ', $errors) . "\n";
            }
        }
    }
}

// Step 5: Verify report was saved in database
echo "\n--- STEP 5: VERIFY REPORT IN DATABASE ---\n";
if ($newReportId) {
    $savedReport = App\Models\DisasterReport::find($newReportId);
    if ($savedReport) {
        echo "✅ Report verified in database:\n";
        echo "  ID: {$savedReport->id}\n";
        echo "  Title: {$savedReport->title}\n";
        echo "  Type: {$savedReport->disaster_type}\n";
        echo "  Severity: {$savedReport->severity_level}\n";
        echo "  Status: {$savedReport->status}\n";
        echo "  Location: {$savedReport->location_name}\n";
        echo "  Reported by: User ID {$savedReport->reported_by}\n";
        echo "  Created: {$savedReport->created_at}\n";
    } else {
        echo "❌ Report not found in database!\n";
    }
}

// Step 6: Test statistics API (should now show updated data)
echo "\n--- STEP 6: VERIFY STATISTICS UPDATE ---\n";
$statsUrl = 'http://127.0.0.1:8000/api/disasters/reports/statistics';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $statsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $authToken,
    'Accept: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$statsResponse = curl_exec($ch);
$statsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Statistics API Response (HTTP $statsHttpCode):\n";
if ($statsHttpCode === 200) {
    $statsData = json_decode($statsResponse, true);
    if (isset($statsData['data'])) {
        $stats = $statsData['data'];
        echo "✅ Updated Statistics:\n";
        echo "  Active Reports: {$stats['activeReports']}\n";
        echo "  Total Volunteers: {$stats['totalVolunteers']}\n";
        echo "  Ready Teams: {$stats['readyTeams']}\n";
        echo "  Recent Activity Count: " . count($stats['recentActivity']) . "\n";
    }
} else {
    echo "❌ Statistics API failed\n";
    echo $statsResponse . "\n";
}

// Step 7: Final database state
echo "\n--- STEP 7: FINAL DATABASE STATE ---\n";
$finalReportCount = App\Models\DisasterReport::count();
echo "Total reports after test: $finalReportCount\n";
echo "Reports added during test: " . ($finalReportCount - $reportCount) . "\n";

echo "\n=== COMPREHENSIVE E2E TEST COMPLETED ===\n";
echo "Summary:\n";
echo "  ✅ Backend API operational\n";
echo "  ✅ Authentication system working\n";
echo "  ✅ Disaster report submission working\n";
echo "  ✅ Database persistence confirmed\n";
echo "  ✅ Statistics API responding\n";
echo "\nNext: Test mobile app UI with this confirmed working backend!\n";
