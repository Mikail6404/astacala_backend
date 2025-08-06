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

echo "=== CHECKING USERS IN DATABASE ===\n\n";

// Get all users
$stmt = $pdo->query("SELECT id, nama_lengkap, email, role, status, created_at FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "❌ No users found in database\n";
} else {
    echo "Found " . count($users) . " users:\n\n";

    foreach ($users as $user) {
        echo "ID: {$user['id']}\n";
        echo "Name: {$user['nama_lengkap']}\n";
        echo "Email: {$user['email']}\n";
        echo "Role: {$user['role']}\n";
        echo "Status: {$user['status']}\n";
        echo "Created: {$user['created_at']}\n";
        echo "---\n";
    }

    // Check admin users specifically
    $adminStmt = $pdo->query("SELECT * FROM users WHERE role = 'admin'");
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nAdmin users found: " . count($admins) . "\n";

    if (!empty($admins)) {
        echo "\nFirst admin credentials for testing:\n";
        $firstAdmin = $admins[0];
        echo "Email: {$firstAdmin['email']}\n";
        echo "ID: {$firstAdmin['id']}\n";
        echo "Status: {$firstAdmin['status']}\n";
    }
}
