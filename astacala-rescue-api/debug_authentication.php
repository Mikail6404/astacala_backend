<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Authentication Debug Script ===\n";

// Check existing users
echo "\n1. Checking existing users:\n";
$users = User::take(5)->get(['id', 'name', 'email', 'created_at']);
foreach ($users as $user) {
    echo "  - ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
}

// Create a test user for authentication
echo "\n2. Creating test user for authentication:\n";
$testEmail = 'auth_test@example.com';

$existingUser = User::where('email', $testEmail)->first();
if ($existingUser) {
    echo "  Test user already exists: {$existingUser->email}\n";
    $testUser = $existingUser;
} else {
    $testUser = User::create([
        'name' => 'Auth Test User',
        'email' => $testEmail,
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "  Created test user: {$testUser->email}\n";
}

// Test token generation
echo "\n3. Testing token generation:\n";
try {
    $token = $testUser->createToken('test-token')->plainTextToken;
    echo "  ✅ Token generated successfully: " . substr($token, 0, 20) . "...\n";

    // Test token validation
    echo "\n4. Testing token validation:\n";
    $parts = explode('|', $token);
    if (count($parts) === 2) {
        $tokenId = $parts[0];
        $tokenHash = $parts[1];
        echo "  Token ID: {$tokenId}\n";
        echo "  Token Hash: " . substr($tokenHash, 0, 20) . "...\n";

        // Check if token exists in database
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);
        if ($personalAccessToken) {
            echo "  ✅ Token exists in database\n";
            echo "  Token name: {$personalAccessToken->name}\n";
            echo "  Token abilities: " . json_encode($personalAccessToken->abilities) . "\n";
        } else {
            echo "  ❌ Token not found in database\n";
        }
    }
} catch (Exception $e) {
    echo "  ❌ Token generation failed: " . $e->getMessage() . "\n";
}

// Test authentication endpoints
echo "\n5. Testing authentication configuration:\n";

// Check Sanctum configuration
$sanctumConfig = config('sanctum');
echo "  Sanctum stateful domains: " . json_encode($sanctumConfig['stateful'] ?? []) . "\n";
echo "  Sanctum guard: " . json_encode($sanctumConfig['guard'] ?? []) . "\n";

// Check middleware
echo "\n6. Authentication middleware test:\n";
try {
    $middleware = app()->make('Illuminate\Contracts\Http\Kernel')->getMiddlewareGroups();
    echo "  API middleware: " . json_encode($middleware['api'] ?? []) . "\n";
} catch (Exception $e) {
    echo "  ❌ Could not retrieve middleware: " . $e->getMessage() . "\n";
}

echo "\n=== Test completed ===\n";
echo "\nTest credentials for API testing:\n";
echo "Email: {$testEmail}\n";
echo "Password: password123\n";
echo "Token: {$token}\n";
