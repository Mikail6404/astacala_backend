<?php

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Bootstrap Laravel database
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'astacala_rescue',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== UPDATING ADMIN PASSWORD FOR TESTING ===\n";

// Update admin password
$updated = DB::table('users')
    ->where('email', 'admin@web.test')
    ->update(['password' => password_hash('admin123', PASSWORD_DEFAULT)]);

if ($updated) {
    echo "✅ Password updated for admin@web.test\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";
} else {
    echo "❌ User not found or password not updated\n";
}

// Also check what username format is expected by the web app
$admin = DB::table('users')->where('email', 'admin@web.test')->first();
if ($admin) {
    echo "Admin User Details:\n";
    echo "  ID: {$admin->id}\n";
    echo "  Name: {$admin->name}\n";
    echo "  Email: {$admin->email}\n";
    echo "  Role: {$admin->role}\n";

    // The web app expects username, not email for login
    echo "\n🔍 Login info for web app:\n";
    echo "  Use username: admin (extracted from email prefix)\n";
    echo "  Use password: admin123\n";
}

echo "\n✅ Admin login setup completed!\n";
