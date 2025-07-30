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
        Schema::create('forum_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disaster_report_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_message_id')->nullable()->constrained('forum_messages')->onDelete('cascade');
            $table->text('message');
            $table->enum('message_type', ['text', 'emergency', 'update', 'question'])->default('text');
            $table->enum('priority_level', ['low', 'normal', 'high', 'emergency'])->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['disaster_report_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['parent_message_id']);
            $table->index(['priority_level', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_messages');
    }
};
