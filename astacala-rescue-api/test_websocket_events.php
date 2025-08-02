<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Events\DisasterReportSubmitted;
use App\Events\ReportVerified;
use App\Events\AdminNotification;

// Mock report data for testing
$mockReport = (object) [
    'id' => 1,
    'title' => 'Test Disaster Report',
    'location_name' => 'Test Location',
    'severity_level' => 'high',
    'disaster_type' => 'earthquake',
    'created_at' => now(),
    'latitude' => -6.2088,
    'longitude' => 106.8456,
    'reported_by' => 1,
    'status' => 'verified',
    'verification_notes' => 'Report verified successfully',
    'verified_at' => now(),
    'reporter' => (object) ['name' => 'Test User'],
    'verifiedBy' => (object) ['name' => 'Admin User'],
];

echo "Testing WebSocket Broadcasting Events...\n\n";

try {
    // Test DisasterReportSubmitted event
    echo "1. Broadcasting DisasterReportSubmitted event...\n";
    event(new DisasterReportSubmitted($mockReport));
    echo "✅ DisasterReportSubmitted event dispatched successfully\n\n";

    // Test ReportVerified event  
    echo "2. Broadcasting ReportVerified event...\n";
    event(new ReportVerified($mockReport));
    echo "✅ ReportVerified event dispatched successfully\n\n";

    // Test AdminNotification event
    echo "3. Broadcasting AdminNotification event...\n";
    event(new AdminNotification(
        'success',
        'System Test',
        'WebSocket broadcasting system is working correctly',
        ['test' => true]
    ));
    echo "✅ AdminNotification event dispatched successfully\n\n";

    echo "🎉 All WebSocket events have been dispatched successfully!\n";
    echo "Check the Reverb server logs to see if the events are being processed.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
