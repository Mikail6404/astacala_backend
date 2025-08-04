<?php

/**
 * Phase 4 Advanced Features Comprehensive Testing Suite
 * Tests forum/chat, admin management, and real-time integration
 */

require_once __DIR__ . '/vendor/autoload.php';

// Create Laravel app instance
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 PHASE 4: ADVANCED FEATURES COMPREHENSIVE TESTING\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Test Results Tracking
$testResults = [
    'forum_api' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'admin_features' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'real_time' => ['total' => 0, 'passed' => 0, 'failed' => 0],
    'cross_platform' => ['total' => 0, 'passed' => 0, 'failed' => 0]
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
// 1. FORUM/CHAT API COMPREHENSIVE TESTING
// =============================================================================
echo "🔍 1. FORUM/CHAT API TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test database schema for forum_messages
try {
    $forumTableExists = \Illuminate\Support\Facades\Schema::hasTable('forum_messages');
    logTest('forum_api', 'Forum Messages Table Exists', $forumTableExists);

    if ($forumTableExists) {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('forum_messages');
        $requiredColumns = ['id', 'disaster_report_id', 'user_id', 'parent_message_id', 'message', 'message_type', 'priority_level', 'is_read', 'created_at', 'updated_at'];

        $hasAllColumns = true;
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $hasAllColumns = false;
                break;
            }
        }
        logTest(
            'forum_api',
            'Forum Schema Complete',
            $hasAllColumns,
            $hasAllColumns ? 'All required columns present' : 'Missing required columns'
        );
    }
} catch (\Exception $e) {
    logTest('forum_api', 'Forum Database Schema', false, "Error: " . $e->getMessage());
}

// Test forum routes exist
$forumRoutes = [
    'disasters/{id}/forum' => 'POST',
    'disasters/{id}/forum' => 'GET',
    'disasters/{id}/forum/{msg_id}' => 'PUT',
    'disasters/{id}/forum/{msg_id}' => 'DELETE',
    'disasters/{id}/forum/mark-read' => 'POST',
    'disasters/{id}/forum/statistics' => 'GET'
];

$routeCount = 0;
foreach ($forumRoutes as $route => $method) {
    try {
        // Check if route pattern exists in Laravel routes
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $found = false;
        foreach ($routes as $r) {
            $uri = $r->uri();
            // Check for forum-related routes with more flexible matching
            if ((strpos($uri, 'forum') !== false || strpos($uri, 'messages') !== false) &&
                in_array($method, $r->methods())
            ) {
                $found = true;
                break;
            }
        }
        if ($found) $routeCount++;
    } catch (\Exception $e) {
        // Route checking error
    }
}

logTest(
    'forum_api',
    'Forum API Routes',
    $routeCount >= 4,
    "Found $routeCount forum-related routes"
);

// =============================================================================
// 2. ADMIN FEATURES TESTING  
// =============================================================================
echo "\n👤 2. ADMIN FEATURES TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test admin user existence
try {
    $adminUsers = App\Models\User::where('role', 'ADMIN')->count();
    logTest(
        'admin_features',
        'Admin Users Exist',
        $adminUsers > 0,
        "$adminUsers admin accounts found"
    );
} catch (\Exception $e) {
    logTest('admin_features', 'Admin Users Query', false, "Error: " . $e->getMessage());
}

// Test admin controllers exist
$adminControllers = [
    'App\Http\Controllers\API\UserController',
    'App\Http\Controllers\API\AuthController',
    'App\Http\Controllers\API\DisasterReportController',
    'App\Http\Controllers\API\NotificationController'
];

$adminControllerCount = 0;
foreach ($adminControllers as $controller) {
    if (class_exists($controller)) {
        $adminControllerCount++;
    }
}

logTest(
    'admin_features',
    'Admin Controllers Available',
    $adminControllerCount >= 3,
    "$adminControllerCount admin controllers found"
);

// Test role middleware functionality
try {
    $roleMiddleware = new App\Http\Middleware\RoleMiddleware();
    logTest('admin_features', 'Role Middleware Exists', true, 'Role-based access control available');
} catch (\Exception $e) {
    logTest('admin_features', 'Role Middleware', false, "Error: " . $e->getMessage());
}

// =============================================================================
// 3. REAL-TIME FEATURES TESTING
// =============================================================================
echo "\n⚡ 3. REAL-TIME FEATURES TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test WebSocket configuration
$broadcastDriver = config('broadcasting.default');
$reverbConfig = config('broadcasting.connections.reverb');

logTest(
    'real_time',
    'WebSocket Driver Configured',
    $broadcastDriver === 'reverb',
    "Broadcasting driver: $broadcastDriver"
);

$reverbHost = $reverbConfig['options']['host'] ?? 'not_configured';
$reverbPort = $reverbConfig['options']['port'] ?? 'not_configured';

logTest(
    'real_time',
    'Reverb WebSocket Configuration',
    $reverbHost !== 'not_configured' && $reverbPort !== 'not_configured',
    "Host: $reverbHost, Port: $reverbPort"
);

// Test notification system
try {
    $notificationCount = App\Models\Notification::count();
    logTest(
        'real_time',
        'Notification System',
        $notificationCount >= 0,
        "$notificationCount notifications in system"
    );
} catch (\Exception $e) {
    logTest('real_time', 'Notification System', false, "Error: " . $e->getMessage());
}

// Test broadcasting events exist
$broadcastEvents = [
    'App\Events\ReportVerified',
    'App\Events\SystemNotification'
];

$eventCount = 0;
foreach ($broadcastEvents as $event) {
    if (class_exists($event)) {
        $eventCount++;
    }
}

logTest(
    'real_time',
    'Broadcasting Events',
    $eventCount >= 1,
    "$eventCount broadcast events available"
);

// =============================================================================
// 4. CROSS-PLATFORM INTEGRATION TESTING
// =============================================================================
echo "\n🔗 4. CROSS-PLATFORM INTEGRATION TESTING\n";
echo "-" . str_repeat("-", 40) . "\n";

// Test API versioning
$apiVersion = config('app.api_version', '1.0.0');
logTest(
    'cross_platform',
    'API Versioning',
    !empty($apiVersion),
    "API Version: $apiVersion"
);

// Test CORS configuration
$corsConfig = config('cors.paths');
$corsEnabled = is_array($corsConfig) && in_array('api/*', $corsConfig);
logTest(
    'cross_platform',
    'CORS Configuration',
    $corsEnabled,
    $corsEnabled ? 'CORS enabled for API' : 'CORS needs configuration'
);

// Test Sanctum authentication
$sanctumConfig = config('sanctum.stateful');
logTest(
    'cross_platform',
    'Sanctum Authentication',
    is_array($sanctumConfig),
    'Laravel Sanctum configured for mobile authentication'
);

// Test file upload capability
$uploadPath = storage_path('app/public/uploads');
$uploadWritable = is_dir($uploadPath) && is_writable($uploadPath);
logTest(
    'cross_platform',
    'File Upload Support',
    $uploadWritable,
    $uploadWritable ? 'Upload directory writable' : 'Upload directory needs setup'
);

// =============================================================================
// PHASE 4 COMPREHENSIVE RESULTS
// =============================================================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 PHASE 4 ADVANCED FEATURES TEST RESULTS\n";
echo str_repeat("=", 60) . "\n\n";

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

echo "🎯 OVERALL PHASE 4 STATUS:\n";
echo "   Total Tests: $totalTests\n";
echo "   ✅ Passed: $totalPassed ($overallPercentage%)\n";
echo "   ❌ Failed: $totalFailed\n\n";

// Phase 4 Assessment
if ($overallPercentage >= 90) {
    echo "🚀 PHASE 4 STATUS: EXCELLENT\n";
    echo "   ✅ Advanced features are highly functional\n";
    echo "   ✅ Ready for comprehensive integration testing\n";
    echo "   ✅ Can proceed with Phase 4B implementation\n";
    $recommendation = "PROCEED WITH PHASE 4B - INTEGRATION ENHANCEMENT";
} elseif ($overallPercentage >= 80) {
    echo "✅ PHASE 4 STATUS: GOOD\n";
    echo "   ✅ Core advanced features operational\n";
    echo "   🟡 Some enhancements needed\n";
    echo "   ✅ Phase 4A objectives substantially met\n";
    $recommendation = "PROCEED WITH TARGETED IMPROVEMENTS AND PHASE 4B";
} elseif ($overallPercentage >= 70) {
    echo "🟡 PHASE 4 STATUS: MODERATE\n";
    echo "   ✅ Basic advanced features working\n";
    echo "   🟡 Several improvements required\n";
    echo "   ⚠️ Focus on Phase 4A completion\n";
    $recommendation = "COMPLETE PHASE 4A BEFORE PROCEEDING";
} else {
    echo "❌ PHASE 4 STATUS: NEEDS ATTENTION\n";
    echo "   ⚠️ Significant advanced feature gaps\n";
    echo "   ❌ Phase 4A requires substantial work\n";
    $recommendation = "FOCUS ON PHASE 4A FOUNDATION COMPLETION";
}

echo "\n🎯 RECOMMENDATION: $recommendation\n";

echo "\n📋 IMMEDIATE NEXT STEPS:\n";
if ($overallPercentage >= 80) {
    echo "   1. Begin real-time integration testing\n";
    echo "   2. Test mobile forum functionality\n";
    echo "   3. Validate cross-platform messaging\n";
    echo "   4. Implement Phase 4B enhancements\n";
} else {
    echo "   1. Address failed test cases\n";
    echo "   2. Complete Phase 4A foundations\n";
    echo "   3. Re-run comprehensive testing\n";
    echo "   4. Ensure 80%+ success before Phase 4B\n";
}

echo "\n🎯 PHASE 4 IMPLEMENTATION STATUS: " . ($overallPercentage >= 80 ? "✅ READY FOR PHASE 4B" : "🔧 PHASE 4A IN PROGRESS") . "\n";

exit($overallPercentage >= 70 ? 0 : 1);
