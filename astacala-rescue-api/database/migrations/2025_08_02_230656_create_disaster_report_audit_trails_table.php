<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('disaster_report_audit_trails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('modified_by');
            $table->string('user_platform', 20)->default('web'); // 'mobile' or 'web'
            $table->json('modified_fields')->comment('Array of field names that were modified');
            $table->json('field_changes')->comment('Before/after values for each modified field');
            $table->timestamp('modification_timestamp');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('modification_reason')->nullable()->comment('Optional reason for the change');
            $table->timestamps();

            // Indexes for performance (with shorter names)
            $table->index('report_id', 'audit_trails_report_id_idx');
            $table->index('modified_by', 'audit_trails_modified_by_idx');
            $table->index('modification_timestamp', 'audit_trails_timestamp_idx');
            $table->index(['report_id', 'modification_timestamp'], 'audit_trails_report_time_idx');
            $table->index(['modified_by', 'modification_timestamp'], 'audit_trails_user_time_idx');
            $table->index('user_platform', 'audit_trails_platform_idx');
            $table->index('created_at', 'audit_trails_created_idx');

            // Foreign key constraints
            $table->foreign('report_id')->references('id')->on('disaster_reports')->onDelete('cascade');
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disaster_report_audit_trails');
    }
};
