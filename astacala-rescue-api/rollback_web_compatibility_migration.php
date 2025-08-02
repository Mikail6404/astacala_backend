<?php

/**
 * Rollback Script for Web Compatibility Migration
 * INTEGRATION_ROADMAP.md Phase 3 Week 4 Database Unification
 * 
 * This script provides rollback procedures for the web compatibility migration
 * Usage: php rollback_web_compatibility_migration.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebCompatibilityMigrationRollback
{
    private $backupTablePattern = 'disaster_reports_backup_%';

    public function execute()
    {
        echo "🔄 Starting Web Compatibility Migration Rollback\n";
        echo "=================================================\n\n";

        try {
            // Step 1: Find the most recent backup table
            $backupTable = $this->findLatestBackupTable();

            if (!$backupTable) {
                echo "❌ No backup table found. Cannot perform rollback.\n";
                return false;
            }

            echo "📋 Found backup table: {$backupTable}\n";

            // Step 2: Verify backup table integrity
            if (!$this->verifyBackupIntegrity($backupTable)) {
                echo "❌ Backup table integrity check failed. Aborting rollback.\n";
                return false;
            }

            // Step 3: Create restore point of current data
            $restoreTable = $this->createRestorePoint();
            echo "💾 Created restore point: {$restoreTable}\n";

            // Step 4: Drop web compatibility columns
            $this->dropWebCompatibilityColumns();
            echo "🗑️ Removed web compatibility columns\n";

            // Step 5: Restore data from backup (if needed)
            // Note: Since we're only adding columns, existing data should be preserved

            echo "\n✅ Rollback completed successfully!\n";
            echo "📝 Restore point created at: {$restoreTable}\n";
            echo "📝 Original backup preserved at: {$backupTable}\n\n";

            // Step 6: Provide verification instructions
            $this->printVerificationInstructions();

            return true;
        } catch (Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function findLatestBackupTable()
    {
        $tables = DB::select("SHOW TABLES LIKE '{$this->backupTablePattern}'");

        if (empty($tables)) {
            return null;
        }

        // Get the most recent backup table (highest timestamp)
        $tableNames = array_map(function ($table) {
            return array_values((array) $table)[0];
        }, $tables);

        sort($tableNames);
        return end($tableNames);
    }

    private function verifyBackupIntegrity($backupTable)
    {
        // Check if table exists and has data
        $count = DB::table($backupTable)->count();
        echo "📊 Backup table contains {$count} records\n";

        // Verify essential columns exist
        $columns = Schema::getColumnListing($backupTable);
        $requiredColumns = ['id', 'title', 'description', 'disaster_type', 'status'];

        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                echo "❌ Missing required column: {$column}\n";
                return false;
            }
        }

        echo "✅ Backup table integrity verified\n";
        return true;
    }

    private function createRestorePoint()
    {
        $restoreTableName = 'disaster_reports_restore_point_' . date('Y_m_d_His');

        // Create restore point table with current structure
        DB::statement("CREATE TABLE {$restoreTableName} AS SELECT * FROM disaster_reports");

        return $restoreTableName;
    }

    private function dropWebCompatibilityColumns()
    {
        Schema::table('disaster_reports', function ($table) {
            // Drop indexes first (if they exist)
            try {
                $table->dropIndex(['notification_status']);
                $table->dropIndex(['verification_status']);
                $table->dropIndex(['casualty_count']);
                $table->dropIndex(['personnel_count']);
            } catch (Exception $e) {
                echo "⚠️ Some indexes may not exist: " . $e->getMessage() . "\n";
            }

            // Drop web compatibility columns
            $webColumns = [
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

            foreach ($webColumns as $column) {
                try {
                    $table->dropColumn($column);
                } catch (Exception $e) {
                    echo "⚠️ Column {$column} may not exist: " . $e->getMessage() . "\n";
                }
            }
        });
    }

    private function printVerificationInstructions()
    {
        echo "🔍 VERIFICATION INSTRUCTIONS:\n";
        echo "============================\n";
        echo "1. Check disaster_reports table structure:\n";
        echo "   DESCRIBE disaster_reports;\n\n";
        echo "2. Verify record count matches backup:\n";
        echo "   SELECT COUNT(*) FROM disaster_reports;\n\n";
        echo "3. Test mobile app functionality\n";
        echo "4. Check API endpoints still work\n\n";
        echo "🔄 To re-apply migration:\n";
        echo "   php artisan migrate\n\n";
    }
}

// Execute rollback if script is run directly
if (php_sapi_name() === 'cli') {
    $rollback = new WebCompatibilityMigrationRollback();
    $rollback->execute();
}
