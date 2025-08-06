<?php

// Test the admin-list endpoint on the CORRECT port (8000)
require_once 'vendor/autoload.php';

echo "Testing /api/v1/users/admin-list endpoint on CORRECT port 8000\n";
echo "==============================================================\n";

// Test with our admin user token
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDEvYXBpL3YxL2F1dGgvbG9naW4iLCJpYXQiOjE3MzYzNjMzNjUsImV4cCI6MTczNjM2Njk2NSwibmJmIjoxNzM2MzYzMzY1LCJqdGkiOiJ6UG1lVGpCRzhsUmwyNGJ0Iiwic3ViIjoiNDkiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.vITH4ZjnC_Xqg0OlSVrK_L-iw2zF6ILJ8OTdnNc2a-8';

// Test admin-list endpoint on port 8000
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/users/admin-list');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer '.$token,
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Let's also test the statistics endpoint
echo "\n\nTesting /api/v1/users/statistics endpoint:\n";
echo "=============================================\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/v1/users/statistics');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer '.$token,
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
echo "Response: $response2\n";
