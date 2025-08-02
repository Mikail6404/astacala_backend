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
        Schema::table('disaster_reports', function (Blueprint $table) {
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Update status enum to include VERIFIED
            $table->enum('status', ['PENDING', 'VERIFIED', 'ACTIVE', 'RESOLVED', 'REJECTED'])->default('PENDING')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            $table->dropForeign(['verified_by_admin_id']);
            $table->dropColumn(['verified_by_admin_id', 'verification_notes', 'verified_at']);

            // Revert status enum to original
            $table->enum('status', ['PENDING', 'ACTIVE', 'RESOLVED', 'REJECTED'])->default('PENDING')->change();
        });
    }
};
