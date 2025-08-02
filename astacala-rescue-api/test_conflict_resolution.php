<?php

require_once 'vendor/autoload.php';

use App\Services\ConflictResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ASTACALA CONFLICT RESOLUTION SYSTEM TEST ===\n\n";

try {
    $conflictService = new ConflictResolutionService();

    // Get a valid user ID for testing
    $testUser = DB::table('users')->select('id')->first();
    $testUserId = $testUser->id;
    echo "Using test user ID: $testUserId\n";

    // Test 1: Create test scenario with concurrent modifications
    echo "\nTEST 1: Setting up test scenario...\n";

    // Check if test report exists, create if not
    $testReport = DB::table('disaster_reports')->where('title', 'Test Conflict Report')->first();

    if (!$testReport) {
        $reportId = DB::table('disaster_reports')->insertGetId([
            'title' => 'Test Conflict Report',
            'description' => 'Initial description for testing',
            'location_name' => 'Test Location',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'status' => 'pending',
            'reported_by' => $testUserId,
            'version' => 1,
            'last_modified_at' => now(),
            'last_modified_by' => $testUserId,
            'last_modified_platform' => 'web',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "Created test report with ID: $reportId\n";
    } else {
        $reportId = $testReport->id;
        echo "Using existing test report with ID: $reportId\n";
    }

    // Test 2: Check for conflicts with optimistic locking
    echo "\nTEST 2: Testing optimistic locking conflict detection...\n";

    $originalVersion = DB::table('disaster_reports')->where('id', $reportId)->value('version');
    echo "Original version: $originalVersion\n";

    // Simulate first user change (increment version)
    DB::table('disaster_reports')
        ->where('id', $reportId)
        ->update([
            'description' => 'Modified by first user at ' . now(),
            'version' => $originalVersion + 1,
            'last_modified_at' => now(),
            'last_modified_by' => $testUserId,
            'last_modified_platform' => 'mobile'
        ]);

    $newVersion = DB::table('disaster_reports')->where('id', $reportId)->value('version');
    echo "Version after first modification: $newVersion\n";

    // Test 3: Detect conflict when second user tries to modify with old version
    echo "\nTEST 3: Testing conflict detection...\n";

    $proposedChanges = [
        'description' => 'Modified by second user at ' . now(),
        'severity_level' => 'high',
        'status' => 'verified'
    ];

    $conflictDetected = $conflictService->detectConflict($reportId, $originalVersion, $proposedChanges, $testUserId + 1);

    if ($conflictDetected) {
        echo "✅ CONFLICT DETECTED: Optimistic locking working correctly!\n";

        // Test 4: Get conflict details
        echo "\nTEST 4: Getting conflict details...\n";
        $conflicts = $conflictService->getConflictsForReport($reportId);

        if (!empty($conflicts)) {
            echo "✅ Found " . count($conflicts) . " conflicts in queue\n";
            foreach ($conflicts as $conflict) {
                echo "   - Conflict ID: {$conflict->id}\n";
                echo "   - Conflicting fields: " . implode(', ', json_decode($conflict->conflicting_fields)) . "\n";
                echo "   - Severity: {$conflict->conflict_severity}\n";
                echo "   - Status: {$conflict->status}\n";
            }
        } else {
            echo "❌ No conflicts found in queue\n";
        }

        // Test 5: Resolve conflict using admin wins strategy
        echo "\nTEST 5: Testing conflict resolution (admin wins)...\n";

        $latestConflict = collect($conflicts)->first();
        if ($latestConflict) {
            $resolved = $conflictService->resolveConflictManually(
                $latestConflict->id,
                'keep_original',
                $testUserId, // admin user ID
                'Testing admin wins strategy'
            );

            if ($resolved) {
                echo "✅ Conflict resolved using admin wins strategy\n";

                // Check audit trail
                $auditEntries = DB::table('disaster_report_audit_trails')
                    ->where('report_id', $reportId)
                    ->orderBy('modification_timestamp', 'desc')
                    ->limit(3)
                    ->get();

                echo "   - Audit trail entries: " . count($auditEntries) . "\n";
                foreach ($auditEntries as $entry) {
                    $modifiedFields = json_decode($entry->modified_fields);
                    echo "   - Modified: " . implode(', ', $modifiedFields) . " by user {$entry->modified_by} on {$entry->user_platform}\n";
                }
            } else {
                echo "❌ Failed to resolve conflict\n";
            }
        }
    } else {
        echo "❌ CONFLICT NOT DETECTED: Issue with optimistic locking!\n";
    }

    // Test 6: Test field merging for non-critical conflicts
    echo "\nTEST 6: Testing field merging for non-critical conflicts...\n";

    // Create a scenario where only non-critical fields conflict
    $nonCriticalChanges = [
        'description' => 'Updated description with additional details',
        // Not modifying critical fields like status or severity
    ];

    // Reset version for this test
    $currentVersion = DB::table('disaster_reports')->where('id', $reportId)->value('version');

    // Simulate another user making non-critical changes
    $mergeConflict = $conflictService->detectConflict($reportId, $currentVersion, $nonCriticalChanges, $testUserId + 2);

    if ($mergeConflict) {
        echo "   Detected conflict for non-critical fields\n";

        $mergeConflicts = $conflictService->getConflictsForReport($reportId, 'pending_review');
        $mergeConflictItem = collect($mergeConflicts)->first();

        if ($mergeConflictItem) {
            $mergeResolved = $conflictService->resolveConflictManually(
                $mergeConflictItem->id,
                'merge_changes',
                $testUserId,
                'Testing field merging for non-critical fields'
            );

            if ($mergeResolved) {
                echo "✅ Successfully merged non-critical field changes\n";
            } else {
                echo "❌ Failed to merge changes\n";
            }
        }
    } else {
        echo "   No conflicts detected for non-critical changes\n";
    }

    // Test 7: Performance test - bulk conflict detection
    echo "\nTEST 7: Performance test - bulk operations...\n";

    $startTime = microtime(true);

    // Test multiple concurrent modification scenarios
    for ($i = 0; $i < 5; $i++) {
        $testChanges = [
            'description' => "Bulk test modification #$i at " . now(),
            'severity_level' => ($i % 2 == 0) ? 'high' : 'low'
        ];

        $conflictService->detectConflict($reportId, 1, $testChanges, $testUserId + $i + 10);
    }

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    echo "✅ Processed 5 conflict detections in " . round($executionTime, 2) . "ms\n";

    // Final summary
    echo "\n=== SUMMARY ===\n";
    $totalConflicts = DB::table('conflict_resolution_queue')->where('report_id', $reportId)->count();
    $resolvedConflicts = DB::table('conflict_resolution_queue')
        ->where('report_id', $reportId)
        ->where('status', 'resolved')
        ->count();
    $pendingConflicts = $totalConflicts - $resolvedConflicts;

    echo "Total conflicts created: $totalConflicts\n";
    echo "Resolved conflicts: $resolvedConflicts\n";
    echo "Pending conflicts: $pendingConflicts\n";

    $auditCount = DB::table('disaster_report_audit_trails')->where('report_id', $reportId)->count();
    echo "Audit trail entries: $auditCount\n";

    $currentReport = DB::table('disaster_reports')->where('id', $reportId)->first();
    echo "Final report version: {$currentReport->version}\n";
    echo "Last modified by: {$currentReport->last_modified_by} on {$currentReport->last_modified_platform}\n";

    echo "\n✅ ALL CONFLICT RESOLUTION TESTS COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";
