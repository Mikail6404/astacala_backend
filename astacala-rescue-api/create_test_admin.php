<?php

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup database connection
$pdo = new PDO(
    "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'],
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);

echo "=== CREATING TEST ADMIN FOR API TESTING ===\n\n";

$testEmail = 'test-admin@astacala.test';
$testPassword = 'testpassword123';

// Check if test admin already exists
$stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
$stmt->execute([$testEmail]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingUser) {
    echo "Test admin already exists with ID: {$existingUser['id']}\n";
    echo "Email: {$existingUser['email']}\n";

    // Update password
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $updateStmt->execute([$hashedPassword, $testEmail]);
    echo "✅ Password updated successfully\n";
} else {
    // Create new test admin
    $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("
        INSERT INTO users (
            name, email, password, role, is_active, 
            phone, birth_date, place_of_birth, member_number,
            organization, email_verified_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $now = date('Y-m-d H:i:s');

    $insertStmt->execute([
        'Test Admin for API',
        $testEmail,
        $hashedPassword,
        'ADMIN',
        1,
        '+6281234567890',
        '1990-01-01',
        'Jakarta',
        'ADMIN001',
        'Test Organization',
        $now,
        $now,
        $now
    ]);

    $newUserId = $pdo->lastInsertId();
    echo "✅ Created new test admin with ID: $newUserId\n";
}

echo "\nTest credentials for API:\n";
echo "Email: $testEmail\n";
echo "Password: $testPassword\n";

echo "\n=== TESTING LOGIN WITH NEW CREDENTIALS ===\n";

$loginData = [
    'email' => $testEmail,
    'password' => $testPassword
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://127.0.0.1:8000/api/v1/auth/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($loginData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "Login Response Code: $httpCode\n";
echo "Login Response: $response\n";

if ($httpCode === 200) {
    $loginResponse = json_decode($response, true);
    if (isset($loginResponse['access_token'])) {
        echo "\n✅ Authentication successful!\n";
        echo "Token: " . substr($loginResponse['access_token'], 0, 50) . "...\n";
    }
}
