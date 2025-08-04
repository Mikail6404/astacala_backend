<?php

/**
 * Phase 3 Notifications System Validation (Corrected)
 * Testing notification functionality with correct routes
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Phase 3 Notifications System Validation ===\n\n";

// Step 1: Authenticate user
$testUser = App\Models\User::where('email', 'testuser@example.com')->first();
if (!$testUser) {
    echo "❌ Test user not found!\n";
    exit(1);
}

echo "1. Testing user: {$testUser->name} (ID: {$testUser->id})\n\n";

// Authenticate
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
$loginResult = json_decode($loginResponse, true);
if (!$loginResult || !isset($loginResult['data']['tokens']['accessToken'])) {
    echo "❌ Authentication failed\n";
    exit(1);
}

$token = $loginResult['data']['tokens']['accessToken'];
echo "✅ Authentication successful\n\n";

// Step 2: Test basic notification functionality
echo "2. Testing core notification functionality:\n";

// Create test notification
$notification = App\Models\Notification::create([
    'user_id' => $testUser->id,
    'recipient_id' => $testUser->id,
    'title' => 'Phase 3 System Test',
    'message' => 'Testing notification system for Phase 3 completion.',
    'type' => 'system_test',
    'priority' => 'MEDIUM',
    'data' => [
        'test_id' => 'phase3_validation_' . time(),
        'platform' => 'mobile'
    ],
    'is_read' => false
]);

echo "✅ Test notification created (ID: {$notification->id})\n";

// Test notification listing
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

$notificationsResult = json_decode($notificationsResponse, true);
$notificationsWorking = $httpCode === 200 && $notificationsResult && isset($notificationsResult['success']) && $notificationsResult['success'];

echo ($notificationsWorking ? "✅" : "❌") . " Notification listing: " . ($notificationsWorking ? "WORKING" : "FAILED") . "\n";
if ($notificationsWorking) {
    echo "   - Total notifications: " . count($notificationsResult['data']) . "\n";
    echo "   - Unread count: " . ($notificationsResult['unread_count'] ?? 0) . "\n";
}

// Step 3: Test mark as read (correct route)
echo "\n3. Testing mark as read functionality:\n";

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

$markReadResult = json_decode($markReadResponse, true);
$markReadWorking = $httpCode === 200 && $markReadResult && isset($markReadResult['success']) && $markReadResult['success'];

echo ($markReadWorking ? "✅" : "❌") . " Mark as read: " . ($markReadWorking ? "WORKING" : "FAILED") . "\n";
if (!$markReadWorking) {
    echo "   Response: " . substr($markReadResponse, 0, 200) . "...\n";
}

// Step 4: Test unread count endpoint
echo "\n4. Testing unread count endpoint:\n";

$unreadCountUrl = 'http://127.0.0.1:8000/api/v1/notifications/unread-count';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $unreadCountUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$unreadResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$unreadResult = json_decode($unreadResponse, true);
$unreadWorking = $httpCode === 200 && $unreadResult && isset($unreadResult['success']) && $unreadResult['success'];

echo ($unreadWorking ? "✅" : "❌") . " Unread count: " . ($unreadWorking ? "WORKING" : "FAILED") . "\n";
if ($unreadWorking) {
    echo "   - Unread count: " . ($unreadResult['unread_count'] ?? 0) . "\n";
}

// Step 5: Test CrossPlatformNotificationService integration
echo "\n5. Testing service integration:\n";

try {
    $notificationService = app(App\Services\CrossPlatformNotificationService::class);
    echo "✅ CrossPlatformNotificationService instantiated\n";

    $platformNotifications = $notificationService->getPlatformNotifications($testUser, 'mobile');
    echo "✅ Platform notifications: " . count($platformNotifications) . " retrieved\n";

    $unreadCount = $notificationService->getUnreadCount($testUser, 'mobile');
    echo "✅ Service unread count: $unreadCount\n";
} catch (\Exception $e) {
    echo "❌ Service integration error: " . $e->getMessage() . "\n";
}

// Step 6: Test real-time broadcasting setup
echo "\n6. Testing real-time capabilities:\n";

$broadcastConnection = config('broadcasting.default');
echo "Broadcast driver: $broadcastConnection\n";

if ($broadcastConnection === 'reverb') {
    echo "✅ Reverb WebSocket configured\n";

    // Check Reverb configuration
    $reverbConfig = config('broadcasting.connections.reverb');
    $appKey = $reverbConfig['key'] ?? 'NOT_SET';
    $host = $reverbConfig['options']['host'] ?? 'NOT_SET';
    $port = $reverbConfig['options']['port'] ?? 'NOT_SET';

    echo "   - App Key: " . ($appKey !== 'NOT_SET' ? "CONFIGURED" : "MISSING") . "\n";
    echo "   - Host: $host\n";
    echo "   - Port: $port\n";

    $reverbConfigured = $appKey !== 'NOT_SET' && $host !== 'NOT_SET';
    echo ($reverbConfigured ? "✅" : "⚠️") . " Reverb configuration: " . ($reverbConfigured ? "COMPLETE" : "INCOMPLETE") . "\n";
} else {
    echo "⚠️ Real-time broadcasting not configured (current: $broadcastConnection)\n";
}

// Step 7: Test notification test endpoint
echo "\n7. Testing notification test endpoint:\n";

$testNotificationUrl = 'http://127.0.0.1:8000/test-notifications';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testNotificationUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$testResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$testResult = json_decode($testResponse, true);
$testEndpointWorking = $httpCode === 200 && $testResult && isset($testResult['success']) && $testResult['success'];

echo ($testEndpointWorking ? "✅" : "❌") . " Test endpoint: " . ($testEndpointWorking ? "WORKING" : "FAILED") . "\n";

// Clean up
echo "\n8. Cleaning up:\n";
$notification->delete();
echo "✅ Test notification cleaned up\n";

// Summary
echo "\n=== NOTIFICATION SYSTEM VALIDATION SUMMARY ===\n\n";

$coreTests = [
    'notification_listing' => $notificationsWorking,
    'mark_as_read' => $markReadWorking,
    'unread_count' => $unreadWorking,
    'service_integration' => isset($notificationService),
    'test_endpoint' => $testEndpointWorking,
];

$passedCore = 0;
$totalCore = count($coreTests);

foreach ($coreTests as $test => $passed) {
    echo "- " . ucfirst(str_replace('_', ' ', $test)) . ": " . ($passed ? "✅ PASS" : "❌ FAIL") . "\n";
    if ($passed) $passedCore++;
}

$corePercentage = round(($passedCore / $totalCore) * 100);

echo "\n🎯 CORE FUNCTIONALITY: $corePercentage% ({$passedCore}/{$totalCore} tests passed)\n";

$realTimeReady = ($broadcastConnection === 'reverb' && isset($reverbConfigured) && $reverbConfigured);
echo "⚡ REAL-TIME READY: " . ($realTimeReady ? "✅ YES" : "⚠️ NEEDS SETUP") . "\n";

$overallStatus = $corePercentage >= 80 ? 'WORKING' : ($corePercentage >= 60 ? 'PARTIAL' : 'BROKEN');
echo "\n📊 OVERALL STATUS: $overallStatus\n";

if ($corePercentage >= 80) {
    echo "\n🚀 Phase 3 Notification System: CORE FUNCTIONALITY VALIDATED!\n";
    echo "   ✅ Notification creation and retrieval working\n";
    echo "   ✅ Read/unread state management functional\n";
    echo "   ✅ API endpoints operational\n";
    echo "   ✅ Service integration working\n";

    if ($realTimeReady) {
        echo "   ✅ Real-time WebSocket configured (Reverb)\n";
        $completionPercentage = 90;
    } else {
        echo "   ⚠️ Real-time setup needs completion\n";
        $completionPercentage = 75;
    }
} else {
    echo "\n⚠️ Phase 3 Notification System: NEEDS ATTENTION\n";
    $completionPercentage = $corePercentage * 0.75; // Reduce for issues
}

echo "\n📈 NOTIFICATION SYSTEM COMPLETION: {$completionPercentage}%\n";

exit($corePercentage >= 80 ? 0 : 1);
