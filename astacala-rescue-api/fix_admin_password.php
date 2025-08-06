<?php

echo "Testing password verification:\n";
echo "=============================\n";

$password = 'admin123';
$storedHash = '$2y$12$5K7PWsfl2XndrBRM/JmxcO1t9MmETyJbj1RSEb9VT1HDEZTwIgNsK';

$isValid = password_verify($password, $storedHash);
echo "Password 'admin123' valid: ".($isValid ? 'YES' : 'NO')."\n";

// Let's try other common passwords
$passwords = ['admin123', 'password', 'admin', '123456', 'uat123'];

foreach ($passwords as $pwd) {
    $valid = password_verify($pwd, $storedHash);
    echo "Password '$pwd': ".($valid ? 'VALID' : 'invalid')."\n";
}

// Let's create a new hash for admin123 and update the user
echo "\nCreating new password hash for admin123...\n";
$newHash = password_hash('admin123', PASSWORD_BCRYPT);
echo "New hash: $newHash\n";

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

// Update the admin user password
Capsule::table('users')
    ->where('email', 'admin@uat.test')
    ->update(['password' => $newHash]);

echo "Password updated for admin@uat.test\n";
