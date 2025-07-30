<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== AVAILABLE USERS ===\n";
$users = App\Models\User::all(['id', 'name', 'email']);
foreach ($users as $user) {
    echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
}

// Try to create a test login or update the existing user
echo "\n=== TESTING LOGIN CREDENTIALS ===\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if ($testUser) {
    // Update the user password to a known value
    $testUser->password = bcrypt('password123');
    $testUser->save();
    echo "Updated test user password to 'password123'\n";
} else {
    echo "Test user not found, will use another user\n";
    $testUser = App\Models\User::first();
    if ($testUser) {
        echo "Using user: {$testUser->name} ({$testUser->email})\n";
        // Update password to known value
        $testUser->password = bcrypt('password123');
        $testUser->save();
        echo "Updated user password to 'password123'\n";
    }
}

echo "\nTest user setup complete!\n";
