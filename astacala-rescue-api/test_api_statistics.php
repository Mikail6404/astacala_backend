<?php

require_once 'vendor/autoload.php';

// Create Laravel app instance  
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING STATISTICS API ===\n";

// Get a user and create a token
$user = App\Models\User::first();
if (!$user) {
    echo "No users found!\n";
    exit(1);
}

echo "Using user: {$user->name} ({$user->email})\n";

// Create a token for the user
$token = $user->createToken('test-token')->plainTextToken;
echo "Generated token: " . substr($token, 0, 20) . "...\n";

// Test the statistics endpoint
$url = 'http://127.0.0.1:8000/api/disasters/reports/statistics';
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nAPI Response (HTTP $httpCode):\n";
echo $response . "\n";

// Parse and display the statistics
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        $stats = $data['data'];
        echo "\n=== PARSED STATISTICS ===\n";
        echo "Active Reports: " . ($stats['activeReports'] ?? 'N/A') . "\n";
        echo "Total Volunteers: " . ($stats['totalVolunteers'] ?? 'N/A') . "\n";
        echo "Ready Teams: " . ($stats['readyTeams'] ?? 'N/A') . "\n";
    }
} else {
    echo "API request failed with HTTP code: $httpCode\n";
}

echo "\n=== TEST COMPLETED ===\n";
