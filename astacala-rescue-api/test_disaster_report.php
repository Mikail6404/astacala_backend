<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING DISASTER REPORT SUBMISSION ===\n";

// Get a user and create a token
$user = App\Models\User::first();
if (!$user) {
    echo "No users found!\n";
    exit(1);
}

echo "Using user: {$user->name} ({$user->email})\n";

// Create a token for the user
$token = $user->createToken('test-report-token')->plainTextToken;
echo "Generated token: " . substr($token, 0, 20) . "...\n";

// Test the disaster report submission endpoint
$url = 'http://127.0.0.1:8000/api/reports';
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
];

// Test data that matches the mobile app structure
$postData = [
    'title' => 'Test Mobile Report',
    'description' => "Tim: Test Team\nJumlah Personel: 5\nKontak: +62123456789\nInfo Bencana: Test disaster information\nJumlah Korban: 2\nDeskripsi: This is a test disaster report from mobile app integration test",
    'disasterType' => 'FLOOD',
    'severityLevel' => 'MEDIUM',
    'latitude' => '-6.2088',
    'longitude' => '106.8456',
    'locationName' => 'Jakarta Test Location',
    'incidentTimestamp' => date('c'), // Current timestamp in ISO 8601 format
    'teamName' => 'Test Team',
    'estimatedAffected' => 50,
];

// Convert to query string for testing
$postFields = http_build_query($postData);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nAPI Response (HTTP $httpCode):\n";
echo $response . "\n";

// Parse and display the result
if ($httpCode === 201) {
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        echo "\n=== REPORT SUBMITTED SUCCESSFULLY ===\n";
        echo "Report ID: " . $data['data']['reportId'] . "\n";
        echo "Status: " . $data['data']['status'] . "\n";
        echo "Submitted At: " . $data['data']['submittedAt'] . "\n";
    }
} else {
    echo "\nAPI request failed with HTTP code: $httpCode\n";
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['errors'])) {
            echo "Validation Errors:\n";
            foreach ($data['errors'] as $field => $errors) {
                echo "  $field: " . implode(', ', $errors) . "\n";
            }
        }
    }
}

echo "\n=== TEST COMPLETED ===\n";
