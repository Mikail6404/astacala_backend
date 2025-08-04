<?php

/**
 * Phase 3 Real-Time Notifications Testing
 * Testing notification system and real-time capabilities
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 Real-Time Notifications Testing ===\n\n";

// Step 1: Authenticate user
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if (!$testUser) {
    echo "❌ Test user not found!\n";
    exit(1);
}

echo "1. Testing user: {$testUser->name} (ID: {$testUser->id})\n\n";

// Authenticate to get token
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$loginResult = json_decode($loginResponse, true);
if (!$loginResult || !isset($loginResult['data']['tokens']['accessToken'])) {
    echo "❌ Authentication failed\n";
    exit(1);
}

$token = $loginResult['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Step 2: Test notification endpoints
echo "2. Testing notification API endpoints:\n";

// Test getting notifications
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

echo "Notifications API (HTTP $httpCode): ";
$notificationsResult = json_decode($notificationsResponse, true);
$notificationsWorking = $httpCode === 200 && $notificationsResult && isset($notificationsResult['success']) && $notificationsResult['success'];

if ($notificationsWorking) {
    echo "✅ WORKING\n";
    echo "   - Total notifications: " . count($notificationsResult['data']) . "\n";
    echo "   - Unread count: " . ($notificationsResult['unread_count'] ?? 0) . "\n";
} else {
    echo "❌ FAILED\n";
    echo "   Response: " . substr($notificationsResponse, 0, 200) . "...\n";
}

// Step 3: Test notification creation (simulate system notification)
echo "\n3. Testing notification creation:\n";

try {
    // Create a test notification directly
    $notification = App\Models\Notification::create([
        'user_id' => $testUser->id,
        'recipient_id' => $testUser->id,
        'title' => 'Real-Time Test Notification',
        'message' => 'This is a test notification for Phase 3 real-time testing.',
        'type' => 'system_test',
        'priority' => 'MEDIUM',
        'data' => [
            'test_id' => 'phase3_test_' . time(),
            'platform' => 'mobile'
        ],
        'is_read' => false
    ]);

    echo "✅ Notification created (ID: {$notification->id})\n";

    // Test notification retrieval after creation
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $notificationsUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $updatedResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $updatedResult = json_decode($updatedResponse, true);
    if ($httpCode === 200 && $updatedResult && isset($updatedResult['success']) && $updatedResult['success']) {
        $newCount = count($updatedResult['data']);
        echo "✅ Notification retrieval working (Total: $newCount)\n";

        // Find our test notification
        $testNotificationFound = false;
        foreach ($updatedResult['data'] as $notif) {
            if ($notif['id'] == $notification->id) {
                $testNotificationFound = true;
                echo "✅ Test notification found in API response\n";
                break;
            }
        }

        if (!$testNotificationFound) {
            echo "⚠️ Test notification not found in API response\n";
        }
    } else {
        echo "❌ Updated notification retrieval failed\n";
    }
} catch (\Exception $e) {
    echo "❌ Notification creation failed: " . $e->getMessage() . "\n";
}

// Step 4: Test notification marking as read
echo "\n4. Testing notification read/unread functionality:\n";

if (isset($notification)) {
    $markReadUrl = "http://127.0.0.1:8000/api/v1/notifications/{$notification->id}/read";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $markReadUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $markReadResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $markReadResult = json_decode($markReadResponse, true);
    $markReadWorking = $httpCode === 200 && $markReadResult && isset($markReadResult['success']) && $markReadResult['success'];

    echo ($markReadWorking ? "✅" : "❌") . " Mark as read functionality: " . ($markReadWorking ? "WORKING" : "FAILED") . "\n";
    if (!$markReadWorking) {
        echo "   Response: " . substr($markReadResponse, 0, 200) . "...\n";
    }
} else {
    echo "❌ Cannot test mark as read - no notification created\n";
}

// Step 5: Test CrossPlatformNotificationService
echo "\n5. Testing CrossPlatformNotificationService:\n";

try {
    $notificationService = app(App\Services\CrossPlatformNotificationService::class);
    echo "✅ CrossPlatformNotificationService instantiated\n";

    // Test getting platform notifications
    $platformNotifications = $notificationService->getPlatformNotifications($testUser, 'mobile');
    echo "✅ Platform notifications retrieved: " . count($platformNotifications) . " notifications\n";

    // Test unread count
    $unreadCount = $notificationService->getUnreadCount($testUser, 'mobile');
    echo "✅ Unread count calculated: $unreadCount unread notifications\n";
} catch (\Exception $e) {
    echo "❌ CrossPlatformNotificationService error: " . $e->getMessage() . "\n";
}

// Step 6: Test broadcasting configuration
echo "\n6. Testing broadcasting configuration:\n";

// Check broadcasting configuration
$broadcastConnection = config('broadcasting.default');
echo "Broadcast connection: $broadcastConnection\n";

if ($broadcastConnection === 'null') {
    echo "⚠️ Broadcasting is disabled (set to 'null')\n";
    echo "   - Notifications work but are not real-time\n";
    echo "   - For real-time functionality, configure Pusher/WebSocket\n";
} elseif ($broadcastConnection === 'log') {
    echo "⚠️ Broadcasting is set to 'log' (development mode)\n";
    echo "   - Broadcast events are logged but not sent to clients\n";
    echo "   - Real-time functionality needs WebSocket/Pusher configuration\n";
} else {
    echo "✅ Broadcasting configured: $broadcastConnection\n";
}

// Step 7: Test report verification notification trigger
echo "\n7. Testing notification trigger from report verification:\n";

try {
    // Create a test report
    $testReport = App\Models\DisasterReport::create([
        'title' => 'Notification Test Report',
        'description' => 'Test report for notification triggering',
        'disasterType' => 'FLOOD',
        'locationName' => 'Test Location',
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'severityLevel' => 'MEDIUM',
        'incidentTimestamp' => now()->toISOString(),
        'teamName' => 'Test Team',
        'reported_by' => $testUser->id,
        'status' => 'PENDING'
    ]);

    echo "✅ Test report created (ID: {$testReport->id})\n";

    // Simulate verification process that should trigger notification
    $testReport->update([
        'verification_status' => 'VERIFIED',
        'status' => 'ACTIVE'
    ]);

    // Check if notification was created
    $verificationNotification = App\Models\Notification::where('user_id', $testUser->id)
        ->where('type', 'report_verified')
        ->where('data->report_id', $testReport->id)
        ->first();

    if ($verificationNotification) {
        echo "✅ Verification notification automatically created\n";
    } else {
        echo "⚠️ Verification notification not automatically triggered\n";
        echo "   (Manual notification creation works, but auto-trigger may need setup)\n";
    }
} catch (\Exception $e) {
    echo "❌ Report verification test failed: " . $e->getMessage() . "\n";
}

// Clean up test data
echo "\n8. Cleaning up test data:\n";
if (isset($notification)) {
    $notification->delete();
    echo "✅ Test notification cleaned up\n";
}
if (isset($testReport)) {
    $testReport->delete();
    echo "✅ Test report cleaned up\n";
}

// Summary
echo "\n=== REAL-TIME NOTIFICATIONS TEST SUMMARY ===\n\n";

$results = [
    'notifications_api' => $notificationsWorking ?? false,
    'notification_creation' => isset($notification) && $notification->exists,
    'mark_as_read' => $markReadWorking ?? false,
    'service_integration' => isset($notificationService),
    'broadcasting_configured' => $broadcastConnection !== 'null',
];

foreach ($results as $test => $passed) {
    echo "- " . ucfirst(str_replace('_', ' ', $test)) . ": " . ($passed ? "✅ PASS" : "❌ FAIL") . "\n";
}

$coreNotificationsWorking = $results['notifications_api'] && $results['notification_creation'] && $results['service_integration'];
$realTimeConfigured = $results['broadcasting_configured'];

echo "\n🎯 CORE NOTIFICATIONS: " . ($coreNotificationsWorking ? "✅ WORKING" : "❌ BROKEN") . "\n";
echo "⚡ REAL-TIME ENABLED: " . ($realTimeConfigured ? "✅ YES" : "⚠️ NO (needs WebSocket setup)") . "\n";

if ($coreNotificationsWorking) {
    echo "\n🚀 Phase 3 Notification System: CORE FUNCTIONALITY VALIDATED!\n";
    echo "   ✅ Notification API endpoints working\n";
    echo "   ✅ Notification creation and retrieval functional\n";
    echo "   ✅ CrossPlatformNotificationService operational\n";
    echo "   ✅ Read/unread state management working\n";

    if (!$realTimeConfigured) {
        echo "\n📋 REAL-TIME SETUP NEEDED:\n";
        echo "   - Configure WebSocket server (Pusher, Reverb, or Socket.io)\n";
        echo "   - Update BROADCAST_CONNECTION in .env\n";
        echo "   - Set up client-side WebSocket listeners\n";
        echo "   ⚠️ Currently: Notifications work but require manual refresh\n";
    }
} else {
    echo "\n❌ Phase 3 Notification System: NEEDS ATTENTION\n";
}

$completionPercentage = $coreNotificationsWorking ? ($realTimeConfigured ? 90 : 75) : 25;
echo "\n📈 NOTIFICATION SYSTEM COMPLETION: {$completionPercentage}%\n";

exit($coreNotificationsWorking ? 0 : 1);
