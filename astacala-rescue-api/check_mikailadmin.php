<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING MIKAILADMIN USER ===\n\n";

try {
    // Check for mikailadmin variations
    echo "1. Checking for 'mikailadmin' as email...\n";
    $user1 = DB::table('users')->where('email', 'mikailadmin')->first();
    if ($user1) {
        echo '   ✅ Found: '.json_encode($user1)."\n";
    } else {
        echo "   ❌ Not found\n";
    }

    echo "\n2. Checking for 'mikailadmin' as name...\n";
    $user2 = DB::table('users')->where('name', 'mikailadmin')->first();
    if ($user2) {
        echo '   ✅ Found: '.json_encode($user2)."\n";
    } else {
        echo "   ❌ Not found\n";
    }

    echo "\n3. Checking for 'mikailadmin@admin.astacala.local'...\n";
    $user3 = DB::table('users')->where('email', 'mikailadmin@admin.astacala.local')->first();
    if ($user3) {
        echo '   ✅ Found: '.json_encode($user3)."\n";
    } else {
        echo "   ❌ Not found\n";
    }

    echo "\n4. Searching all users containing 'mikail'...\n";
    $mikailUsers = DB::table('users')->where('email', 'like', '%mikail%')->orWhere('name', 'like', '%mikail%')->get();
    foreach ($mikailUsers as $user) {
        echo "   - Email: {$user->email}, Name: {$user->name}, Role: ".($user->role ?? 'N/A')."\n";
    }

    echo "\n5. Testing web app registration endpoint...\n";
    // Check if web app has its own user registration

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
}

echo "\n=== SEARCH COMPLETE ===\n";
