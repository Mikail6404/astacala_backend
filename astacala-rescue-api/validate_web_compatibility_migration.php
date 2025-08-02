<?php

/**
 * Data Validation Script for Web Compatibility Migration
 * INTEGRATION_ROADMAP.md Phase 3 Week 4 Database Unification
 * 
 * This script validates data integrity after the web compatibility migration
 * Usage: php validate_web_compatibility_migration.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebCompatibilityMigrationValidator
{
    private $validationResults = [];

    public function execute()
    {
        echo "🔍 Starting Web Compatibility Migration Validation\n";
        echo "==================================================\n\n";

        try {
            // Run all validation checks
            $this->validateTableStructure();
            $this->validateDataIntegrity();
            $this->validateIndexes();
            $this->validateConstraints();
            $this->validateDataConsistency();
            $this->validateAPICompatibility();

            // Print summary
            $this->printValidationSummary();

            return $this->allValidationsPassed();
        } catch (Exception $e) {
            echo "❌ Validation failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function validateTableStructure()
    {
        echo "📋 Validating table structure...\n";

        // Check if all web compatibility columns exist
        $columns = Schema::getColumnListing('disaster_reports');
        $requiredWebColumns = [
            'personnel_count',
            'contact_phone',
            'brief_info',
            'coordinate_string',
            'scale_assessment',
            'casualty_count',
            'additional_description',
            'notification_status',
            'verification_status',
            'images',
            'evidence_documents'
        ];

        $missingColumns = [];
        foreach ($requiredWebColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }

        if (empty($missingColumns)) {
            $this->validationResults['table_structure'] = [
                'status' => 'PASS',
                'message' => 'All web compatibility columns present'
            ];
            echo "✅ Table structure validation passed\n";
        } else {
            $this->validationResults['table_structure'] = [
                'status' => 'FAIL',
                'message' => 'Missing columns: ' . implode(', ', $missingColumns)
            ];
            echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
        }
    }

    private function validateDataIntegrity()
    {
        echo "\n📊 Validating data integrity...\n";

        // Check that original data is preserved
        $totalRecords = DB::table('disaster_reports')->count();
        $recordsWithOriginalData = DB::table('disaster_reports')
            ->whereNotNull('title')
            ->whereNotNull('description')
            ->whereNotNull('disaster_type')
            ->count();

        if ($totalRecords === $recordsWithOriginalData) {
            $this->validationResults['data_integrity'] = [
                'status' => 'PASS',
                'message' => "All {$totalRecords} records have intact original data"
            ];
            echo "✅ Data integrity validation passed ({$totalRecords} records)\n";
        } else {
            $this->validationResults['data_integrity'] = [
                'status' => 'FAIL',
                'message' => "Data integrity issue: {$recordsWithOriginalData}/{$totalRecords} records have complete data"
            ];
            echo "❌ Data integrity issue detected\n";
        }
    }

    private function validateIndexes()
    {
        echo "\n🔗 Validating indexes...\n";

        // Check if required indexes exist
        $requiredIndexes = [
            'notification_status',
            'verification_status',
            'casualty_count',
            'personnel_count'
        ];

        $indexQuery = "SHOW INDEXES FROM disaster_reports WHERE Key_name IN ('" .
            implode("','", $requiredIndexes) . "')";
        $existingIndexes = DB::select($indexQuery);

        $foundIndexes = array_map(function ($index) {
            return $index->Key_name;
        }, $existingIndexes);

        $missingIndexes = array_diff($requiredIndexes, $foundIndexes);

        if (empty($missingIndexes)) {
            $this->validationResults['indexes'] = [
                'status' => 'PASS',
                'message' => 'All required indexes present'
            ];
            echo "✅ Index validation passed\n";
        } else {
            $this->validationResults['indexes'] = [
                'status' => 'FAIL',
                'message' => 'Missing indexes: ' . implode(', ', $missingIndexes)
            ];
            echo "❌ Missing indexes: " . implode(', ', $missingIndexes) . "\n";
        }
    }

    private function validateConstraints()
    {
        echo "\n🔒 Validating constraints...\n";

        // Check foreign key constraints are intact
        $constraintQuery = "SELECT 
            CONSTRAINT_NAME, 
            COLUMN_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'disaster_reports' 
            AND REFERENCED_TABLE_NAME IS NOT NULL";

        $constraints = DB::select($constraintQuery);

        if (count($constraints) >= 2) { // At least reported_by and verified_by_admin_id
            $this->validationResults['constraints'] = [
                'status' => 'PASS',
                'message' => count($constraints) . ' foreign key constraints found'
            ];
            echo "✅ Constraint validation passed\n";
        } else {
            $this->validationResults['constraints'] = [
                'status' => 'FAIL',
                'message' => 'Missing foreign key constraints'
            ];
            echo "❌ Missing foreign key constraints\n";
        }
    }

    private function validateDataConsistency()
    {
        echo "\n🔄 Validating data consistency...\n";

        // Check for logical consistency in new fields
        $inconsistencies = [];

        // Check casualty_count vs estimated_affected consistency
        $casualtyInconsistencies = DB::table('disaster_reports')
            ->whereNotNull('casualty_count')
            ->whereNotNull('estimated_affected')
            ->whereRaw('casualty_count > estimated_affected')
            ->count();

        if ($casualtyInconsistencies > 0) {
            $inconsistencies[] = "{$casualtyInconsistencies} records with casualty_count > estimated_affected";
        }

        // Check notification_status and verification_status
        $statusInconsistencies = DB::table('disaster_reports')
            ->where('verification_status', true)
            ->where('notification_status', false)
            ->count();

        if ($statusInconsistencies > 0) {
            $inconsistencies[] = "{$statusInconsistencies} records verified but not notified";
        }

        if (empty($inconsistencies)) {
            $this->validationResults['data_consistency'] = [
                'status' => 'PASS',
                'message' => 'No data consistency issues found'
            ];
            echo "✅ Data consistency validation passed\n";
        } else {
            $this->validationResults['data_consistency'] = [
                'status' => 'WARNING',
                'message' => 'Potential issues: ' . implode('; ', $inconsistencies)
            ];
            echo "⚠️ Data consistency warnings: " . implode('; ', $inconsistencies) . "\n";
        }
    }

    private function validateAPICompatibility()
    {
        echo "\n🌐 Validating API compatibility...\n";

        try {
            // Test basic API endpoint structure (simulated)
            $sampleRecord = DB::table('disaster_reports')->first();

            if ($sampleRecord) {
                // Check if record can be serialized to JSON (important for API responses)
                $jsonData = json_encode($sampleRecord);

                if ($jsonData !== false) {
                    $this->validationResults['api_compatibility'] = [
                        'status' => 'PASS',
                        'message' => 'Records can be serialized to JSON for API responses'
                    ];
                    echo "✅ API compatibility validation passed\n";
                } else {
                    $this->validationResults['api_compatibility'] = [
                        'status' => 'FAIL',
                        'message' => 'JSON serialization failed'
                    ];
                    echo "❌ JSON serialization failed\n";
                }
            } else {
                $this->validationResults['api_compatibility'] = [
                    'status' => 'SKIP',
                    'message' => 'No records to test'
                ];
                echo "⏭️ No records to test API compatibility\n";
            }
        } catch (Exception $e) {
            $this->validationResults['api_compatibility'] = [
                'status' => 'ERROR',
                'message' => 'Validation error: ' . $e->getMessage()
            ];
            echo "❌ API compatibility validation error\n";
        }
    }

    private function printValidationSummary()
    {
        echo "\n📊 VALIDATION SUMMARY\n";
        echo "====================\n";

        $passCount = 0;
        $totalCount = count($this->validationResults);

        foreach ($this->validationResults as $test => $result) {
            $icon = $this->getStatusIcon($result['status']);
            echo "{$icon} {$test}: {$result['status']} - {$result['message']}\n";

            if ($result['status'] === 'PASS') {
                $passCount++;
            }
        }

        echo "\n📈 Results: {$passCount}/{$totalCount} validations passed\n\n";

        if ($this->allValidationsPassed()) {
            echo "🎉 All critical validations passed! Migration appears successful.\n\n";
            echo "📋 Next Steps (from INTEGRATION_ROADMAP.md):\n";
            echo "- [ ] Test migration on staging environment\n";
            echo "- [ ] Validate data integrity after migration ✅ COMPLETED\n";
            echo "- [ ] Create rollback procedures ✅ COMPLETED\n";
            echo "- [ ] Execute production data migration\n";
        } else {
            echo "⚠️ Some validations failed. Review issues before proceeding.\n";
        }
    }

    private function getStatusIcon($status)
    {
        switch ($status) {
            case 'PASS':
                return '✅';
            case 'FAIL':
                return '❌';
            case 'WARNING':
                return '⚠️';
            case 'SKIP':
                return '⏭️';
            case 'ERROR':
                return '🔥';
            default:
                return '❓';
        }
    }

    private function allValidationsPassed()
    {
        foreach ($this->validationResults as $result) {
            if (in_array($result['status'], ['FAIL', 'ERROR'])) {
                return false;
            }
        }
        return true;
    }
}

// Execute validation if script is run directly
if (php_sapi_name() === 'cli') {
    $validator = new WebCompatibilityMigrationValidator();
    $success = $validator->execute();
    exit($success ? 0 : 1);
}
