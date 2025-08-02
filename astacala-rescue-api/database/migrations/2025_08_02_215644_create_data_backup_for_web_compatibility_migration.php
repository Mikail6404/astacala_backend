<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates backup tables before web compatibility migration
     * as specified in INTEGRATION_ROADMAP.md Phase 3 Week 4 Database Unification
     */
    public function up(): void
    {
        // Create backup using a simple table copy approach
        $backupTableName = 'disaster_reports_backup_' . date('Y_m_d_His');

        // Use CREATE TABLE AS SELECT for a perfect copy
        DB::statement("CREATE TABLE {$backupTableName} AS SELECT * FROM disaster_reports");

        // Add a comment to identify this as a backup table
        DB::statement("ALTER TABLE {$backupTableName} COMMENT = 'Backup of disaster_reports table before web compatibility migration'");

        echo "✅ Backup created: {$backupTableName}\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find and drop backup tables (for cleanup)
        $tables = DB::select("SHOW TABLES LIKE 'disaster_reports_backup_%'");
        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            Schema::dropIfExists($tableName);
        }
    }
};
