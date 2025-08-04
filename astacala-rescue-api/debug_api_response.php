<?php

// Debug the API listing response
$token = '51|rOV0WsUSvC7j44c33dHLRjNkqij9POW7sVK6a0Cf59f3a946'; // Fresh token

$url = 'http://127.0.0.1:8000/api/v1/reports';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response structure:\n";
$decoded = json_decode($response, true);
print_r($decoded);
