<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

echo "=== BACKEND FIELD ENHANCEMENTS VALIDATION ===\n\n";

// Test 1: Check disaster reports with new fields
echo "1. Testing Disaster Reports with New Fields...\n";
$reports = \App\Models\DisasterReport::select('id', 'title', 'coordinate_display', 'reporter_phone', 'reporter_username')->limit(3)->get();

foreach ($reports as $report) {
    echo "   Report #{$report->id}: {$report->title}\n";
    echo "     📍 Coordinates: " . ($report->coordinate_display ?? 'N/A') . "\n";
    echo "     📞 Phone: " . ($report->reporter_phone ?? 'N/A') . "\n";
    echo "     👤 Username: " . ($report->reporter_username ?? 'N/A') . "\n";
    echo "\n";
}

// Test 2: Check publications with new fields
echo "2. Testing Publications with New Fields...\n";
$publications = \App\Models\Publication::select('id', 'title', 'created_by', 'creator_name')->get();

foreach ($publications as $publication) {
    echo "   Publication #{$publication->id}: {$publication->title}\n";
    echo "     👤 Created By ID: " . ($publication->created_by ?? 'N/A') . "\n";
    echo "     👤 Creator Name: " . ($publication->creator_name ?? 'N/A') . "\n";
    echo "\n";
}

// Test 3: Check API endpoint responses
echo "3. Testing API Endpoint Responses...\n";

// Test Gibran publications endpoint
echo "   a) Testing /api/gibran/publications endpoint...\n";
try {
    $response = file_get_contents('http://127.0.0.1:8000/api/gibran/publications');
    $data = json_decode($response, true);

    if ($data && $data['status'] === 'success') {
        $publicationCount = count($data['data']);
        echo "      ✅ Publications endpoint working: $publicationCount publications\n";

        if ($publicationCount > 0) {
            $firstPub = $data['data'][0];
            $hasCreatorFields = isset($firstPub['created_by']) && isset($firstPub['creator_name']);
            echo "      " . ($hasCreatorFields ? '✅' : '❌') . " Creator fields present: " .
                ($hasCreatorFields ? "ID={$firstPub['created_by']}, Name={$firstPub['creator_name']}" : "Missing") . "\n";
        }
    } else {
        echo "      ❌ Publications endpoint failed\n";
    }
} catch (Exception $e) {
    echo "      ❌ Publications endpoint error: " . $e->getMessage() . "\n";
}

// Test Gibran berita-bencana endpoint
echo "   b) Testing /api/gibran/berita-bencana endpoint...\n";
try {
    $response = file_get_contents('http://127.0.0.1:8000/api/gibran/berita-bencana');
    $data = json_decode($response, true);

    if ($data && $data['status'] === 'success') {
        $beritaCount = count($data['data']);
        echo "      ✅ Berita endpoint working: $beritaCount berita items\n";
    } else {
        echo "      ❌ Berita endpoint failed\n";
    }
} catch (Exception $e) {
    echo "      ❌ Berita endpoint error: " . $e->getMessage() . "\n";
}

// Test 4: Database counts
echo "\n4. Database Statistics...\n";
$disasterReportsCount = \App\Models\DisasterReport::count();
$reportsWithCoordinates = \App\Models\DisasterReport::whereNotNull('coordinate_display')->count();
$reportsWithPhone = \App\Models\DisasterReport::whereNotNull('reporter_phone')->count();
$reportsWithUsername = \App\Models\DisasterReport::whereNotNull('reporter_username')->count();

$publicationsCount = \App\Models\Publication::count();
$publicationsWithCreator = \App\Models\Publication::whereNotNull('created_by')->count();
$publicationsWithCreatorName = \App\Models\Publication::whereNotNull('creator_name')->count();

echo "   📊 Disaster Reports: $disasterReportsCount total\n";
echo "     - With coordinate_display: $reportsWithCoordinates\n";
echo "     - With reporter_phone: $reportsWithPhone\n";
echo "     - With reporter_username: $reportsWithUsername\n";
echo "\n";
echo "   📊 Publications: $publicationsCount total\n";
echo "     - With created_by: $publicationsWithCreator\n";
echo "     - With creator_name: $publicationsWithCreatorName\n";

echo "\n=== BACKEND FIELD ENHANCEMENTS VALIDATION COMPLETE ===\n";
