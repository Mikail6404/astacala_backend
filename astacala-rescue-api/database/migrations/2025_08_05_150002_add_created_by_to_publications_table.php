<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds missing fields to publications table for web dashboard compatibility
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Add created_by field to track publication creator (after author_id since that exists)
            $table->unsignedBigInteger('created_by')->nullable()->after('author_id')
                ->comment('User ID who created this publication');
            $table->string('creator_name', 255)->nullable()->after('created_by')
                ->comment('Cached creator name for quick display');

            // Add foreign key constraint
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Add index for improved query performance
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by']);
            $table->dropColumn(['created_by', 'creator_name']);
        });
    }
};
