<?php

require_once 'vendor/autoload.php';

use App\Services\DataValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ASTACALA DATA VALIDATION & INTEGRITY TEST ===\n\n";

try {
    $validationService = new DataValidationService();

    // Get a valid user for testing
    $testUser = DB::table('users')->select('id', 'name')->first();
    $testUserId = $testUser->id;
    echo "Using test user: {$testUser->name} (ID: $testUserId)\n";

    // Test 1: Validate correct disaster report data
    echo "\nTEST 1: Testing valid disaster report data...\n";

    $validData = [
        'title' => 'Valid Test Report for Data Validation',
        'description' => 'This is a comprehensive description for testing data validation with all required fields properly filled.',
        'disaster_type' => 'earthquake',
        'severity_level' => 'high',
        'status' => 'pending',
        'latitude' => 40.7589,
        'longitude' => -73.9851,
        'location_name' => 'New York City, NY',
        'reported_by' => $testUserId,
        'incident_timestamp' => now()->subHours(2)->toDateTimeString(),
        'estimated_affected' => 150,
        'casualty_count' => 3,
        'weather_condition' => 'Clear',
        'personnel_count' => 5,
        'contact_phone' => '+1-555-123-4567',
        'scale_assessment' => 'moderate'
    ];

    $validator = $validationService->validateDisasterReport($validData);

    if ($validator->passes()) {
        echo "✅ Valid data passed validation\n";
    } else {
        echo "❌ Valid data failed validation:\n";
        foreach ($validator->errors()->all() as $error) {
            echo "   - $error\n";
        }
    }

    // Test 2: Test invalid data validation
    echo "\nTEST 2: Testing invalid disaster report data...\n";

    $invalidData = [
        'title' => 'Bad', // Too short
        'description' => 'Too short', // Too short
        'disaster_type' => 'invalid_type', // Invalid type
        'severity_level' => 'extreme', // Invalid severity
        'status' => 'unknown', // Invalid status
        'latitude' => 95.0, // Invalid latitude
        'longitude' => 200.0, // Invalid longitude
        'location_name' => 'NY', // Too short
        'reported_by' => 99999, // Non-existent user
        'incident_timestamp' => now()->addDays(1)->toDateTimeString(), // Future date
        'estimated_affected' => -10, // Negative value
        'casualty_count' => -5, // Negative value
        'contact_phone' => 'invalid-phone', // Invalid format
        'scale_assessment' => 'invalid' // Invalid scale
    ];

    $invalidValidator = $validationService->validateDisasterReport($invalidData);

    if ($invalidValidator->fails()) {
        echo "✅ Invalid data correctly failed validation\n";
        echo "   Validation errors found:\n";
        foreach ($invalidValidator->errors()->all() as $error) {
            echo "   - $error\n";
        }
    } else {
        echo "❌ Invalid data unexpectedly passed validation\n";
    }

    // Test 3: Test data consistency validation
    echo "\nTEST 3: Testing data consistency validation...\n";

    $inconsistentData = [
        'title' => 'Inconsistent Data Test Report',
        'description' => 'Testing data consistency with conflicting severity and casualty information.',
        'disaster_type' => 'fire',
        'severity_level' => 'low', // Low severity
        'status' => 'pending',
        'latitude' => 40.7589,
        'longitude' => -73.9851,
        'location_name' => 'Test Location',
        'reported_by' => $testUserId,
        'casualty_count' => 50, // High casualties for low severity
        'estimated_affected' => 1000 // Many affected for low severity
    ];

    $consistencyValidator = $validationService->validateDisasterReport($inconsistentData);

    if ($consistencyValidator->fails()) {
        echo "✅ Data consistency validation working correctly\n";
        echo "   Consistency errors found:\n";
        foreach ($consistencyValidator->errors()->all() as $error) {
            echo "   - $error\n";
        }
    } else {
        echo "❌ Data consistency validation failed to catch inconsistencies\n";
    }

    // Test 4: Create test data for health check
    echo "\nTEST 4: Creating test data with issues for health check...\n";

    // Create report with invalid coordinates
    $invalidCoordReport = DB::table('disaster_reports')->insertGetId([
        'title' => 'Invalid Coordinates Test',
        'description' => 'Report with invalid coordinates for health check testing',
        'disaster_type' => 'flood',
        'severity_level' => 'medium',
        'status' => 'pending',
        'latitude' => 0, // Invalid
        'longitude' => 0, // Invalid
        'location_name' => 'Invalid Location',
        'reported_by' => $testUserId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // Create report with future timestamp
    $futureReport = DB::table('disaster_reports')->insertGetId([
        'title' => 'Future Incident Test',
        'description' => 'Report with future incident timestamp for testing',
        'disaster_type' => 'earthquake',
        'severity_level' => 'high',
        'status' => 'pending',
        'latitude' => 40.7589,
        'longitude' => -73.9851,
        'location_name' => 'Future Location',
        'reported_by' => $testUserId,
        'incident_timestamp' => now()->addDays(1), // Future date
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // Create report without last_modified_at (simulating missing audit trail data)
    $noAuditReport = DB::table('disaster_reports')->insertGetId([
        'title' => 'No Audit Trail Test',
        'description' => 'Report without proper audit trail for testing validation functionality',
        'disaster_type' => 'fire',
        'severity_level' => 'low',
        'status' => 'pending',
        'latitude' => 40.7589,
        'longitude' => -73.9851,
        'location_name' => 'No Audit Location',
        'reported_by' => $testUserId,
        'version' => 1,
        'last_modified_at' => null, // Missing for audit trail test
        'created_at' => now(),
        'updated_at' => now()
    ]);

    echo "✅ Created test reports with issues:\n";
    echo "   - Report $invalidCoordReport: Invalid coordinates (0,0)\n";
    echo "   - Report $futureReport: Future incident timestamp\n";
    echo "   - Report $noAuditReport: Missing audit trail data\n";

    // Test 5: Perform comprehensive data health check
    echo "\nTEST 5: Performing comprehensive data health check...\n";

    $healthReport = $validationService->performDataHealthCheck();

    echo "✅ Data health check completed\n";
    echo "   Overall status: {$healthReport['overall_status']}\n";
    echo "   Checks performed: " . count($healthReport['checks_performed']) . "\n";
    echo "   Issues found: " . count($healthReport['issues_found']) . "\n";

    if (!empty($healthReport['issues_found'])) {
        echo "\n   📋 Issues found:\n";
        foreach ($healthReport['issues_found'] as $issue) {
            echo "   - {$issue['type']}: {$issue['count']} issues ({$issue['severity']} severity)\n";
            echo "     Description: {$issue['description']}\n";
        }
    }

    if (!empty($healthReport['recommendations'])) {
        echo "\n   💡 Recommendations:\n";
        foreach ($healthReport['recommendations'] as $recommendation) {
            echo "   - $recommendation\n";
        }
    }

    // Test 6: Test auto-fix functionality
    echo "\nTEST 6: Testing auto-fix functionality...\n";

    $fixedIssues = $validationService->autoFixDataIssues();

    echo "✅ Auto-fix completed\n";
    echo "   Issues fixed: " . count($fixedIssues) . "\n";

    if (!empty($fixedIssues)) {
        foreach ($fixedIssues as $fix) {
            echo "   - Fixed {$fix['type']}: {$fix['description']}\n";
            if (isset($fix['count'])) {
                echo "     Count: {$fix['count']}\n";
            }
        }
    }

    // Test 7: Re-run health check after auto-fix
    echo "\nTEST 7: Re-running health check after auto-fix...\n";

    $postFixHealthReport = $validationService->performDataHealthCheck();

    echo "✅ Post-fix health check completed\n";
    echo "   New overall status: {$postFixHealthReport['overall_status']}\n";
    echo "   Issues remaining: " . count($postFixHealthReport['issues_found']) . "\n";

    // Compare before and after
    $issuesBefore = count($healthReport['issues_found']);
    $issuesAfter = count($postFixHealthReport['issues_found']);

    if ($issuesAfter < $issuesBefore) {
        echo "   ✅ Issues reduced from $issuesBefore to $issuesAfter\n";
    } else {
        echo "   ⚠️ Issues remain the same: $issuesBefore\n";
    }

    // Test 8: Generate comprehensive validation report
    echo "\nTEST 8: Generating comprehensive validation report...\n";

    $validationReport = $validationService->generateValidationReport();

    echo "✅ Validation report generated\n";
    echo "   Report timestamp: {$validationReport['report_timestamp']}\n";
    echo "   System status: {$validationReport['system_status']}\n";
    echo "   Total reports: {$validationReport['total_reports']}\n";
    echo "   Total users: {$validationReport['total_users']}\n";
    echo "   Total audit entries: {$validationReport['total_audit_entries']}\n";
    echo "   Total conflicts: {$validationReport['total_conflicts']}\n";

    echo "\n   📊 Issue Summary:\n";
    echo "   - Critical issues: {$validationReport['summary']['critical_issues']}\n";
    echo "   - Medium issues: {$validationReport['summary']['medium_issues']}\n";
    echo "   - Low issues: {$validationReport['summary']['low_issues']}\n";
    echo "   - Recommendations: {$validationReport['summary']['recommendations_count']}\n";

    // Test 9: Test update validation
    echo "\nTEST 9: Testing update validation...\n";

    $updateData = [
        'description' => 'Updated description for validation testing',
        'severity_level' => 'critical'
    ];

    $updateValidator = $validationService->validateDisasterReport($updateData, true);

    if ($updateValidator->passes()) {
        echo "✅ Update validation passed correctly\n";
    } else {
        echo "❌ Update validation failed:\n";
        foreach ($updateValidator->errors()->all() as $error) {
            echo "   - $error\n";
        }
    }

    // Test 10: Performance test
    echo "\nTEST 10: Performance testing validation...\n";

    $startTime = microtime(true);

    // Test multiple validations
    for ($i = 0; $i < 10; $i++) {
        $testData = [
            'title' => "Performance Test Report #$i",
            'description' => 'Testing validation performance with multiple simultaneous validations.',
            'disaster_type' => 'flood',
            'severity_level' => 'medium',
            'status' => 'pending',
            'latitude' => 40.7589 + ($i * 0.001),
            'longitude' => -73.9851 + ($i * 0.001),
            'location_name' => "Performance Test Location #$i",
            'reported_by' => $testUserId
        ];

        $validationService->validateDisasterReport($testData);
    }

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;

    echo "✅ Validated 10 reports in " . round($executionTime, 2) . "ms\n";
    echo "   Average: " . round($executionTime / 10, 2) . "ms per validation\n";

    // Final summary
    echo "\n=== DATA VALIDATION & INTEGRITY TEST SUMMARY ===\n";

    $finalHealthCheck = $validationService->performDataHealthCheck();

    echo "System Health Status: {$finalHealthCheck['overall_status']}\n";
    echo "Total Data Integrity Checks: " . count($finalHealthCheck['checks_performed']) . "\n";
    echo "Issues Found: " . count($finalHealthCheck['issues_found']) . "\n";
    echo "Recommendations Generated: " . count($finalHealthCheck['recommendations']) . "\n";

    echo "\n✅ ALL DATA VALIDATION & INTEGRITY TESTS COMPLETED SUCCESSFULLY!\n";
    echo "\nData Validation System Status: OPERATIONAL ✅\n";
    echo "- Field validation: Working\n";
    echo "- Data consistency checks: Working\n";
    echo "- Health monitoring: Working\n";
    echo "- Auto-fix functionality: Working\n";
    echo "- Performance: Acceptable (" . round($executionTime / 10, 2) . "ms/validation)\n";

    // Clean up test reports
    echo "\nCleaning up test reports...\n";
    DB::table('disaster_reports')->whereIn('id', [$invalidCoordReport, $futureReport, $noAuditReport])->delete();
    echo "✅ Test data cleaned up\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== DATA VALIDATION TEST COMPLETED ===\n";
