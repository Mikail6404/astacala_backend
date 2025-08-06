<?php

// Test the web application login and admin dashboard features
echo "Testing Web Application Dashboard Features:\n";
echo "==========================================\n";

// Test 1: Login to web app with admin credentials
echo "1. Testing web app login...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'admin',
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/web_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/web_cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login response code: $httpCode\n";
if ($httpCode == 200) {
    echo "✅ Login successful!\n";
} else {
    echo "❌ Login failed\n";
    echo substr($response, 0, 500)."...\n";
}

// Test 2: Access dashboard
echo "\n2. Testing dashboard access...\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8001/dashboard');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch2, CURLOPT_COOKIEJAR, '/tmp/web_cookies.txt');
curl_setopt($ch2, CURLOPT_COOKIEFILE, '/tmp/web_cookies.txt');

$dashResponse = curl_exec($ch2);
$dashCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Dashboard response code: $dashCode\n";
if ($dashCode == 200) {
    echo "✅ Dashboard accessible!\n";
} else {
    echo "❌ Dashboard not accessible\n";
}

// Test 3: Test admin features
$adminFeatures = [
    '/Dataadmin' => 'Admin Management',
    '/Datapengguna' => 'User Management',
    '/pelaporan' => 'Reporting Data',
    '/publikasi' => 'Publication Management',
];

echo "\n3. Testing admin features...\n";

foreach ($adminFeatures as $url => $name) {
    echo "Testing $name ($url)...\n";

    $ch3 = curl_init();
    curl_setopt($ch3, CURLOPT_URL, 'http://localhost:8001'.$url);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch3, CURLOPT_COOKIEJAR, '/tmp/web_cookies.txt');
    curl_setopt($ch3, CURLOPT_COOKIEFILE, '/tmp/web_cookies.txt');
    curl_setopt($ch3, CURLOPT_TIMEOUT, 10);

    $featureResponse = curl_exec($ch3);
    $featureCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);

    echo "  Status: $featureCode ";
    if ($featureCode == 200) {
        echo "✅ Working\n";
    } else {
        echo "❌ Not working\n";
    }
}

// Test 4: Direct API test to make sure backend integration works
echo "\n4. Testing backend API integration...\n";

$ch4 = curl_init();
curl_setopt($ch4, CURLOPT_URL, 'http://localhost:8000/api/v1/health');
curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);

$apiResponse = curl_exec($ch4);
$apiCode = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
curl_close($ch4);

echo "Backend API health: $apiCode ";
if ($apiCode == 200) {
    echo "✅ Backend API working\n";
} else {
    echo "❌ Backend API not working\n";
}

echo "\nTest Summary:\n";
echo "=============\n";
echo 'Web login: '.($httpCode == 200 ? '✅' : '❌')."\n";
echo 'Dashboard: '.($dashCode == 200 ? '✅' : '❌')."\n";
echo 'Backend API: '.($apiCode == 200 ? '✅' : '❌')."\n";
