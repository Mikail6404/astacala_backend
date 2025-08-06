<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Authentication Feature Tests for Astacala Rescue API
 *
 * Tests the complete authentication flow including:
 * - User registration
 * - User login
 * - User logout
 * - Token management
 * - Validation and error handling
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test database
        $this->artisan('migrate');
    }

    /**
     * Test successful user registration
     */
    public function test_user_can_register_successfully()
    {
        // Arrange
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'phone' => '+628123456789',
            'role' => 'VOLUNTEER',
        ];

        // Act
        $response = $this->postJson('/api/auth/register', $userData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'isActive',
                    ],
                    'tokens' => [
                        'accessToken',
                        'tokenType',
                        'expiresIn',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                        'role' => 'VOLUNTEER',
                    ],
                ],
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'VOLUNTEER',
        ]);

        // Verify password is hashed
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Test registration validation errors
     */
    public function test_registration_validation_errors()
    {
        // Test empty data
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // Test invalid email format
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test duplicate email
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test short password
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test successful user login
     */
    public function test_user_can_login_successfully()
    {
        // Arrange - Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Act
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'profilePictureUrl',
                    ],
                    'tokens' => [
                        'accessToken',
                        'tokenType',
                        'expiresIn',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);

        // Verify last_login is updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        $updatedUser = User::find($user->id);
        $this->assertNotNull($updatedUser->last_login);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_with_invalid_credentials()
    {
        // Arrange - Create a user
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Act - Wrong password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);

        // Act - Non-existent email
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test login validation errors
     */
    public function test_login_validation_errors()
    {
        // Test empty data
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Test invalid email format
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test successful logout
     */
    public function test_user_can_logout_successfully()
    {
        // Arrange - Create and authenticate user
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/auth/logout');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Verify token is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * Test logout without authentication
     */
    public function test_logout_without_authentication()
    {
        // Act
        $response = $this->postJson('/api/auth/logout');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * Test get current user information
     */
    public function test_get_current_user_information()
    {
        // Arrange - Create and authenticate user
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+628123456789',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'address',
                    'profilePictureUrl',
                    'role',
                    'emergencyContacts',
                    'joinedAt',
                    'isActive',
                    'lastLogin',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'phone' => '+628123456789',
                ],
            ]);
    }

    /**
     * Test authentication performance (should be fast)
     */
    public function test_authentication_performance()
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Act & Assert - Login performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1000, $duration);
        $response->assertStatus(200);

        // Act & Assert - Token validation performance
        $token = $response->json('data.tokens.accessToken');

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Token validation should be very fast (less than 100ms)
        $this->assertLessThan(100, $duration);
        $response->assertStatus(200);
    }

    /**
     * Test token expiration handling
     */
    public function test_token_expiration_handling()
    {
        // This test would require custom token implementation with expiration
        // For now, test that tokens work correctly

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Valid token should work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200);

        // Revoked token should not work
        $user->tokens()->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test concurrent authentication requests
     */
    public function test_concurrent_authentication_requests()
    {
        // Create multiple users for concurrent testing
        $users = User::factory()->count(3)->create();

        $responses = [];

        // Simulate concurrent login requests
        foreach ($users as $user) {
            $responses[] = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);
        }

        // All should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    /**
     * Test authentication with different user roles
     */
    public function test_authentication_with_different_roles()
    {
        $roles = ['VOLUNTEER', 'COORDINATOR', 'ADMIN'];

        foreach ($roles as $role) {
            // Create user with specific role
            $user = User::factory()->create([
                'role' => $role,
                'email' => strtolower($role).'@example.com',
            ]);

            // Login should work for all roles
            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'role' => $role,
                        ],
                    ],
                ]);
        }
    }

    /**
     * Test malformed authentication requests
     */
    public function test_malformed_authentication_requests()
    {
        // Test with malformed JSON
        $response = $this->postJson('/api/auth/login', 'invalid-json');
        $response->assertStatus(400);

        // Test with missing Content-Type header
        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        // Should handle gracefully
    }
}
