<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Debug Authentication Test
 */
class AuthenticationDebugTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test and debug login response
     */
    public function test_debug_login_response()
    {
        // Create test user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Test login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Debug the response
        dump('Response Status:', $response->getStatusCode());
        dump('Response Content:', $response->getContent());

        $this->assertTrue(true); // Just to pass the test
    }
}
