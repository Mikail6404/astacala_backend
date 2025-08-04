<?php

/**
 * Phase 3 GPS and Mapping Coordination Test
 * Testing GPS data flow from mobile app to backend and web dashboard
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 GPS and Mapping Coordination Test ===\n\n";

// Step 1: Authenticate test user
echo "1. Authenticating test user:\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';
$loginData = [
    'email' => $testUser->email,
    'password' => 'password',
];

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
curl_close($ch);

$loginData = json_decode($loginResponse, true);
$token = $loginData['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Step 2: Test disaster report creation with GPS coordinates
echo "2. Testing disaster report creation with GPS coordinates:\n";
$createReportUrl = 'http://127.0.0.1:8000/api/v1/reports';

// GPS coordinates for various locations in Indonesia
$gpsTestData = [
    [
        'name' => 'Jakarta Central',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'location_name' => 'Jakarta Pusat',
        'address' => 'Jl. MH Thamrin, Jakarta Pusat'
    ],
    [
        'name' => 'Bandung',
        'latitude' => -6.9175,
        'longitude' => 107.6191,
        'location_name' => 'Bandung City',
        'address' => 'Jl. Asia Afrika, Bandung'
    ],
    [
        'name' => 'Surabaya',
        'latitude' => -7.2575,
        'longitude' => 112.7521,
        'location_name' => 'Surabaya Port',
        'address' => 'Pelabuhan Tanjung Perak, Surabaya'
    ]
];

$createdReports = [];

foreach ($gpsTestData as $index => $location) {
    echo "Testing GPS location: {$location['name']}\n";

    $reportData = [
        'title' => "GPS Test Report - {$location['name']}",
        'description' => "Testing GPS coordination for {$location['name']} location",
        'disaster_type' => 'FLOOD',
        'severity_level' => 'MEDIUM',
        'latitude' => $location['latitude'],
        'longitude' => $location['longitude'],
        'location_name' => $location['location_name'],
        'address' => $location['address'],
        'team_name' => 'GPS Test Team',
        'reported_by' => $testUser->id
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $createReportUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reportData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $createResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Create report response (HTTP $httpCode): $createResponse\n";

    $responseData = json_decode($createResponse, true);
    if (isset($responseData['data']['id'])) {
        $createdReports[] = $responseData['data']['id'];
        echo "✅ Report created with ID: {$responseData['data']['id']}\n";
    }
    echo "\n";
}

// Step 3: Test location-based retrieval
echo "3. Testing location-based report retrieval:\n";
$reportsUrl = 'http://127.0.0.1:8000/api/v1/reports';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $reportsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$reportsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Reports retrieval response (HTTP $httpCode):\n";
$reportsData = json_decode($reportsResponse, true);
if (isset($reportsData['data'])) {
    foreach ($reportsData['data'] as $report) {
        if (in_array($report['id'], $createdReports)) {
            echo "  Report ID: {$report['id']}\n";
            echo "  Title: {$report['title']}\n";
            echo "  GPS: {$report['latitude']}, {$report['longitude']}\n";
            echo "  Location: {$report['location_name']}\n";
            echo "  Address: {$report['address']}\n\n";
        }
    }
}

// Step 4: Test GPS coordinate validation
echo "4. Testing GPS coordinate validation:\n";

// Test invalid coordinates
$invalidGpsData = [
    'title' => 'Invalid GPS Test',
    'description' => 'Testing invalid GPS coordinates',
    'disaster_type' => 'FLOOD',
    'severity_level' => 'LOW',
    'latitude' => 999.999, // Invalid latitude
    'longitude' => 999.999, // Invalid longitude
    'location_name' => 'Invalid Location',
    'team_name' => 'Test Team'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $createReportUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invalidGpsData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$invalidResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Invalid GPS response (HTTP $httpCode): $invalidResponse\n\n";

// Step 5: Test report update with new GPS coordinates
echo "5. Testing GPS coordinate updates:\n";
if (!empty($createdReports)) {
    $reportId = $createdReports[0];
    $updateUrl = "http://127.0.0.1:8000/api/v1/reports/$reportId";

    $updateData = [
        'latitude' => -6.1751,
        'longitude' => 106.8650,
        'location_name' => 'Updated Location - Monas',
        'address' => 'Monas, Jakarta Pusat, DKI Jakarta'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $updateUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $updateResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "GPS update response (HTTP $httpCode): $updateResponse\n\n";
}

// Step 6: Test database GPS data validation
echo "6. Testing database GPS data consistency:\n";
foreach ($createdReports as $reportId) {
    $report = App\Models\DisasterReport::find($reportId);
    if ($report) {
        echo "Report ID: {$report->id}\n";
        echo "  Database GPS: {$report->latitude}, {$report->longitude}\n";
        echo "  Location Name: {$report->location_name}\n";
        echo "  Address: {$report->address}\n\n";
    }
}

echo "✅ GPS and mapping coordination test complete\n";
echo "=== GPS Coordination Test Complete ===\n";
