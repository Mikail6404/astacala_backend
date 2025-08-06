<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing web app compatibility fields to users table
     * that are required for proper dashboard functionality.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Web app compatibility fields for admin and volunteer profiles
            $table->string('place_of_birth', 255)->nullable()->after('birth_date')->comment('Tempat lahir (birth place)');
            $table->string('member_number', 100)->nullable()->after('place_of_birth')->comment('No anggota/member number');

            // Add indexes for improved query performance
            $table->index('member_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['member_number']);
            $table->dropColumn(['place_of_birth', 'member_number']);
        });
    }
};
