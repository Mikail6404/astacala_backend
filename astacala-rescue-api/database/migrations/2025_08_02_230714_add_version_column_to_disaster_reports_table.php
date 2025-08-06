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
            // Add version column for optimistic locking
            $table->integer('version')->default(1)->after('id')->comment('Version for optimistic locking');

            // Add index for better performance on version checks
            $table->index(['id', 'version']);

            // Add audit fields for better tracking
            $table->timestamp('last_modified_at')->nullable()->after('updated_at')->comment('Explicit modification timestamp');
            $table->unsignedBigInteger('last_modified_by')->nullable()->after('last_modified_at')->comment('User who last modified');
            $table->string('last_modified_platform', 50)->nullable()->after('last_modified_by')->comment('Platform used for last modification');

            // Foreign key for last modified by
            $table->foreign('last_modified_by')->references('id')->on('users')->onDelete('set null');

            // Index for modification tracking
            $table->index('last_modified_at');
            $table->index('last_modified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['last_modified_by']);

            // Drop indexes
            $table->dropIndex(['id', 'version']);
            $table->dropIndex(['last_modified_at']);
            $table->dropIndex(['last_modified_by']);

            // Drop columns
            $table->dropColumn([
                'version',
                'last_modified_at',
                'last_modified_by',
                'last_modified_platform',
            ]);
        });
    }
};
