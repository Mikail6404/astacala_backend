<?php

echo "=== DISASTER REPORT SUBMISSION TEST ===\n";

// Test disaster report creation
$testReportData = [
    'title' => 'Test Report via API',
    'description' => 'Tim: Test Team\nJumlah Personel: 5\nKontak: 08123456789\nInfo Bencana: Gempa bumi test\nDeskripsi: Test report untuk verifikasi integrasi',
    'disasterType' => 'EARTHQUAKE',
    'severityLevel' => 'MEDIUM',
    'latitude' => '-6.175392',
    'longitude' => '106.827153',
    'incidentTimestamp' => date('c'), // ISO 8601 format
    'locationName' => 'Jakarta Test Location'
];

// Get auth token first
$loginData = [
    'email' => 'test@example.com',
    'password' => 'password123'
];

$loginCurl = curl_init();
curl_setopt_array($loginCurl, [
    CURLOPT_URL => 'http://localhost:8000/api/auth/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($loginData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ]
]);

$loginResponse = curl_exec($loginCurl);
$loginHttpCode = curl_getinfo($loginCurl, CURLINFO_HTTP_CODE);
curl_close($loginCurl);

echo "--- Login Test ---\n";
echo "Login Response (HTTP $loginHttpCode):\n";

if ($loginHttpCode === 200) {
    $loginData = json_decode($loginResponse, true);
    if (isset($loginData['data']['tokens']['accessToken'])) {
        $token = $loginData['data']['tokens']['accessToken'];
        echo "✅ Login successful, token obtained\n";

        // Now submit disaster report
        echo "\n--- Disaster Report Submission Test ---\n";

        $reportCurl = curl_init();
        curl_setopt_array($reportCurl, [
            CURLOPT_URL => 'http://localhost:8000/api/reports',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($testReportData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]
        ]);

        $reportResponse = curl_exec($reportCurl);
        $reportHttpCode = curl_getinfo($reportCurl, CURLINFO_HTTP_CODE);
        curl_close($reportCurl);

        echo "Report Response (HTTP $reportHttpCode):\n";
        echo "$reportResponse\n";

        if ($reportHttpCode === 201) {
            echo "✅ DISASTER REPORT SUBMISSION SUCCESSFUL!\n";
        } else {
            echo "❌ Disaster report submission failed\n";
        }
    } else {
        echo "❌ Login failed - no token in response\n";
        echo "$loginResponse\n";
    }
} else {
    echo "❌ Login failed\n";
    echo "$loginResponse\n";
}

echo "\n=== TEST COMPLETE ===\n";
