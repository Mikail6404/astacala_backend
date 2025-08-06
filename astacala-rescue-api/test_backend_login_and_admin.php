<?php

// Login to the CORRECT backend API (port 8000) to get proper token
require_once 'vendor/autoload.php';

echo "Logging in to backend API on port 8000:\n";
echo "======================================\n";

// Login with admin credentials
$loginData = [
    'email' => 'admin@uat.test',
    'password' => 'admin123',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login HTTP Code: $httpCode\n";
echo "Login Response: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['data']['tokens']['accessToken'])) {
        $token = $data['data']['tokens']['accessToken'];
        echo "\nGot token: $token\n";

        // Now test admin-list with correct token
        echo "\nTesting admin-list with correct token:\n";
        echo "=====================================\n";

        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/v1/users/admin-list');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $adminResponse = curl_exec($ch2);
        $adminCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        echo "Admin List HTTP Code: $adminCode\n";
        echo "Admin List Response: $adminResponse\n";
    }
}
