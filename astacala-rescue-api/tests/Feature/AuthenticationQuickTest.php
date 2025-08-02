<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Simplified Authentication Test
 * 
 * Quick validation of authentication components working
 */
class AuthenticationQuickTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test mobile JWT authentication works
     */
    public function test_mobile_jwt_authentication_works()
    {
        // Create test user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Test login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'tokens' => [
                    'accessToken',
                    'tokenType',
                    'expiresIn'
                ]
            ]
        ]);

        // Test authenticated endpoint
        $token = $response->json('data.tokens.accessToken');

        $authResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

        $authResponse->assertStatus(200);
        $authResponse->assertJson([
            'success' => true
        ]);
    }

    /**
     * Test dual authentication middleware
     */
    public function test_dual_authentication_middleware_exists()
    {
        $middleware = app(\App\Http\Middleware\DualAuthenticationMiddleware::class);
        $this->assertInstanceOf(\App\Http\Middleware\DualAuthenticationMiddleware::class, $middleware);
    }

    /**
     * Test user context service
     */
    public function test_user_context_service_exists()
    {
        $service = app(\App\Services\UserContextService::class);
        $this->assertInstanceOf(\App\Services\UserContextService::class, $service);
    }
}
