<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing fields to disaster_reports table for web dashboard compatibility
     */
    public function up(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            // Add fields that are missing for web dashboard display
            $table->string('coordinate_display', 500)->nullable()->after('coordinate_string')
                ->comment('Human-readable coordinate display for web dashboard');
            $table->string('reporter_phone', 20)->nullable()->after('coordinate_display')
                ->comment('Reporter contact phone number');
            $table->string('reporter_username', 255)->nullable()->after('reporter_phone')
                ->comment('Cached reporter username for quick display');

            // Add indexes for improved query performance
            $table->index('reporter_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            $table->dropIndex(['reporter_username']);
            $table->dropColumn(['coordinate_display', 'reporter_phone', 'reporter_username']);
        });
    }
};
