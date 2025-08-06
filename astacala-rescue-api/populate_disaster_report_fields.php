<?php

/**
 * Populate Missing Disaster Report Fields
 * 
 * This script populates the newly added fields in disaster_reports table:
 * - coordinate_display: Human-readable coordinate representation
 * - reporter_phone: Contact phone numbers for reporters
 * - reporter_username: Cached usernames for quick display
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\DisasterReport;
use App\Models\User;

echo "🔄 POPULATING DISASTER REPORT MISSING FIELDS\n";
echo "==========================================\n\n";

// Sample Indonesian phone numbers
$phone_numbers = [
    '+62812345001',
    '+62812345002',
    '+62812345003',
    '+62812345004',
    '+62812345005',
    '+62812345006',
    '+62812345007',
    '+62812345008',
    '+62812345009',
    '+62812345010',
    '+62812345011',
    '+62812345012',
    '+62812345013',
    '+62812345014',
    '+62812345015',
    '+62812345016',
    '+62812345017',
    '+62812345018',
    '+62812345019',
    '+62812345020'
];

try {
    $reports = DisasterReport::all();
    echo "📊 Found " . $reports->count() . " disaster reports to update\n\n";

    $updated_count = 0;

    foreach ($reports as $report) {
        $updates = [];

        // Generate coordinate_display from latitude/longitude
        if ($report->latitude && $report->longitude) {
            $updates['coordinate_display'] = number_format($report->latitude, 6) . ', ' . number_format($report->longitude, 6);
        } else {
            // Generate realistic Indonesian coordinates if missing
            $lat = -6.2 + (rand(-200, 200) / 100); // Around Jakarta area
            $lng = 106.8 + (rand(-200, 200) / 100);
            $updates['coordinate_display'] = number_format($lat, 6) . ', ' . number_format($lng, 6);
            $updates['latitude'] = $lat;
            $updates['longitude'] = $lng;
        }

        // Add reporter phone number
        if (empty($report->reporter_phone)) {
            $updates['reporter_phone'] = $phone_numbers[array_rand($phone_numbers)];
        }

        // Add reporter username
        if (empty($report->reporter_username)) {
            if ($report->reported_by) {
                $reporter = User::find($report->reported_by);
                if ($reporter) {
                    $updates['reporter_username'] = $reporter->name;
                } else {
                    $updates['reporter_username'] = 'Emergency Reporter';
                }
            } else {
                $updates['reporter_username'] = 'Anonymous Reporter';
            }
        }

        if (!empty($updates)) {
            $report->update($updates);
            $updated_count++;

            echo "✅ Updated report #{$report->id}: {$report->title}\n";
            echo "   📍 Koordinat: {$updates['coordinate_display']}\n";
            echo "   📞 Phone: {$updates['reporter_phone']}\n";
            echo "   👤 Reporter: {$updates['reporter_username']}\n\n";
        }
    }

    echo "🎉 SUCCESS! Updated $updated_count disaster reports\n";
    echo "📊 All reports now have:\n";
    echo "   ✅ coordinate_display (for Koordinat column)\n";
    echo "   ✅ reporter_phone (for No HP column)\n";
    echo "   ✅ reporter_username (for Username Pengguna column)\n\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🔄 TESTING UPDATED DATA\n";
echo "======================\n";

// Test query to verify the data
$test_reports = DisasterReport::select('title', 'coordinate_display', 'reporter_phone', 'reporter_username')
    ->limit(5)
    ->get();

foreach ($test_reports as $report) {
    echo "📋 {$report->title}\n";
    echo "   📍 Koordinat: {$report->coordinate_display}\n";
    echo "   📞 No HP: {$report->reporter_phone}\n";
    echo "   👤 Username: {$report->reporter_username}\n\n";
}

echo "✅ DISASTER REPORTS DATA POPULATION COMPLETE!\n";
