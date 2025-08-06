<?php

echo "=== TESTING CORRECTED DATA SYNCHRONIZATION ===\n";

$baseUrl = 'http://127.0.0.1:8000';

// Get authentication token
$credentials = [
    'email' => 'volunteer@mobile.test',
    'password' => 'password123'
];

$response = makeRequest('POST', $baseUrl . '/api/v1/auth/login', $credentials);
$token = $response['data']['tokens']['accessToken'];

echo "🔑 Authentication successful\n";

// Test with CORRECTED field names (camelCase as expected by controller)
$correctReportData = [
    'title' => 'FIXED Test Disaster Report',
    'description' => 'Testing data synchronization with corrected field names',
    'disasterType' => 'FLOOD',  // Changed from disaster_type
    'severityLevel' => 'MEDIUM', // Changed from severity_level  
    'latitude' => -6.2088,
    'longitude' => 106.8456,
    'locationName' => 'Jakarta Test', // Changed from location_name
    'incidentTimestamp' => date('Y-m-d\TH:i:s\Z'), // Added required field
    'estimatedAffected' => 100,
    'teamName' => 'Test Team',
    'weatherCondition' => 'Clear'
];

echo "\n🔄 Testing Data Synchronization with corrected fields...\n";

try {
    // CREATE
    echo "  📝 Creating disaster report...\n";
    $createResponse = makeRequest('POST', $baseUrl . '/api/v1/reports', $correctReportData, $token);

    $reportId = $createResponse['data']['reportId'] ?? $createResponse['id'] ?? null;

    if (!$reportId) {
        throw new Exception("No report ID returned: " . json_encode($createResponse));
    }

    echo "  ✅ CREATE: Report ID $reportId created successfully\n";

    // READ
    echo "  📖 Reading back created report...\n";
    $readResponse = makeRequest('GET', $baseUrl . '/api/v1/reports/' . $reportId, [], $token);

    $readId = $readResponse['id'] ?? $readResponse['data']['id'] ?? null;

    if ($readId != $reportId) {
        throw new Exception("Read failed - ID mismatch");
    }

    echo "  ✅ READ: Report successfully retrieved\n";

    // UPDATE  
    echo "  ✏️ Updating report...\n";
    $updateData = ['title' => 'FIXED Updated Test Report'];
    $updateResponse = makeRequest('PUT', $baseUrl . '/api/v1/reports/' . $reportId, $updateData, $token);

    echo "  ✅ UPDATE: Report successfully updated\n";

    echo "\n🎉 DATA SYNCHRONIZATION: FIXED AND WORKING!\n";
} catch (Exception $e) {
    echo "  ❌ Data sync still failing: " . $e->getMessage() . "\n";

    // If still failing, let's debug the validation errors
    if (strpos($e->getMessage(), '422') !== false) {
        echo "\n🔍 DEBUGGING VALIDATION ERRORS:\n";
        echo "Request data sent:\n" . json_encode($correctReportData, JSON_PRETTY_PRINT) . "\n";
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
