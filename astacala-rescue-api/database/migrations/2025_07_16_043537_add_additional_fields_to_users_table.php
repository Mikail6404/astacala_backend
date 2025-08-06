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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->string('profile_picture_url', 500)->nullable()->after('address');
            $table->enum('role', ['VOLUNTEER', 'COORDINATOR', 'ADMIN'])->default('VOLUNTEER')->after('profile_picture_url');
            $table->json('emergency_contacts')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('emergency_contacts');
            $table->timestamp('last_login')->nullable()->after('is_active');

            // Add indexes
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'address',
                'profile_picture_url',
                'role',
                'emergency_contacts',
                'is_active',
                'last_login',
            ]);
            $table->dropIndex(['role']);
        });
    }
};
