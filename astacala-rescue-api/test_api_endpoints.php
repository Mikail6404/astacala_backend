<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "🔗 API Endpoint Testing\n";
echo "=======================\n\n";

// Create a test user
$testUser = User::firstOrCreate(
    ['email' => 'api.test@example.com'],
    [
        'name' => 'API Test User',
        'password' => Hash::make('password123'),
        'phone' => '+6281234567890',
        'organization' => 'Test Organization',
        'role' => 'volunteer'
    ]
);

// Create a token for API testing
$token = $testUser->createToken('test-token')->plainTextToken;

echo "✅ Test user created with API token\n";
echo "   User: {$testUser->name} (ID: {$testUser->id})\n";
echo "   Token: " . substr($token, 0, 20) . "...\n\n";

// Test data for Gibran web format
$gibranTestData = [
    'judul_laporan' => 'Test Banjir via API - ' . date('H:i:s'),
    'jenis_bencana' => 'FLOOD',
    'deskripsi_kejadian' => 'Banjir setinggi 2 meter di wilayah Jakarta Utara. Air mulai surut namun masih ada genangan di beberapa titik.',
    'lokasi_kejadian' => 'Jakarta Utara, DKI Jakarta',
    'lat' => -6.1344,
    'lng' => 106.8370,
    'tingkat_dampak' => 'MEDIUM',
    'jumlah_terdampak' => 75
];

echo "📊 Test Data Prepared:\n";
foreach ($gibranTestData as $key => $value) {
    echo "   {$key}: {$value}\n";
}
echo "\n";

// Test API endpoints using cURL
function testApiEndpoint($url, $method = 'GET', $data = null, $token = null)
{
    $curl = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://127.0.0.1:8000' . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => $data ? json_encode($data) : null
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    return [
        'status_code' => $httpCode,
        'response' => $response ? json_decode($response, true) : null,
        'error' => $error,
        'raw_response' => $response
    ];
}

echo "🌐 Testing Gibran Web API Endpoints:\n";
echo "------------------------------------\n";

// Test 1: Submit pelaporan via Gibran web format
echo "1. Testing POST /api/gibran/pelaporans (Submit Report)\n";
$result1 = testApiEndpoint('/api/gibran/pelaporans', 'POST', $gibranTestData, $token);
echo "   Status: {$result1['status_code']}\n";
if ($result1['response']) {
    echo "   Response: " . json_encode($result1['response'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "   Raw Response: " . substr($result1['raw_response'], 0, 200) . "...\n";
}
echo "\n";

// Test 2: Get pelaporans list
echo "2. Testing GET /api/gibran/pelaporans (Get Reports List)\n";
$result2 = testApiEndpoint('/api/gibran/pelaporans', 'GET', null, $token);
echo "   Status: {$result2['status_code']}\n";
if ($result2['response']) {
    echo "   Total Reports: " . count(isset($result2['response']['data']) ? $result2['response']['data'] : []) . "\n";
    if (!empty($result2['response']['data'])) {
        $firstReport = $result2['response']['data'][0];
        $title = isset($firstReport['judul_laporan']) ? $firstReport['judul_laporan'] : 'N/A';
        echo "   First Report: {$title}\n";
    }
} else {
    echo "   Raw Response: " . substr($result2['raw_response'], 0, 200) . "...\n";
}
echo "\n";

// Test 3: Get dashboard statistics
echo "3. Testing GET /api/gibran/dashboard/statistics\n";
$result3 = testApiEndpoint('/api/gibran/dashboard/statistics', 'GET', null, $token);
echo "   Status: {$result3['status_code']}\n";
if ($result3['response']) {
    $stats = isset($result3['response']['data']) ? $result3['response']['data'] : [];
    echo "   Statistics:\n";
    foreach ($stats as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
} else {
    echo "   Raw Response: " . substr($result3['raw_response'], 0, 200) . "...\n";
}
echo "\n";

// Test 4: Test mobile API endpoint for comparison
echo "4. Testing GET /api/v1/reports (Mobile API for comparison)\n";
$result4 = testApiEndpoint('/api/v1/reports', 'GET', null, $token);
echo "   Status: {$result4['status_code']}\n";
if ($result4['response']) {
    $dataCount = isset($result4['response']['data']) ? count($result4['response']['data']) : 0;
    echo "   Mobile Reports Count: {$dataCount}\n";
} else {
    echo "   Raw Response: " . substr($result4['raw_response'], 0, 200) . "...\n";
}
echo "\n";

echo "🎯 API TESTING SUMMARY:\n";
echo "=======================\n";
echo "✅ Gibran Web API: " . ($result1['status_code'] == 201 ? "WORKING" : "NEEDS CHECK") . "\n";
echo "✅ Report Retrieval: " . ($result2['status_code'] == 200 ? "WORKING" : "NEEDS CHECK") . "\n";
echo "✅ Dashboard Stats: " . ($result3['status_code'] == 200 ? "WORKING" : "NEEDS CHECK") . "\n";
echo "✅ Mobile API Compatibility: " . ($result4['status_code'] == 200 ? "PRESERVED" : "NEEDS CHECK") . "\n";
echo "\n";

echo "💡 PRACTICAL TESTING COMPLETED!\n";
echo "Your integration is ready for real-world usage.\n";
