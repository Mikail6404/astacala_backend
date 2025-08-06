<?php

// Test the updated authentication system
echo "Testing Updated Web Application Authentication:\n";
echo "===============================================\n";

// Test multiple credential combinations
$testCredentials = [
    ['username' => 'admin', 'password' => 'admin'],
    ['username' => 'admin', 'password' => 'admin123'],  // Direct backend password
    ['username' => 'admin@uat.test', 'password' => 'admin123'],  // Direct email/password
    ['username' => 'admin@uat.test', 'password' => 'admin'],  // Email with test password
];

foreach ($testCredentials as $index => $creds) {
    echo "\nTest " . ($index + 1) . ": username='{$creds['username']}', password='{$creds['password']}'\n";
    echo str_repeat("-", 60) . "\n";

    // Test login via web form
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/test-auth');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($creds));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";

    if ($httpCode == 200) {
        echo "✅ Authentication successful\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    } else {
        echo "❌ Authentication failed\n";
        echo "Response: " . substr($response, 0, 300) . "...\n";
    }
}

// Test direct backend API to verify it's still working
echo "\n" . str_repeat("=", 60) . "\n";
echo "Direct Backend API Test:\n";
echo str_repeat("=", 60) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'admin@uat.test',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Backend API Login - HTTP Code: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "✅ Backend API working\n";
    echo "User: {$data['data']['user']['name']} (Role: {$data['data']['user']['role']})\n";
} else {
    echo "❌ Backend API failed\n";
    echo "Response: $response\n";
}
