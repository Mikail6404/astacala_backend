<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test user for mobile testing
        User::firstOrCreate([
            'email' => 'test@astacala.com'
        ], [
            'name' => 'Test User Mobile',
            'password' => Hash::make('password123'),
            'phone' => '081234567890',
            'role' => 'VOLUNTEER',
            'is_active' => 1,
        ]);

        // Create admin test user
        User::firstOrCreate([
            'email' => 'admin@astacala.com'
        ], [
            'name' => 'Admin Test User',
            'password' => Hash::make('admin123'),
            'phone' => '081234567891',
            'role' => 'ADMIN',
            'is_active' => 1,
        ]);

        $this->command->info('Test users created successfully');
    }
}
