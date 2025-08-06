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

echo "=== CHECKING DATABASE SCHEMA ===\n\n";

// Check users table structure
echo "Users table structure:\n";
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']} {$column['Default']}\n";
}

echo "\n=== CHECKING USERS IN DATABASE ===\n\n";

// Get all users with correct column names
$stmt = $pdo->query("SELECT * FROM users LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "❌ No users found in database\n";
} else {
    echo "Found " . count($users) . " users (showing first 5):\n\n";

    foreach ($users as $user) {
        echo "User data:\n";
        foreach ($user as $key => $value) {
            echo "  $key: $value\n";
        }
        echo "---\n";
    }
}
