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
            // Add new columns for enhanced file storage
            $table->string('thumbnail_path')->nullable()->after('thumbnail_url');
            $table->string('original_filename')->nullable()->after('thumbnail_path');
            $table->string('mime_type')->nullable()->after('original_filename');
            $table->boolean('is_primary')->default(false)->after('upload_order');
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('is_primary');
            $table->string('platform')->default('mobile')->after('uploaded_by');
            $table->json('metadata')->nullable()->after('platform');

            // Add foreign key for uploaded_by
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });

        // Rename columns in a separate operation to avoid conflicts
        Schema::table('report_images', function (Blueprint $table) {
            $table->renameColumn('report_id', 'disaster_report_id');
            $table->renameColumn('image_url', 'image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_images', function (Blueprint $table) {
            // Rename columns back
            $table->renameColumn('disaster_report_id', 'report_id');
            $table->renameColumn('image_path', 'image_url');
        });

        Schema::table('report_images', function (Blueprint $table) {
            // Drop foreign key and new columns
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn([
                'thumbnail_path',
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
