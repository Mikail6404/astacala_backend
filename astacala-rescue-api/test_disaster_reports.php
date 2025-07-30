<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DISASTER REPORT ENDPOINTS TEST ===\n";

// First, get a valid auth token
echo "\n--- STEP 1: Authenticate ---\n";
$loginUrl = 'http://127.0.0.1:8000/api/auth/login';
$loginData = [
    'email' => 'test@example.com',
    'password' => 'password123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);
$token = $responseData['data']['tokens']['accessToken'] ?? null;

if (!$token) {
    echo "❌ Authentication failed\n";
    exit(1);
}

echo "✅ Authentication successful\n";
echo "Token: " . substr($token, 0, 20) . "...\n";

// Test 1: Get disaster reports
echo "\n--- STEP 2: Test GET /api/reports ---\n";
$reportsUrl = 'http://127.0.0.1:8000/api/reports';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $reportsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$reportsResponse = curl_exec($ch);
$reportsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET Reports Response (HTTP $reportsHttpCode):\n";
if ($reportsHttpCode === 200) {
    $reportsData = json_decode($reportsResponse, true);
    echo "✅ Reports endpoint working\n";
    echo "Total reports in response: " . count($reportsData['data'] ?? []) . "\n";
} else {
    echo "❌ Reports endpoint failed\n";
    echo "Response: $reportsResponse\n";
}

// Test 2: Create disaster report
echo "\n--- STEP 3: Test POST /api/reports ---\n";
$createUrl = 'http://127.0.0.1:8000/api/reports';
$reportData = [
    'title' => 'Test Mobile Integration Report',
    'description' => 'Testing disaster report creation from backend integration test',
    'disasterType' => 'FLOOD',
    'severityLevel' => 'HIGH',
    'latitude' => '-6.2088',
    'longitude' => '106.8456',
    'locationName' => 'Jakarta Test Area',
    'incidentTimestamp' => date('c'),
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $createUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($reportData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$createResponse = curl_exec($ch);
$createHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST Reports Response (HTTP $createHttpCode):\n";
if ($createHttpCode === 201 || $createHttpCode === 200) {
    echo "✅ Report creation working\n";
    $createData = json_decode($createResponse, true);
    echo "Created report ID: " . ($createData['data']['id'] ?? 'unknown') . "\n";
} else {
    echo "❌ Report creation failed\n";
    echo "Response: $createResponse\n";
}

// Test 3: Get statistics
echo "\n--- STEP 4: Test GET /api/reports/statistics ---\n";
$statsUrl = 'http://127.0.0.1:8000/api/reports/statistics';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $statsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$statsResponse = curl_exec($ch);
$statsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET Statistics Response (HTTP $statsHttpCode):\n";
if ($statsHttpCode === 200) {
    echo "✅ Statistics endpoint working\n";
    $statsData = json_decode($statsResponse, true);
    echo "Total reports in stats: " . ($statsData['totalReports'] ?? 0) . "\n";
} else {
    echo "❌ Statistics endpoint failed\n";
    echo "Response: $statsResponse\n";
}

echo "\n=== DISASTER REPORT ENDPOINTS TEST COMPLETE ===\n";
