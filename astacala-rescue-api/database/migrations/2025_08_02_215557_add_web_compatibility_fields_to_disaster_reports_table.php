<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds web app compatibility fields to disaster_reports table
     * as specified in INTEGRATION_ROADMAP.md Phase 3 Week 4 Database Unification
     */
    public function up(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            // Web app compatibility fields for cross-platform integration
            $table->integer('personnel_count')->nullable()->comment('Number of personnel in response team');
            $table->string('contact_phone', 255)->nullable()->comment('Emergency contact phone number');
            $table->text('brief_info')->nullable()->comment('Brief summary for quick admin review');
            $table->string('coordinate_string', 255)->nullable()->comment('Human-readable coordinate string');
            $table->string('scale_assessment', 100)->nullable()->comment('Assessment of disaster scale');
            $table->integer('casualty_count')->nullable()->comment('Number of casualties (web app field)');
            $table->text('additional_description')->nullable()->comment('Additional details for web dashboard');
            $table->boolean('notification_status')->default(false)->comment('Notification sent status');
            $table->boolean('verification_status')->default(false)->comment('Manual verification flag for web dashboard');

            // Additional JSON fields for storing complex web app data
            $table->json('images')->nullable()->comment('Image attachments JSON array');
            $table->json('evidence_documents')->nullable()->comment('Evidence documents JSON array');

            // Indexes for improved query performance
            $table->index('notification_status');
            $table->index('verification_status');
            $table->index('casualty_count');
            $table->index('personnel_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['notification_status']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['casualty_count']);
            $table->dropIndex(['personnel_count']);

            // Drop the web compatibility columns
            $table->dropColumn([
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
                'evidence_documents',
            ]);
        });
    }
};
