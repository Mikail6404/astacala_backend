<?php

require_once __DIR__.'/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Setting up admin user with known password ===\n\n";

// Find or create admin user
$adminUser = App\Models\User::where('role', 'ADMIN')->first();

if (! $adminUser) {
    echo "Creating new admin user...\n";
    $adminUser = App\Models\User::create([
        'name' => 'Test Admin',
        'email' => 'admin@astacala.test',
        'password' => bcrypt('password'),
        'role' => 'ADMIN',
        'phone' => '081234567890',
        'is_active' => true,
    ]);
    echo "✅ Admin user created\n";
} else {
    echo "Updating existing admin user password...\n";
    $adminUser->password = bcrypt('password');
    $adminUser->save();
    echo "✅ Admin user password updated\n";
}

echo "\nAdmin user details:\n";
echo "ID: {$adminUser->id}\n";
echo "Name: {$adminUser->name}\n";
echo "Email: {$adminUser->email}\n";
echo "Role: {$adminUser->role}\n";
echo "Password: password (for testing)\n";
