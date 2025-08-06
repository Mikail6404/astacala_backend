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

echo "Checking users in BACKEND database:\n";
echo "===================================\n";

try {
    $users = Capsule::table('users')->get();

    foreach ($users as $user) {
        echo "ID: {$user->id}, Email: {$user->email}, Role: {$user->role}, Name: {$user->name}\n";
    }

    echo "\nChecking for admin user specifically:\n";
    $adminUser = Capsule::table('users')->where('email', 'admin@uat.test')->first();

    if ($adminUser) {
        echo "Admin user found:\n";
        echo "ID: {$adminUser->id}\n";
        echo "Email: {$adminUser->email}\n";
        echo "Role: {$adminUser->role}\n";
        echo "Password hash: {$adminUser->password}\n";
    } else {
        echo "Admin user NOT found in backend database!\n";

        // Let's create the admin user in the backend database
        echo "\nCreating admin user in backend database...\n";

        Capsule::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@uat.test',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'ADMIN',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "Admin user created successfully!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
