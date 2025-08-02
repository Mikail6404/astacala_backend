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
        Schema::create('conflict_resolution_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('conflicting_user_id')->comment('User attempting the conflicting change');
            $table->unsignedBigInteger('original_user_id')->comment('User who made the original change');
            $table->json('conflicting_fields')->comment('Array of field names in conflict');
            $table->json('proposed_changes')->comment('Changes proposed by conflicting user');
            $table->json('original_changes')->nullable()->comment('Original changes for comparison');
            $table->enum('conflict_severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending_review', 'resolved', 'rejected', 'expired'])->default('pending_review');
            $table->unsignedBigInteger('resolved_by')->nullable()->comment('Admin who resolved the conflict');
            $table->enum('resolution_action', ['accept_new', 'keep_original', 'merge_changes', 'custom'])->nullable();
            $table->text('resolution_notes')->nullable()->comment('Admin notes about the resolution');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('When this conflict expires if not resolved');
            $table->timestamps();

            // Indexes for performance
            $table->index('report_id');
            $table->index('status');
            $table->index('conflict_severity');
            $table->index('expires_at');
            $table->index(['status', 'created_at']);

            // Foreign key constraints
            $table->foreign('report_id')->references('id')->on('disaster_reports')->onDelete('cascade');
            $table->foreign('conflicting_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('original_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conflict_resolution_queue');
    }
};
