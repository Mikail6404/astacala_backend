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
        Schema::table('report_images', function (Blueprint $table) {
            // Add new columns for enhanced file storage (if they don't exist)
            if (! Schema::hasColumn('report_images', 'original_filename')) {
                $table->string('original_filename')->nullable();
            }
            if (! Schema::hasColumn('report_images', 'mime_type')) {
                $table->string('mime_type')->nullable();
            }
            if (! Schema::hasColumn('report_images', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }
            if (! Schema::hasColumn('report_images', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('report_images', 'platform')) {
                $table->string('platform')->default('mobile');
            }
            if (! Schema::hasColumn('report_images', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_images', function (Blueprint $table) {
            // Drop the added columns
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn([
                'original_filename',
                'mime_type',
                'is_primary',
                'uploaded_by',
                'platform',
                'metadata',
            ]);
        });
    }
};
