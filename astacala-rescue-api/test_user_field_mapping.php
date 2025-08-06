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

echo "=== TESTING BACKEND USER FIELD MAPPING ===\n\n";

// Test admin user data
$admin = DB::table('users')->where('role', 'ADMIN')->first();
if ($admin) {
    echo "Admin Sample Data:\n";
    echo "  ID: {$admin->id}\n";
    echo "  Name: {$admin->name}\n";
    echo "  Email: {$admin->email}\n";
    echo '  Place of Birth: '.($admin->place_of_birth ?? 'NULL')."\n";
    echo '  Member Number: '.($admin->member_number ?? 'NULL')."\n";
    echo "  Role: {$admin->role}\n\n";
}

// Test volunteer user data
$volunteer = DB::table('users')->where('role', 'VOLUNTEER')->first();
if ($volunteer) {
    echo "Volunteer Sample Data:\n";
    echo "  ID: {$volunteer->id}\n";
    echo "  Name: {$volunteer->name}\n";
    echo "  Email: {$volunteer->email}\n";
    echo '  Place of Birth: '.($volunteer->place_of_birth ?? 'NULL')."\n";
    echo '  Member Number: '.($volunteer->member_number ?? 'NULL')."\n";
    echo "  Role: {$volunteer->role}\n\n";
}

// Test the admin list endpoint data structure
echo "Testing Admin List Fields Selection:\n";
$adminFields = DB::table('users')
    ->whereIn('role', ['ADMIN', 'admin', 'super_admin', 'SUPER_ADMIN'])
    ->select(
        'id',
        'name',
        'email',
        'role',
        'is_active',
        'created_at',
        'last_login',
        'birth_date',
        'place_of_birth',
        'phone',
        'organization',
        'member_number'
    )
    ->first();

if ($adminFields) {
    echo '  Available fields: '.implode(', ', array_keys((array) $adminFields))."\n";
    echo '  place_of_birth: '.($adminFields->place_of_birth ?? 'NULL')."\n";
    echo '  member_number: '.($adminFields->member_number ?? 'NULL')."\n\n";
}

echo "✅ Field mapping test completed!\n";
