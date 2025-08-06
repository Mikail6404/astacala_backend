<?php

// Test the admin-list endpoint specifically
require_once 'vendor/autoload.php';

echo "Testing /api/v1/users/admin-list endpoint\n";
echo "========================================\n";

// Test with our admin user token
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDEvYXBpL3YxL2F1dGgvbG9naW4iLCJpYXQiOjE3MzYzNjMzNjUsImV4cCI6MTczNjM2Njk2NSwibmJmIjoxNzM2MzYzMzY1LCJqdGkiOiJ6UG1lVGpCRzhsUmwyNGJ0Iiwic3ViIjoiNDkiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.vITH4ZjnC_Xqg0OlSVrK_L-iw2zF6ILJ8OTdnNc2a-8';

// Test admin-list endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/api/v1/users/admin-list');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer '.$token,
    'Accept: application/json',
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
echo "\nVerbose log:\n";
echo $verboseLog;

curl_close($ch);

// Also test if we can reach the base API
echo "\n\nTesting base API health:\n";
echo "========================\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8001/api/v1/health');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$healthResponse = curl_exec($ch2);
$healthCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Health Check - HTTP Code: $healthCode\n";
echo "Health Response: $healthResponse\n";
