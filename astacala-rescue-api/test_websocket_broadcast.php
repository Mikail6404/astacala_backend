<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Events\DisasterReportSubmitted;
use App\Events\ReportVerified;
use App\Events\AdminNotification;

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== WebSocket Event Testing ===\n";

// Test 1: System Notification Event
echo "📢 Broadcasting system notification...\n";
try {
    broadcast(new AdminNotification(
        'System Alert',
        'This is a test system notification for WebSocket connectivity',
        'system'
    ));
    echo "✅ System notification broadcasted successfully\n";
} catch (Exception $e) {
    echo "❌ Error broadcasting system notification: " . $e->getMessage() . "\n";
}

sleep(2);

// Test 2: Report Verified Event
echo "\n✅ Broadcasting report verified event...\n";
try {
    broadcast(new ReportVerified(123, 1));
    echo "✅ Report verified event broadcasted successfully\n";
} catch (Exception $e) {
    echo "❌ Error broadcasting report verified: " . $e->getMessage() . "\n";
}

sleep(2);

// Test 3: Disaster Report Submitted Event
echo "\n📋 Broadcasting disaster report submitted...\n";
try {
    broadcast(new DisasterReportSubmitted([
        'id' => 456,
        'type' => 'Gempa Bumi',
        'location' => 'Test Location',
        'description' => 'Test disaster report for WebSocket testing',
        'user_id' => 1
    ]));
    echo "✅ Disaster report submitted event broadcasted successfully\n";
} catch (Exception $e) {
    echo "❌ Error broadcasting disaster report: " . $e->getMessage() . "\n";
}

echo "\n🎯 All WebSocket events have been triggered!\n";
echo "Check your Flutter app to see if the events were received.\n";
