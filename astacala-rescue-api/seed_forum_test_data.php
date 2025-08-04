<?php

/**
 * Phase 4 Forum System - Test Data Seeder
 * Creates sample data for testing forum functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PHASE 4: FORUM SYSTEM - TEST DATA SEEDER ===\n\n";

try {
    // Create or find test user
    $user = App\Models\User::firstOrCreate(
        ['email' => 'forum@test.com'],
        [
            'name' => 'Test Forum User',
            'password' => bcrypt('password123'),
            'role' => 'VOLUNTEER',
            'is_active' => true,
        ]
    );
    echo "✅ Test user ready: ID {$user->id}\n";

    // Create test disaster report
    $report = App\Models\DisasterReport::create([
        'title' => 'Test Disaster for Forum',
        'description' => 'This is a test disaster report for forum testing',
        'location_name' => 'Test Location',
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'disaster_type' => 'flood',
        'severity_level' => 'medium',
        'status' => 'ACTIVE',
        'reported_by' => $user->id,
        'incident_timestamp' => now(),
    ]);
    echo "✅ Test disaster report created: ID {$report->id}\n";

    // Create test forum messages
    $message1 = App\Models\ForumMessage::create([
        'disaster_report_id' => $report->id,
        'user_id' => $user->id,
        'message' => 'This is the first test message in the forum. Testing basic functionality.',
        'message_type' => 'text',
        'priority_level' => 'normal',
        'is_read' => false,
    ]);
    echo "✅ Forum message 1 created: ID {$message1->id}\n";

    $message2 = App\Models\ForumMessage::create([
        'disaster_report_id' => $report->id,
        'user_id' => $user->id,
        'message' => 'This is an emergency message for testing high priority notifications!',
        'message_type' => 'emergency',
        'priority_level' => 'emergency',
        'is_read' => false,
    ]);
    echo "✅ Forum message 2 created: ID {$message2->id} (EMERGENCY)\n";

    $reply = App\Models\ForumMessage::create([
        'disaster_report_id' => $report->id,
        'user_id' => $user->id,
        'parent_message_id' => $message1->id,
        'message' => 'This is a reply to the first message. Testing threading functionality.',
        'message_type' => 'text',
        'priority_level' => 'normal',
        'is_read' => false,
    ]);
    echo "✅ Forum reply created: ID {$reply->id} (Reply to message {$message1->id})\n";

    // Create or find admin user
    $admin = App\Models\User::firstOrCreate(
        ['email' => 'admin@test.com'],
        [
            'name' => 'Test Admin',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]
    );
    echo "✅ Test admin ready: ID {$admin->id}\n";

    $adminMessage = App\Models\ForumMessage::create([
        'disaster_report_id' => $report->id,
        'user_id' => $admin->id,
        'message' => 'Admin response: We have received your report and are coordinating response efforts.',
        'message_type' => 'update',
        'priority_level' => 'high',
        'is_read' => false,
    ]);
    echo "✅ Admin message created: ID {$adminMessage->id}\n";

    echo "\n📊 Forum Test Data Summary:\n";
    echo "- Users: " . App\Models\User::count() . "\n";
    echo "- Disaster Reports: " . App\Models\DisasterReport::count() . "\n";
    echo "- Forum Messages: " . App\Models\ForumMessage::count() . "\n";
    echo "- Replies: " . App\Models\ForumMessage::whereNotNull('parent_message_id')->count() . "\n";

    echo "\n🔑 Test Credentials:\n";
    echo "User: forum@test.com / password123\n";
    echo "Admin: admin@test.com / password123\n";

    echo "\n🎯 Forum Integration Ready for Testing!\n";
} catch (Exception $e) {
    echo "❌ Error creating test data: " . $e->getMessage() . "\n";
}
