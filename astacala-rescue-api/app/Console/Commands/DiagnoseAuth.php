<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DiagnoseAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diagnose:auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose authentication issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== AUTHENTICATION SYSTEM DIAGNOSIS ===');

        // Check users table
        $userCount = User::count();
        $this->info("Users in database: $userCount");

        if ($userCount === 0) {
            $this->error('NO USERS FOUND - Creating test user...');

            $testUser = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
                'role' => 'VOLUNTEER'
            ]);

            $this->info("Test user created: {$testUser->email}");
        } else {
            $firstUser = User::first();
            $this->info("First user email: {$firstUser->email}");
            $this->info("Password hash exists: " . (!empty($firstUser->password) ? 'YES' : 'NO'));

            // Test password verification
            if (Hash::check('password123', $firstUser->password)) {
                $this->info("Password 'password123' works for {$firstUser->email}");
            } else {
                $this->warn("Password 'password123' does NOT work for {$firstUser->email}");

                // Update password to known value
                $firstUser->password = Hash::make('password123');
                $firstUser->save();
                $this->info("Updated password for {$firstUser->email} to 'password123'");
            }
        }

        // Test direct authentication
        $this->info("\n=== TESTING AUTHENTICATION CONTROLLER ===");

        $testEmail = User::first()->email ?? 'test@example.com';
        $this->info("Testing login for: $testEmail");

        // Simulate login request
        $credentials = [
            'email' => $testEmail,
            'password' => 'password123'
        ];

        if (auth()->attempt($credentials)) {
            $this->info("✅ Auth::attempt() works!");
            $user = auth()->user();
            $token = $user->createToken('diagnostic')->plainTextToken;
            $this->info("✅ Token created: " . substr($token, 0, 20) . "...");
        } else {
            $this->error("❌ Auth::attempt() failed!");
        }

        return 0;
    }
}
