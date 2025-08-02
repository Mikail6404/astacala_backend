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
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->enum('type', ['article', 'guide', 'announcement', 'report_summary'])->default('article');
            $table->string('category', 100);
            $table->text('tags')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->text('meta_description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            // User relationships
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('published_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('archived_by')->nullable()->constrained('users')->onDelete('set null');

            // Timestamps
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Analytics
            $table->unsignedBigInteger('view_count')->default(0);

            // Indexes for performance
            $table->index(['status', 'published_at']);
            $table->index(['type', 'category']);
            $table->index(['author_id', 'status']);
            $table->fullText(['title', 'content', 'tags']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
