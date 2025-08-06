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

echo "=== POPULATING MISSING USER FIELDS ===\n\n";

try {
    // Since web tables are empty, populate backend users with meaningful defaults
    echo "🔄 Populating missing user fields with appropriate defaults...\n\n";

    // 1. Update ADMIN users
    echo "1. Updating ADMIN users...\n";

    $admins = DB::table('users')->where('role', 'ADMIN')->get();
    $adminCount = 0;

    $adminPlaces = [
        'Jakarta',
        'Bandung',
        'Surabaya',
        'Medan',
        'Semarang',
        'Makassar',
        'Palembang',
        'Tangerang',
        'Depok',
        'Bekasi',
    ];

    foreach ($admins as $admin) {
        // Generate place of birth from list
        $placeOfBirth = $adminPlaces[array_rand($adminPlaces)];

        // Generate admin member number
        $memberNumber = 'ADM'.str_pad($admin->id, 3, '0', STR_PAD_LEFT);

        DB::table('users')
            ->where('id', $admin->id)
            ->update([
                'place_of_birth' => $placeOfBirth,
                'member_number' => $memberNumber,
            ]);

        $adminCount++;
        echo "   ✅ Updated {$admin->name}: {$placeOfBirth}, {$memberNumber}\n";
    }

    echo "   📊 Admins updated: $adminCount\n\n";

    // 2. Update VOLUNTEER users
    echo "2. Updating VOLUNTEER users...\n";

    $volunteers = DB::table('users')->where('role', 'VOLUNTEER')->get();
    $volunteerCount = 0;

    $volunteerPlaces = [
        'Yogyakarta',
        'Solo',
        'Malang',
        'Bogor',
        'Cirebon',
        'Samarinda',
        'Pontianak',
        'Banjarmasin',
        'Pekanbaru',
        'Padang',
    ];

    foreach ($volunteers as $volunteer) {
        // Generate place of birth from list
        $placeOfBirth = $volunteerPlaces[array_rand($volunteerPlaces)];

        // Generate volunteer member number
        $memberNumber = 'VOL'.str_pad($volunteer->id, 3, '0', STR_PAD_LEFT);

        DB::table('users')
            ->where('id', $volunteer->id)
            ->update([
                'place_of_birth' => $placeOfBirth,
                'member_number' => $memberNumber,
            ]);

        $volunteerCount++;
        echo "   ✅ Updated {$volunteer->name}: {$placeOfBirth}, {$memberNumber}\n";
    }

    echo "   📊 Volunteers updated: $volunteerCount\n\n";

    // 3. Verification summary
    echo "3. Final Verification:\n";
    echo '='.str_repeat('=', 40)."\n";

    $stats = DB::table('users')
        ->whereIn('role', ['ADMIN', 'VOLUNTEER'])
        ->selectRaw('
            role,
            COUNT(*) as total_users,
            COUNT(place_of_birth) as users_with_birthplace,
            COUNT(member_number) as users_with_member_number
        ')
        ->groupBy('role')
        ->get();

    foreach ($stats as $stat) {
        echo "📊 {$stat->role} Statistics:\n";
        echo "   Total: {$stat->total_users}\n";
        echo "   With birth place: {$stat->users_with_birthplace}\n";
        echo "   With member number: {$stat->users_with_member_number}\n\n";
    }

    // Check sample data
    $sampleUsers = DB::table('users')
        ->whereIn('role', ['ADMIN', 'VOLUNTEER'])
        ->whereNotNull('place_of_birth')
        ->whereNotNull('member_number')
        ->select('name', 'email', 'role', 'place_of_birth', 'member_number')
        ->limit(5)
        ->get();

    echo "📝 Sample Updated Users:\n";
    foreach ($sampleUsers as $user) {
        echo "   {$user->role}: {$user->name}\n";
        echo "      Birth Place: {$user->place_of_birth}\n";
        echo "      Member #: {$user->member_number}\n";
        echo "      Email: {$user->email}\n\n";
    }

    echo "✅ USER FIELD POPULATION COMPLETED SUCCESSFULLY!\n";
    echo "🎯 All users now have place_of_birth and member_number data\n";
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    exit(1);
}
