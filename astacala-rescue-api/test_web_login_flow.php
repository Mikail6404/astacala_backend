<?php

// Test actual web app login and dashboard access with session management
echo "Testing Web App Login Flow with Session:\n";
echo "========================================\n";

// Set up cookie jar for session management
$cookieJar = tempnam(sys_get_temp_dir(), 'web_cookies');

echo "1. Testing login with admin credentials...\n";

// First, get the login page to get CSRF token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

$loginPage = curl_exec($ch);
curl_close($ch);

// Extract CSRF token if present
preg_match('/<meta name="csrf-token" content="([^"]+)"/', $loginPage, $csrfMatches);
$csrfToken = $csrfMatches[1] ?? '';

echo "CSRF Token: " . ($csrfToken ? "Found" : "Not found") . "\n";

// Try login with form data
echo "\n2. Submitting login form...\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8001/login');
curl_setopt($ch2, CURLOPT_POST, true);

$postData = [
    'username' => 'admin',
    'password' => 'admin',
];

if ($csrfToken) {
    $postData['_token'] = $csrfToken;
}

curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects
curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieJar);
curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieJar);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'X-Requested-With: XMLHttpRequest',
]);

$loginResponse = curl_exec($ch2);
$loginCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch2, CURLINFO_REDIRECT_URL);
curl_close($ch2);

echo "Login HTTP Code: $loginCode\n";
echo "Redirect URL: $redirectUrl\n";

if ($loginCode == 302 && str_contains($redirectUrl, 'dashboard')) {
    echo "✅ Login successful - redirecting to dashboard\n";

    // Now test dashboard access
    echo "\n3. Testing dashboard access...\n";

    $ch3 = curl_init();
    curl_setopt($ch3, CURLOPT_URL, 'http://localhost:8001/dashboard');
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch3, CURLOPT_COOKIEFILE, $cookieJar);

    $dashboardResponse = curl_exec($ch3);
    $dashboardCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);

    echo "Dashboard HTTP Code: $dashboardCode\n";

    if ($dashboardCode == 200) {
        echo "✅ Dashboard accessible\n";

        // Test admin features
        echo "\n4. Testing admin features...\n";

        $adminFeatures = [
            '/Dataadmin' => 'Admin Management',
            '/Datapengguna' => 'User Management',
            '/pelaporan' => 'Reporting Data',
            '/publikasi' => 'Publication Management'
        ];

        foreach ($adminFeatures as $url => $name) {
            $ch4 = curl_init();
            curl_setopt($ch4, CURLOPT_URL, 'http://localhost:8001' . $url);
            curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch4, CURLOPT_COOKIEJAR, $cookieJar);
            curl_setopt($ch4, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch4, CURLOPT_TIMEOUT, 10);

            $featureResponse = curl_exec($ch4);
            $featureCode = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
            curl_close($ch4);

            echo "$name ($url): ";
            if ($featureCode == 200) {
                echo "✅ Working\n";
            } else {
                echo "❌ Failed (Code: $featureCode)\n";
            }
        }
    } else {
        echo "❌ Dashboard not accessible\n";
    }
} else {
    echo "❌ Login failed\n";
    echo "Response: " . substr($loginResponse, 0, 300) . "...\n";
}

// Clean up
unlink($cookieJar);
