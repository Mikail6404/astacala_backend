<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== POPULATING BACKEND DATABASE WITH SAMPLE DATA ===\n\n";

try {
    echo "🔄 Step 1: Adding missing user profile data...\n";

    // Sample birth dates, organizations, and phone numbers
    $sampleData = [
        ['birth_date' => '1990-05-15', 'organization' => 'Jakarta Emergency Response', 'phone' => '+62812345001'],
        ['birth_date' => '1985-08-22', 'organization' => 'Bandung Rescue Team', 'phone' => '+62812345002'],
        ['birth_date' => '1992-03-10', 'organization' => 'Surabaya Disaster Unit', 'phone' => '+62812345003'],
        ['birth_date' => '1988-11-07', 'organization' => 'Medan Response Force', 'phone' => '+62812345004'],
        ['birth_date' => '1995-01-30', 'organization' => 'Bali Emergency Services', 'phone' => '+62812345005'],
        ['birth_date' => '1987-09-14', 'organization' => 'Yogyakarta Relief Team', 'phone' => '+62812345006'],
        ['birth_date' => '1993-12-03', 'organization' => 'Semarang Rescue Squad', 'phone' => '+62812345007'],
        ['birth_date' => '1991-06-18', 'organization' => 'Makassar Emergency Unit', 'phone' => '+62812345008'],
        ['birth_date' => '1989-04-25', 'organization' => 'Palembang Response Team', 'phone' => '+62812345009'],
        ['birth_date' => '1994-10-12', 'organization' => 'Batam Disaster Relief', 'phone' => '+62812345010'],
    ];

    // Get users that need profile data
    $users = DB::table('users')->whereNull('birth_date')->orWhereNull('phone')->get();

    $updatedCount = 0;
    foreach ($users as $index => $user) {
        $dataIndex = $index % count($sampleData);
        $profileData = $sampleData[$dataIndex];

        DB::table('users')->where('id', $user->id)->update([
            'birth_date' => $profileData['birth_date'],
            'organization' => $profileData['organization'],
            'phone' => $profileData['phone'],
            'updated_at' => now()
        ]);

        $updatedCount++;
        echo "   ✅ Updated user ID {$user->id}: {$user->name}\n";
    }

    echo "   📊 Updated $updatedCount users with profile data\n\n";

    echo "🔄 Step 2: Adding missing disaster report data...\n";

    // Get disaster reports that need additional data
    $reports = DB::table('disaster_reports')->whereNull('personnel_count')->orWhereNull('contact_phone')->get();

    $reportUpdatedCount = 0;
    foreach ($reports as $index => $report) {
        $personnelCount = rand(5, 25);
        $casualtyCount = rand(0, 15);
        $contactPhone = '+62821' . sprintf('%06d', rand(100000, 999999));
        $coordinateString = $report->latitude . ', ' . $report->longitude;

        DB::table('disaster_reports')->where('id', $report->id)->update([
            'personnel_count' => $personnelCount,
            'casualty_count' => $casualtyCount,
            'contact_phone' => $contactPhone,
            'coordinate_string' => $coordinateString,
            'brief_info' => substr($report->description, 0, 100) . '...',
            'scale_assessment' => 'MEDIUM',
            'updated_at' => now()
        ]);

        $reportUpdatedCount++;
        echo "   ✅ Updated report ID {$report->id}: {$report->title}\n";
    }

    echo "   📊 Updated $reportUpdatedCount disaster reports with additional data\n\n";

    echo "🔄 Step 3: Adding sample publications...\n";

    // Check if publications exist
    $publicationCount = DB::table('publications')->count();

    if ($publicationCount < 5) {
        $adminUsers = DB::table('users')->whereIn('role', ['ADMIN', 'admin'])->pluck('id');

        if ($adminUsers->count() > 0) {
            $samplePublications = [
                [
                    'title' => 'Panduan Evakuasi Banjir Jakarta',
                    'content' => 'Panduan lengkap untuk evakuasi mandiri saat banjir melanda Jakarta. Berisi langkah-langkah darurat, rute evakuasi, dan kontak penting.',
                    'type' => 'guide',
                    'category' => 'flood_response',
                    'status' => 'published',
                    'author_id' => $adminUsers->random(),
                    'published_at' => now(),
                ],
                [
                    'title' => 'Pelatihan Tanggap Darurat Gempa',
                    'content' => 'Informasi mengenai pelatihan tanggap darurat gempa bumi untuk relawan dan masyarakat umum. Jadwal dan lokasi pelatihan tersedia.',
                    'type' => 'announcement',
                    'category' => 'earthquake_preparedness',
                    'status' => 'published',
                    'author_id' => $adminUsers->random(),
                    'published_at' => now(),
                ],
                [
                    'title' => 'Laporan Kebakaran Hutan Sumatra',
                    'content' => 'Laporan terkini mengenai kebakaran hutan di Sumatra dan upaya penanggulangannya. Status terkini dan bantuan yang diperlukan.',
                    'type' => 'report_summary',
                    'category' => 'forest_fire',
                    'status' => 'published',
                    'author_id' => $adminUsers->random(),
                    'published_at' => now(),
                ]
            ];

            foreach ($samplePublications as $pub) {
                DB::table('publications')->insert(array_merge($pub, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
                echo "   ✅ Created publication: {$pub['title']}\n";
            }
        }
    }

    echo "   📊 Publications in database: " . DB::table('publications')->count() . "\n\n";

    echo "🔄 Step 4: Adding sample notifications...\n";

    // Check if notifications exist
    $notificationCount = DB::table('notifications')->count();

    if ($notificationCount < 10) {
        $allUsers = DB::table('users')->pluck('id');
        $reports = DB::table('disaster_reports')->pluck('id');

        if ($allUsers->count() > 0) {
            for ($i = 0; $i < 5; $i++) {
                DB::table('notifications')->insert([
                    'recipient_id' => $allUsers->random(),
                    'user_id' => $allUsers->random(),
                    'title' => 'Verifikasi Laporan Bencana',
                    'message' => 'Laporan bencana Anda telah diverifikasi dan sedang dalam proses penanganan.',
                    'type' => 'REPORT',
                    'priority' => 'MEDIUM',
                    'related_report_id' => $reports->count() > 0 ? $reports->random() : null,
                    'is_read' => rand(0, 1),
                    'created_at' => now()->subDays(rand(1, 7)),
                    'updated_at' => now()
                ]);
            }
            echo "   ✅ Created 5 sample notifications\n";
        }
    }

    echo "   📊 Notifications in database: " . DB::table('notifications')->count() . "\n\n";

    echo "✅ Sample data population complete!\n\n";

    // Summary
    echo "📊 FINAL DATABASE SUMMARY:\n";
    echo "   Users: " . DB::table('users')->count() . " (with profile data)\n";
    echo "   Disaster Reports: " . DB::table('disaster_reports')->count() . " (with contact/personnel data)\n";
    echo "   Publications: " . DB::table('publications')->count() . " (with author relationships)\n";
    echo "   Notifications: " . DB::table('notifications')->count() . " (with location data)\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== SAMPLE DATA POPULATION COMPLETE ===\n";
