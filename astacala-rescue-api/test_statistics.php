<?php

require_once 'vendor/autoload.php';

// Create Laravel app instance
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test 1: Check users
echo "=== USERS IN DATABASE ===\n";
$users = App\Models\User::all(['id', 'name', 'email', 'role']);
foreach ($users as $user) {
    echo "ID: {$user->id}, Name: {$user->name}, Email: {$user->email}, Role: {$user->role}\n";
}

// Test 2: Check reports
echo "\n=== DISASTER REPORTS ===\n";
$reports = App\Models\DisasterReport::all(['id', 'title', 'status', 'severity_level']);
foreach ($reports as $report) {
    echo "ID: {$report->id}, Title: {$report->title}, Status: {$report->status}, Severity: {$report->severity_level}\n";
}

// Test 3: Test statistics calculation
echo "\n=== STATISTICS CALCULATION ===\n";
$activeReports = App\Models\DisasterReport::where('status', 'ACTIVE')->count();
$totalVolunteers = App\Models\User::where('role', 'VOLUNTEER')->where('is_active', true)->count();
$totalUsers = App\Models\User::count();

echo "Active Reports: {$activeReports}\n";
echo "Total Volunteers: {$totalVolunteers}\n";
echo "Total Users: {$totalUsers}\n";

echo "\n=== TEST COMPLETED ===\n";
