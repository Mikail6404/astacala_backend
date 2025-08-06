<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ASTACALA RESCUE BACKEND API DATABASE INVESTIGATION ===\n\n";

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    $connection = DB::connection();
    $pdo = $connection->getPdo();
    echo "✅ Database connection successful!\n";
    echo '   Database name: '.$connection->getDatabaseName()."\n\n";

    // List all tables
    echo "2. Listing all tables in database...\n";
    $tables = DB::select('SHOW TABLES');
    $tableNames = [];
    foreach ($tables as $table) {
        $tableName = array_values((array) $table)[0];
        $tableNames[] = $tableName;
        echo "   - $tableName\n";
    }
    echo "\n";

    // Check users table specifically
    echo "3. Checking users table structure...\n";
    if (in_array('users', $tableNames)) {
        $users = DB::table('users')->get();
        echo '   Users found: '.$users->count()."\n";
        if ($users->count() > 0) {
            echo "   Sample user data:\n";
            foreach ($users->take(5) as $user) {
                echo "     - ID: {$user->id}, Email: {$user->email}, Name: ".($user->name ?? 'N/A').', Role: '.($user->role ?? 'N/A')."\n";
            }
        }

        // Check for admin users
        $adminUsers = DB::table('users')->where('role', 'admin')->get();
        echo '   Admin users found: '.$adminUsers->count()."\n";
        if ($adminUsers->count() > 0) {
            foreach ($adminUsers as $admin) {
                echo "     - Admin: {$admin->email} ({$admin->name})\n";
            }
        }

        // Check if mikailadmin user exists
        $mikailadmin = DB::table('users')
            ->where('email', 'mikailadmin')
            ->orWhere('name', 'mikailadmin')
            ->orWhere('username', 'mikailadmin')
            ->first();
        if ($mikailadmin) {
            echo '   ✅ mikailadmin user found: '.json_encode($mikailadmin)."\n";
        } else {
            echo "   ❌ mikailadmin user NOT found\n";
        }
    } else {
        echo "   ❌ users table does not exist!\n";
    }
    echo "\n";

    // Check disaster_reports table
    echo "4. Checking disaster_reports table...\n";
    if (in_array('disaster_reports', $tableNames)) {
        $reports = DB::table('disaster_reports')->get();
        echo '   Disaster reports found: '.$reports->count()."\n";
        if ($reports->count() > 0) {
            echo "   Sample report data:\n";
            foreach ($reports->take(3) as $report) {
                echo "     - ID: {$report->id}, Title: ".($report->title ?? 'N/A').', Status: '.($report->status ?? 'N/A')."\n";
            }
        }
    } else {
        echo "   ❌ disaster_reports table does not exist!\n";
    }
    echo "\n";

    // Check publications table
    echo "5. Checking publications table...\n";
    if (in_array('publications', $tableNames)) {
        $publications = DB::table('publications')->get();
        echo '   Publications found: '.$publications->count()."\n";
        if ($publications->count() > 0) {
            echo "   Sample publication data:\n";
            foreach ($publications->take(3) as $pub) {
                echo "     - ID: {$pub->id}, Title: ".($pub->title ?? 'N/A').', Type: '.($pub->type ?? 'N/A')."\n";
            }
        }
    } else {
        echo "   ❌ publications table does not exist!\n";
    }
} catch (Exception $e) {
    echo '❌ Database error: '.$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
}

echo "\n=== BACKEND API INVESTIGATION COMPLETE ===\n";
