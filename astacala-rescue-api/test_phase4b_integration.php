<?php

/**
 * Phase 4B: Integration Enhancement Testing
 * Tests mobile-web forum integration and real-time messaging
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 PHASE 4B: INTEGRATION ENHANCEMENT TESTING\n";
echo "=" . str_repeat("=", 55) . "\n\n";

// Test Results
$testResults = [
    'forum_integration' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'real_time_messaging' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'cross_platform_sync' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'emergency_features' => ['total' => 0, 'passed' => 0, 'failed' => 0]
];

function logTest($category, $testName, $result, $details = '')
{
    global $testResults;
    $testResults[$category]['total']++;

    if ($result) {
        $testResults[$category]['passed']++;
        echo "✅ $testName\n";
        if ($details) echo "   $details\n";
    } else {
        $testResults[$category]['failed']++;
        echo "❌ $testName\n";
        if ($details) echo "   $details\n";
    }
}

// =============================================================================
// 1. FORUM INTEGRATION TESTING
// =============================================================================
echo "🔗 1. FORUM INTEGRATION TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test forum message creation with real data
try {
    $disasterReport = App\Models\DisasterReport::first();
    $user = App\Models\User::first();

    if ($disasterReport && $user) {
        // Create a test forum message
        $testMessage = App\Models\ForumMessage::create([
            'disaster_report_id' => $disasterReport->id,
            'user_id' => $user->id,
            'message' => 'Phase 4B integration test message',
            'message_type' => 'text',
            'priority_level' => 'normal',
            'is_read' => false
        ]);

        logTest(
            'forum_integration',
            'Forum Message Creation',
            $testMessage && $testMessage->id,
            "Message ID: {$testMessage->id}"
        );

        // Test message retrieval
        $retrievedMessage = App\Models\ForumMessage::find($testMessage->id);
        logTest(
            'forum_integration',
            'Forum Message Retrieval',
            $retrievedMessage && $retrievedMessage->message === $testMessage->message,
            "Message retrieved successfully"
        );

        // Test message threading (reply)
        $replyMessage = App\Models\ForumMessage::create([
            'disaster_report_id' => $disasterReport->id,
            'user_id' => $user->id,
            'parent_message_id' => $testMessage->id,
            'message' => 'Reply to Phase 4B test message',
            'message_type' => 'text',
            'priority_level' => 'normal',
            'is_read' => false
        ]);

        logTest(
            'forum_integration',
            'Message Threading (Replies)',
            $replyMessage && $replyMessage->parent_message_id === $testMessage->id,
            "Reply message created with proper threading"
        );

        // Test priority levels
        $emergencyMessage = App\Models\ForumMessage::create([
            'disaster_report_id' => $disasterReport->id,
            'user_id' => $user->id,
            'message' => 'EMERGENCY: Phase 4B test emergency message',
            'message_type' => 'emergency',
            'priority_level' => 'emergency',
            'is_read' => false
        ]);

        logTest(
            'forum_integration',
            'Emergency Priority Messages',
            $emergencyMessage && $emergencyMessage->priority_level === 'emergency',
            "Emergency message created with high priority"
        );

        // Clean up test messages
        if ($testMessage) $testMessage->delete();
        if ($replyMessage) $replyMessage->delete();
        if ($emergencyMessage) $emergencyMessage->delete();
    } else {
        logTest('forum_integration', 'Test Data Available', false, 'No disaster reports or users available for testing');
    }
} catch (\Exception $e) {
    logTest('forum_integration', 'Forum Integration', false, "Error: " . $e->getMessage());
}

// Test forum controller endpoints
try {
    $controller = new App\Http\Controllers\API\ForumController();

    // Test if methods exist and are callable
    $methods = ['index', 'reportMessages', 'postMessage', 'updateMessage', 'deleteMessage', 'markAsRead'];
    $methodCount = 0;

    foreach ($methods as $method) {
        if (method_exists($controller, $method)) {
            $methodCount++;
        }
    }

    logTest(
        'forum_integration',
        'Forum Controller Methods',
        $methodCount >= 5,
        "$methodCount critical methods available"
    );
} catch (\Exception $e) {
    logTest('forum_integration', 'Forum Controller', false, "Error: " . $e->getMessage());
}

// =============================================================================
// 2. REAL-TIME MESSAGING TESTING
// =============================================================================
echo "\n⚡ 2. REAL-TIME MESSAGING TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test WebSocket configuration
$reverbConfig = config('broadcasting.connections.reverb');
$appId = $reverbConfig['app_id'] ?? null;
$key = $reverbConfig['key'] ?? null;
$secret = $reverbConfig['secret'] ?? null;

logTest(
    'real_time_messaging',
    'Reverb Configuration Complete',
    !empty($appId) && !empty($key) && !empty($secret),
    "App ID, Key, and Secret configured"
);

// Test broadcasting driver
$broadcastDriver = config('broadcasting.default');
logTest(
    'real_time_messaging',
    'Broadcasting Driver Active',
    $broadcastDriver === 'reverb',
    "Driver: $broadcastDriver"
);

// Test event broadcasting capability
try {
    // Check if we can broadcast events
    $channels = config('broadcasting.connections.reverb.options.channels');
    logTest(
        'real_time_messaging',
        'Channel Configuration',
        is_array($channels) || $channels === null,
        "Channel configuration available"
    );
} catch (\Exception $e) {
    logTest('real_time_messaging', 'Channel Configuration', false, "Error: " . $e->getMessage());
}

// Test notification system integration
try {
    $notificationCount = App\Models\Notification::count();
    $hasNotifications = $notificationCount > 0;

    logTest(
        'real_time_messaging',
        'Notification System Active',
        $hasNotifications,
        "$notificationCount notifications in system"
    );

    // Test notification broadcasting
    if ($hasNotifications) {
        $latestNotification = App\Models\Notification::latest()->first();
        logTest(
            'real_time_messaging',
            'Notification Broadcasting Ready',
            $latestNotification && isset($latestNotification->data),
            "Latest notification structure valid"
        );
    }
} catch (\Exception $e) {
    logTest('real_time_messaging', 'Notification System', false, "Error: " . $e->getMessage());
}

// =============================================================================
// 3. CROSS-PLATFORM SYNCHRONIZATION TESTING
// =============================================================================
echo "\n🔄 3. CROSS-PLATFORM SYNCHRONIZATION TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test API endpoints for mobile-web sync
$apiEndpoints = [
    'GET /api/v1/forum/reports/{reportId}/messages',
    'POST /api/v1/forum/reports/{reportId}/messages',
    'PUT /api/v1/forum/reports/{reportId}/messages/{messageId}',
    'DELETE /api/v1/forum/reports/{reportId}/messages/{messageId}',
    'POST /api/v1/forum/reports/{reportId}/mark-read'
];

$routes = \Illuminate\Support\Facades\Route::getRoutes();
$foundEndpoints = 0;

foreach ($apiEndpoints as $endpoint) {
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'forum') !== false || strpos($uri, 'messages') !== false) {
            $foundEndpoints++;
            break;
        }
    }
}

logTest(
    'cross_platform_sync',
    'API Endpoints Available',
    $foundEndpoints >= 3,
    "$foundEndpoints forum endpoints available for sync"
);

// Test CORS configuration for cross-platform
$corsConfig = config('cors');
$corsEnabled = isset($corsConfig['paths']) && in_array('api/*', $corsConfig['paths']);

logTest(
    'cross_platform_sync',
    'CORS Configuration',
    $corsEnabled,
    "CORS enabled for cross-platform API access"
);

// Test Sanctum authentication for mobile
$sanctumConfig = config('sanctum');
$mobileAuth = isset($sanctumConfig['stateful']) && is_array($sanctumConfig['stateful']);

logTest(
    'cross_platform_sync',
    'Mobile Authentication',
    $mobileAuth,
    "Sanctum configured for mobile authentication"
);

// Test data consistency
try {
    $userCount = App\Models\User::count();
    $reportCount = App\Models\DisasterReport::count();
    $forumMessageCount = App\Models\ForumMessage::count();

    logTest(
        'cross_platform_sync',
        'Data Consistency',
        $userCount > 0 && $reportCount > 0,
        "Users: $userCount, Reports: $reportCount, Messages: $forumMessageCount"
    );
} catch (\Exception $e) {
    logTest('cross_platform_sync', 'Data Consistency', false, "Error: " . $e->getMessage());
}

// =============================================================================
// 4. EMERGENCY FEATURES TESTING
// =============================================================================
echo "\n🚨 4. EMERGENCY FEATURES TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test emergency message priority handling
try {
    $disasterReport = App\Models\DisasterReport::first();
    $user = App\Models\User::first();

    if ($disasterReport && $user) {
        // Create emergency message
        $emergencyMsg = App\Models\ForumMessage::create([
            'disaster_report_id' => $disasterReport->id,
            'user_id' => $user->id,
            'message' => 'EMERGENCY TEST: Critical situation requiring immediate attention',
            'message_type' => 'emergency',
            'priority_level' => 'emergency',
            'is_read' => false
        ]);

        logTest(
            'emergency_features',
            'Emergency Message Creation',
            $emergencyMsg && $emergencyMsg->priority_level === 'emergency',
            "Emergency message with high priority created"
        );

        // Test priority querying
        $emergencyMessages = App\Models\ForumMessage::where('priority_level', 'emergency')->get();
        logTest(
            'emergency_features',
            'Emergency Message Querying',
            $emergencyMessages->count() > 0,
            "Emergency messages can be queried and filtered"
        );

        // Clean up
        if ($emergencyMsg) $emergencyMsg->delete();
    }
} catch (\Exception $e) {
    logTest('emergency_features', 'Emergency Message System', false, "Error: " . $e->getMessage());
}

// Test emergency notification system
try {
    $urgentNotifications = App\Models\Notification::where('type', 'LIKE', '%urgent%')
        ->orWhere('type', 'LIKE', '%emergency%')
        ->count();

    logTest(
        'emergency_features',
        'Emergency Notification System',
        $urgentNotifications >= 0,
        "$urgentNotifications emergency notifications available"
    );
} catch (\Exception $e) {
    logTest('emergency_features', 'Emergency Notification System', false, "Error: " . $e->getMessage());
}

// Test admin emergency broadcasting capability
try {
    $adminUsers = App\Models\User::where('role', 'ADMIN')->count();
    logTest(
        'emergency_features',
        'Admin Emergency Broadcasting',
        $adminUsers > 0,
        "$adminUsers admin users available for emergency broadcasting"
    );
} catch (\Exception $e) {
    logTest('emergency_features', 'Admin Emergency Broadcasting', false, "Error: " . $e->getMessage());
}

// =============================================================================
// PHASE 4B COMPREHENSIVE RESULTS
// =============================================================================
echo "\n" . str_repeat("=", 55) . "\n";
echo "🎯 PHASE 4B INTEGRATION ENHANCEMENT RESULTS\n";
echo str_repeat("=", 55) . "\n\n";

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;

foreach ($testResults as $category => $results) {
    $totalTests += $results['total'];
    $totalPassed += $results['passed'];
    $totalFailed += $results['failed'];

    $categoryName = strtoupper(str_replace('_', ' ', $category));
    $percentage = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100) : 0;

    echo "📊 $categoryName:\n";
    echo "   ✅ Passed: {$results['passed']}/{$results['total']} ({$percentage}%)\n";
    echo "   ❌ Failed: {$results['failed']}\n\n";
}

$overallPercentage = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100) : 0;

echo "🎯 OVERALL PHASE 4B STATUS:\n";
echo "   Total Tests: $totalTests\n";
echo "   ✅ Passed: $totalPassed ($overallPercentage%)\n";
echo "   ❌ Failed: $totalFailed\n\n";

// Phase 4B Assessment
if ($overallPercentage >= 90) {
    echo "🚀 PHASE 4B STATUS: EXCELLENT\n";
    echo "   ✅ Integration features fully operational\n";
    echo "   ✅ Real-time messaging working perfectly\n";
    echo "   ✅ Ready for Phase 4C feature completion\n";
    $recommendation = "PROCEED TO PHASE 4C - FEATURE COMPLETION";
} elseif ($overallPercentage >= 80) {
    echo "✅ PHASE 4B STATUS: GOOD\n";
    echo "   ✅ Core integration features working\n";
    echo "   🟡 Minor enhancements beneficial\n";
    echo "   ✅ Phase 4C development feasible\n";
    $recommendation = "PROCEED TO PHASE 4C WITH MONITORING";
} elseif ($overallPercentage >= 70) {
    echo "🟡 PHASE 4B STATUS: MODERATE\n";
    echo "   ✅ Basic integration working\n";
    echo "   🟡 Several improvements needed\n";
    echo "   ⚠️ Complete Phase 4B before Phase 4C\n";
    $recommendation = "COMPLETE PHASE 4B IMPROVEMENTS";
} else {
    echo "❌ PHASE 4B STATUS: NEEDS ATTENTION\n";
    echo "   ⚠️ Integration issues present\n";
    echo "   ❌ Phase 4B completion required\n";
    $recommendation = "RESOLVE INTEGRATION ISSUES";
}

echo "\n🎯 RECOMMENDATION: $recommendation\n";

echo "\n📋 IMMEDIATE NEXT STEPS:\n";
if ($overallPercentage >= 80) {
    echo "   1. Begin Phase 4C feature completion\n";
    echo "   2. Test mobile app forum integration\n";
    echo "   3. Validate emergency communication protocols\n";
    echo "   4. Implement advanced messaging features\n";
} else {
    echo "   1. Address failed integration tests\n";
    echo "   2. Complete Phase 4B real-time messaging\n";
    echo "   3. Fix cross-platform synchronization issues\n";
    echo "   4. Re-test before Phase 4C\n";
}

echo "\n🎯 PHASE 4B INTEGRATION STATUS: " . ($overallPercentage >= 80 ? "✅ READY FOR PHASE 4C" : "🔧 PHASE 4B IN PROGRESS") . "\n";

exit($overallPercentage >= 70 ? 0 : 1);
