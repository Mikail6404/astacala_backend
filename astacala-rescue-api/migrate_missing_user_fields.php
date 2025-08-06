<?php

/**
 * Data Migration Script: Populate Missing User Fields
 * 
 * This script migrates the missing field data from the web app's admins and penggunas
 * tables to the backend's unified users table.
 */

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MIGRATING MISSING USER FIELDS ===\n\n";

try {
    // Use Laravel's database connection since both apps share the same database
    echo "✅ Using shared Laravel database connection\n\n";

    // 1. Migrate admin data
    echo "1. Migrating admin data...\n";

    $admins = DB::table('admins')->get();

    $updatedAdmins = 0;
    foreach ($admins as $admin) {
        $email = $admin->username_akun_admin . '@admin.astacala.local';

        // Update the corresponding user in backend
        $updated = DB::table('users')
            ->where('email', $email)
            ->update([
                'place_of_birth' => $admin->tempat_lahir_admin,
                'member_number' => $admin->no_anggota,
            ]);

        if ($updated) {
            $updatedAdmins++;
            echo "   ✅ Updated admin: {$admin->nama_lengkap_admin}\n";
        } else {
            echo "   ⚠️  Admin not found in backend: {$admin->nama_lengkap_admin}\n";
        }
    }

    echo "   📊 Admins updated: $updatedAdmins\n\n";

    // 2. Migrate pengguna data
    echo "2. Migrating pengguna data...\n";

    $penggunas = DB::table('penggunas')->get();

    $updatedPenggunas = 0;
    foreach ($penggunas as $pengguna) {
        $email = $pengguna->username_akun_pengguna . '@web.local';

        // Update the corresponding user in backend
        $updated = DB::table('users')
            ->where('email', $email)
            ->update([
                'place_of_birth' => $pengguna->tempat_lahir_pengguna,
                'member_number' => 'VOL' . str_pad($pengguna->id, 3, '0', STR_PAD_LEFT), // Generate member number for volunteers
            ]);

        if ($updated) {
            $updatedPenggunas++;
            echo "   ✅ Updated volunteer: {$pengguna->nama_lengkap_pengguna}\n";
        } else {
            echo "   ⚠️  Volunteer not found in backend: {$pengguna->nama_lengkap_pengguna}\n";
        }
    }

    echo "   📊 Volunteers updated: $updatedPenggunas\n\n";

    // 3. Update disaster reports with missing fields
    echo "3. Updating disaster reports with contact info...\n";

    $reportsUpdated = DB::table('disaster_reports')
        ->join('users', 'disaster_reports.reported_by', '=', 'users.id')
        ->whereNotNull('disaster_reports.reported_by')
        ->update([
            'disaster_reports.reporter_phone' => DB::raw('users.phone'),
            'disaster_reports.reporter_username' => DB::raw('SUBSTRING_INDEX(users.email, "@", 1)'),
            'disaster_reports.coordinate_display' => DB::raw('CONCAT(disaster_reports.latitude, ", ", disaster_reports.longitude)')
        ]);

    echo "   📊 Reports updated: $reportsUpdated\n\n";

    // 4. Update publications with creator info
    echo "4. Updating publications with creator info...\n";

    $publicationsUpdated = DB::table('publications')
        ->join('users', 'publications.author_id', '=', 'users.id')
        ->whereNotNull('publications.author_id')
        ->update([
            'publications.created_by' => DB::raw('users.id'),
            'publications.creator_name' => DB::raw('users.name')
        ]);

    echo "   📊 Publications updated: $publicationsUpdated\n\n";

    // 5. Verification summary
    echo "5. Verification Summary:\n";
    echo "=" . str_repeat("=", 30) . "\n";

    // Check users with complete data
    $stats = DB::table('users')
        ->whereIn('role', ['ADMIN', 'VOLUNTEER'])
        ->selectRaw('
            COUNT(*) as total_users,
            COUNT(place_of_birth) as users_with_birthplace,
            COUNT(member_number) as users_with_member_number
        ')
        ->first();

    echo "📊 Backend Users Statistics:\n";
    echo "   Total users: {$stats->total_users}\n";
    echo "   Users with birth place: {$stats->users_with_birthplace}\n";
    echo "   Users with member number: {$stats->users_with_member_number}\n\n";

    // Check sample user data
    $sampleAdmin = DB::table('users')
        ->where('email', 'like', '%@admin.astacala.local')
        ->select('name', 'email', 'place_of_birth', 'member_number')
        ->first();

    if ($sampleAdmin) {
        echo "📝 Sample Admin Data:\n";
        echo "   Name: {$sampleAdmin->name}\n";
        echo "   Email: {$sampleAdmin->email}\n";
        echo "   Place of Birth: " . ($sampleAdmin->place_of_birth ?? 'N/A') . "\n";
        echo "   Member Number: " . ($sampleAdmin->member_number ?? 'N/A') . "\n\n";
    }

    echo "✅ DATA MIGRATION COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
