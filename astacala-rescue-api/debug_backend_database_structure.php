<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== BACKEND DATABASE STRUCTURE ANALYSIS ===\n\n";

// Check backend database connection
try {
    // Switch to backend database
    DB::setDefaultConnection('mysql');

    echo "🔍 BACKEND DATABASE STRUCTURE:\n\n";

    // 1. Check users table structure
    echo "1. Users Table Structure:\n";
    $columns = Schema::getColumnListing('users');
    foreach ($columns as $column) {
        echo "   - $column\n";
    }

    // 2. Sample user data to check what fields have values
    echo "\n2. Sample User Data Analysis:\n";
    $users = DB::table('users')->limit(3)->get();
    foreach ($users as $user) {
        echo "   User ID {$user->id}: {$user->name} ({$user->email})\n";
        echo '     - Phone: '.($user->phone ?? 'N/A')."\n";
        echo '     - Birth Date: '.($user->birth_date ?? 'N/A')."\n";
        echo '     - Organization: '.($user->organization ?? 'N/A')."\n";
        echo '     - Role: '.($user->role ?? 'N/A')."\n";
    }

    // 3. Check disaster_reports table structure
    echo "\n3. Disaster Reports Table Structure:\n";
    $reportColumns = Schema::getColumnListing('disaster_reports');
    foreach ($reportColumns as $column) {
        echo "   - $column\n";
    }

    // 4. Sample disaster report data
    echo "\n4. Sample Disaster Reports Data Analysis:\n";
    $reports = DB::table('disaster_reports')->limit(3)->get();
    foreach ($reports as $report) {
        echo "   Report ID {$report->id}: {$report->title}\n";
        echo '     - Personnel Count: '.($report->personnel_count ?? 'N/A')."\n";
        echo '     - Contact Phone: '.($report->contact_phone ?? 'N/A')."\n";
        echo "     - Coordinates: {$report->latitude}, {$report->longitude}\n";
        echo '     - Team Name: '.($report->team_name ?? 'N/A')."\n";
    }

    // 5. Check publications table structure
    echo "\n5. Publications Table Structure:\n";
    $pubColumns = Schema::getColumnListing('publications');
    foreach ($pubColumns as $column) {
        echo "   - $column\n";
    }

    // 6. Sample publications data
    echo "\n6. Sample Publications Data Analysis:\n";
    $publications = DB::table('publications')->limit(3)->get();
    foreach ($publications as $pub) {
        echo "   Publication ID {$pub->id}: {$pub->title}\n";
        echo '     - Author ID: '.($pub->author_id ?? 'N/A')."\n";
        echo '     - Status: '.($pub->status ?? 'N/A')."\n";
    }

    // 7. Check notifications table structure
    echo "\n7. Notifications Table Structure:\n";
    $notifColumns = Schema::getColumnListing('notifications');
    foreach ($notifColumns as $column) {
        echo "   - $column\n";
    }

    // 8. User count by role
    echo "\n8. User Count by Role:\n";
    $roleStats = DB::table('users')
        ->select('role', DB::raw('COUNT(*) as count'))
        ->groupBy('role')
        ->get();

    foreach ($roleStats as $stat) {
        echo "   {$stat->role}: {$stat->count} users\n";
    }
} catch (Exception $e) {
    echo '❌ Database error: '.$e->getMessage()."\n";
}

echo "\n=== BACKEND DATABASE ANALYSIS COMPLETE ===\n";
