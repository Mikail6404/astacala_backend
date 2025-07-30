<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Hash;

echo "=== PASSWORD TEST ===\n";
$user = App\Models\User::where('email', 'test@example.com')->first();
echo "User ID: {$user->id}\n";
echo "Testing 'password123': " . (Hash::check('password123', $user->password) ? 'MATCH' : 'NO MATCH') . "\n";
echo "Testing 'password': " . (Hash::check('password', $user->password) ? 'MATCH' : 'NO MATCH') . "\n";
echo "Testing 'Password123!': " . (Hash::check('Password123!', $user->password) ? 'MATCH' : 'NO MATCH') . "\n";
echo "Testing 'TestPassword123!': " . (Hash::check('TestPassword123!', $user->password) ? 'MATCH' : 'NO MATCH') . "\n";
