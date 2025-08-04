<?php

/**
 * Generate authentication token for API testing
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== GENERATING API AUTHENTICATION TOKEN ===\n\n";

try {
    // Find the test user
    $user = App\Models\User::where('email', 'forum@test.com')->first();

    if (!$user) {
        echo "❌ Test user not found. Run seed_forum_test_data.php first\n";
        exit(1);
    }

    // Generate Sanctum token
    $token = $user->createToken('Forum Test Token')->plainTextToken;

    echo "✅ Token generated for user: {$user->name} ({$user->email})\n";
    echo "🔑 Token: {$token}\n\n";

    echo "📋 Usage Example:\n";
    echo "curl -H \"Authorization: Bearer {$token}\" \\\n";
    echo "     -H \"Accept: application/json\" \\\n";
    echo "     http://127.0.0.1:8000/api/v1/forum\n\n";

    // Test the token immediately
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 10
        ]
    ]);

    echo "🧪 Testing token with /api/v1/forum endpoint...\n";
    $response = file_get_contents('http://127.0.0.1:8000/api/v1/forum', false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        echo "✅ API Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ API call failed\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
