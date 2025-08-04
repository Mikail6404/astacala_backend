<?php

require_once 'vendor/autoload.php';

try {
    $config = [
        'host' => env('DB_HOST', '127.0.0.1'),
        'dbname' => env('DB_DATABASE', 'astacala_rescue'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ];

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']}",
        $config['username'],
        $config['password']
    );

    echo "DISASTER_REPORTS TABLE SCHEMA:\n";
    echo "==================================\n";

    $stmt = $pdo->query("DESCRIBE disaster_reports");
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    echo "\nFirst few disaster reports:\n";
    echo "==================================\n";

    $stmt = $pdo->query("SELECT * FROM disaster_reports LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Title: " . substr($row['title'], 0, 30) . "...\n";
        echo "User Reference Field: ";
        foreach ($row as $key => $value) {
            if (strpos($key, 'user') !== false) {
                echo "$key = $value ";
            }
        }
        echo "\n---\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}
