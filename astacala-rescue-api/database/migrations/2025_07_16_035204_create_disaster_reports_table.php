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
        Schema::create('disaster_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('disaster_type', 50);
            $table->string('severity_level', 20);
            $table->enum('status', ['PENDING', 'ACTIVE', 'RESOLVED', 'REJECTED'])->default('PENDING');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('location_name')->nullable();
            $table->text('address')->nullable();
            $table->integer('estimated_affected')->default(0);
            $table->string('weather_condition', 100)->nullable();
            $table->string('team_name')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable();
            $table->timestamp('incident_timestamp');
            $table->timestamps();

            // Indexes
            $table->index(['latitude', 'longitude']);
            $table->index('status');
            $table->index('severity_level');
            $table->index('disaster_type');
            $table->index('incident_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disaster_reports');
    }
};
