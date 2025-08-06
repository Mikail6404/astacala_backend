<?php

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/v1/users/admin-list');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer test-token',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== TESTING ADMIN LIST API ENDPOINT ===\n";
echo 'HTTP Code: '.$httpCode."\n";
echo 'Response: '.$response."\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['data']) && ! empty($data['data'])) {
        echo "Sample Admin Record:\n";
        $firstAdmin = $data['data'][0];
        echo '  ID: '.($firstAdmin['id'] ?? 'N/A')."\n";
        echo '  Name: '.($firstAdmin['name'] ?? 'N/A')."\n";
        echo '  Place of Birth: '.($firstAdmin['place_of_birth'] ?? 'N/A')."\n";
        echo '  Member Number: '.($firstAdmin['member_number'] ?? 'N/A')."\n";
        echo '  Email: '.($firstAdmin['email'] ?? 'N/A')."\n";
    }
}

echo "\n✅ API endpoint test completed!\n";
