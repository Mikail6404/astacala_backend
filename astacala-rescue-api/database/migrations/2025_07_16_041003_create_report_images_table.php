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
        Schema::create('report_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('disaster_reports')->onDelete('cascade');
            $table->string('image_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->text('caption')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('upload_order')->default(0);
            $table->timestamps();

            // Index
            $table->index('report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_images');
    }
};
