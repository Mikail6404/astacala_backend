<?php

echo "Testing both ports to identify backend API:\n";
echo "===============================================\n";

// Test port 8000
echo "Testing port 8000 health:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Port 8000 - HTTP Code: $httpCode\n";
echo "Port 8000 - Response: $response\n\n";

// Test port 8001
echo "Testing port 8001 health:\n";
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8001/api/v1/health');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Port 8001 - HTTP Code: $httpCode2\n";
echo "Port 8001 - Response: $response2\n\n";
