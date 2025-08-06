<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

// Setup database connection for backend
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'astacala_rescue',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Checking users table structure:\n";
echo "==============================\n";

try {
    // Get table structure
    $columns = Capsule::select("DESCRIBE users");

    foreach ($columns as $column) {
        echo "Column: {$column->Field}, Type: {$column->Type}, Null: {$column->Null}, Default: {$column->Default}\n";
    }

    echo "\nTesting admin query directly:\n";
    echo "============================\n";

    // Test the query without problematic columns
    $admins = Capsule::table('users')
        ->whereIn('role', ['ADMIN', 'admin', 'super_admin', 'SUPER_ADMIN'])
        ->select('id', 'name', 'email', 'role', 'created_at')
        ->orderBy('created_at', 'desc')
        ->get();

    echo "Found " . count($admins) . " admin users:\n";
    foreach ($admins as $admin) {
        echo "ID: {$admin->id}, Name: {$admin->name}, Email: {$admin->email}, Role: {$admin->role}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
