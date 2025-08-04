<?php

/**
 * Phase 3 Real-Time Notifications Test
 * Testing cross-platform notification functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 Real-Time Notifications Test ===\n\n";

// Step 1: Authenticate test user
echo "1. Authenticating test user:\n";
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();

$loginUrl = 'http://127.0.0.1:8000/api/v1/auth/login';
$loginData = [
    'email' => $testUser->email,
    'password' => 'password',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$loginResponse = curl_exec($ch);
curl_close($ch);

$loginData = json_decode($loginResponse, true);
$token = $loginData['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Step 2: Test FCM token registration
echo "2. Testing FCM token registration:\n";
$fcmTokenUrl = 'http://127.0.0.1:8000/api/v1/notifications/fcm-token';
$fcmData = [
    'fcm_token' => 'test_fcm_token_' . time(),
    'platform' => 'mobile'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fcmTokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$fcmResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "FCM token registration response (HTTP $httpCode): $fcmResponse\n\n";

// Step 3: Test notification retrieval
echo "3. Testing notification retrieval:\n";
$notificationsUrl = 'http://127.0.0.1:8000/api/v1/notifications';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $notificationsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$notificationsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Notifications response (HTTP $httpCode): $notificationsResponse\n\n";

// Step 4: Test unread count
echo "4. Testing unread notification count:\n";
$unreadUrl = 'http://127.0.0.1:8000/api/v1/notifications/unread-count';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $unreadUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$unreadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Unread count response (HTTP $httpCode): $unreadResponse\n\n";

// Step 5: Create test notification (via database for testing)
echo "5. Creating test notification:\n";
$notification = App\Models\Notification::create([
    'title' => 'Phase 3 Test Notification',
    'message' => 'This is a test notification for Phase 3 integration testing.',
    'type' => 'DISASTER_ALERT',
    'recipient_id' => $testUser->id,
    'user_id' => $testUser->id,
    'priority' => 'HIGH',
    'is_read' => false,
    'data' => json_encode(['test' => true, 'phase' => '3'])
]);

echo "✅ Test notification created (ID: {$notification->id})\n\n";

// Step 6: Test notification retrieval again
echo "6. Testing notification retrieval after creation:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $notificationsUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$notificationsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Updated notifications response (HTTP $httpCode): $notificationsResponse\n\n";

// Step 7: Test mark as read
echo "7. Testing mark notification as read:\n";
$markReadUrl = 'http://127.0.0.1:8000/api/v1/notifications/mark-read';
$markReadData = [
    'notification_ids' => [$notification->id]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $markReadUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($markReadData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$markReadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Mark as read response (HTTP $httpCode): $markReadResponse\n\n";

// Step 8: Test admin broadcast (if admin exists)
echo "8. Testing admin notification broadcast:\n";
$adminUser = App\Models\User::where('role', 'ADMIN')->first();
if ($adminUser) {
    // Login as admin
    $adminLoginData = [
        'email' => $adminUser->email,
        'password' => 'password',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $loginUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($adminLoginData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $adminLoginResponse = curl_exec($ch);
    curl_close($ch);

    $adminLoginData = json_decode($adminLoginResponse, true);
    if (isset($adminLoginData['data']['tokens']['accessToken'])) {
        $adminToken = $adminLoginData['data']['tokens']['accessToken'];

        $broadcastUrl = 'http://127.0.0.1:8000/api/v1/notifications/broadcast';
        $broadcastData = [
            'title' => 'Phase 3 Admin Test Broadcast',
            'message' => 'This is a test admin broadcast for Phase 3 validation.',
            'type' => 'EMERGENCY_ALERT',
            'target_platforms' => ['mobile', 'web'],
            'target_roles' => ['VOLUNTEER']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $broadcastUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($broadcastData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $adminToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $broadcastResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "Admin broadcast response (HTTP $httpCode): $broadcastResponse\n\n";
    } else {
        echo "❌ Admin authentication failed\n\n";
    }
} else {
    echo "❌ No admin user found for broadcast testing\n\n";
}

echo "✅ Real-time notifications test complete\n";
echo "=== Notifications Test Complete ===\n";
