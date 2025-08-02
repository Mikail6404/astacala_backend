<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\TestAuthenticationCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class TestAuthenticationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        User::factory()->create([
            'email' => 'test@astacala.com',
            'password' => bcrypt('Test123!@#'),
            'name' => 'Test User'
        ]);
    }

    public function test_command_signature_and_description()
    {
        // Test that the command exists and can be instantiated
        $command = new TestAuthenticationCommand();

        $this->assertInstanceOf(TestAuthenticationCommand::class, $command);
        $this->assertStringContainsString('auth:test', $command->getName());
    }

    public function test_invalid_platform_returns_error()
    {
        $this->artisan('auth:test invalid-platform')
            ->expectsOutput('Platform must be: mobile, web, or both')
            ->assertExitCode(1);
    }

    public function test_mobile_authentication_test()
    {
        // Mock HTTP responses for successful authentication flow
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'fake-jwt-token-12345'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::response([
                'success' => true,
                'data' => [
                    'name' => 'Test User',
                    'email' => 'test@astacala.com'
                ]
            ], 200),

            '*/api/logout' => Http::response([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200)
        ]);

        $this->artisan('auth:test mobile --email=test@astacala.com --password=Test123!@#')
            ->expectsOutput('🔐 Astacala Rescue Authentication Test Suite')
            ->expectsOutput('📱 Testing Mobile Authentication')
            ->expectsOutput('1. Testing mobile login...')
            ->expectsOutput('   ✅ Login successful')
            ->assertExitCode(0);
    }

    public function test_web_authentication_test()
    {
        // Mock web login page response
        Http::fake([
            '*/login' => Http::response('<html><body>Login Page</body></html>', 200)
        ]);

        $this->artisan('auth:test web')
            ->expectsOutput('🌐 Testing Web Authentication')
            ->expectsOutput('   ✅ Web login page accessible')
            ->assertExitCode(0);
    }

    public function test_both_platforms_test()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'fake-jwt-token-12345'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::response([
                'success' => true,
                'data' => [
                    'name' => 'Test User',
                    'email' => 'test@astacala.com'
                ]
            ], 200),

            '*/api/logout' => Http::response([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200),

            '*/login' => Http::response('<html><body>Login Page</body></html>', 200)
        ]);

        $this->artisan('auth:test both')
            ->expectsOutput('📱 Testing Mobile Authentication')
            ->expectsOutput('🌐 Testing Web Authentication')
            ->assertExitCode(0);
    }

    public function test_load_test_option()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'fake-jwt-token-12345'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::response([
                'success' => true,
                'data' => [
                    'name' => 'Test User',
                    'email' => 'loadtest1@astacala.com'
                ]
            ], 200),

            '*/api/logout' => Http::response([
                'success' => true
            ], 200)
        ]);

        $this->artisan('auth:test mobile --load-test')
            ->expectsOutput('🚀 Running Load Test for Mobile Authentication')
            ->expectsOutput('📊 Load Test Results')
            ->assertExitCode(0);
    }

    public function test_network_test_option()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'fake-jwt-token-12345'
                    ]
                ]
            ], 200)
        ]);

        $this->artisan('auth:test mobile --network-test')
            ->expectsOutput('🌐 Running Network Condition Test for Mobile')
            ->assertExitCode(0);
    }

    public function test_rate_limiting_detection()
    {
        // Mock rate limit response
        Http::fake([
            '*/api/login' => Http::sequence()
                ->push(['success' => false], 401)
                ->push(['success' => false], 401)
                ->push(['success' => false], 401)
                ->push(['error' => 'Too Many Requests'], 429) // Rate limit triggered
        ]);

        $this->artisan('auth:test mobile --email=test@astacala.com --password=Test123!@#')
            ->expectsOutput('📱 Testing Mobile Authentication')
            ->assertExitCode(0);
    }

    public function test_authentication_failure_handling()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401)
        ]);

        $this->artisan('auth:test mobile --email=wrong@email.com --password=wrongpassword')
            ->expectsOutput('   ❌ Login failed: Invalid credentials')
            ->assertExitCode(0);
    }

    public function test_invalid_token_handling()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'valid-token'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::sequence()
                ->push(['success' => true, 'data' => ['name' => 'Test User']], 200) // Valid token
                ->push(['error' => 'Unauthorized'], 401) // Invalid token
                ->push(['error' => 'Unauthorized'], 401) // After logout
        ]);

        $this->artisan('auth:test mobile --email=test@astacala.com --password=Test123!@#')
            ->expectsOutput('4. Testing invalid token handling...')
            ->expectsOutput('   ✅ Invalid token properly rejected')
            ->assertExitCode(0);
    }

    public function test_logout_functionality()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'valid-token'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::sequence()
                ->push(['success' => true, 'data' => ['name' => 'Test User']], 200) // Before logout
                ->push(['error' => 'Unauthorized'], 401) // After logout
            ,

            '*/api/logout' => Http::response([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200)
        ]);

        $this->artisan('auth:test mobile --email=test@astacala.com --password=Test123!@#')
            ->expectsOutput('5. Testing logout...')
            ->expectsOutput('   ✅ Logout successful')
            ->expectsOutput('6. Testing token after logout...')
            ->expectsOutput('   ✅ Token properly invalidated after logout')
            ->assertExitCode(0);
    }

    public function test_performance_recommendations()
    {
        // Test excellent performance scenario
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'test-token'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::response([
                'success' => true,
                'data' => ['name' => 'Load Test User']
            ], 200),

            '*/api/logout' => Http::response(['success' => true], 200)
        ]);

        $this->artisan('auth:test mobile --load-test')
            ->expectsOutput('📊 Load Test Results')
            ->assertExitCode(0);
    }

    public function test_network_timeout_handling()
    {
        Http::fake([
            '*/api/login' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            }
        ]);

        $this->artisan('auth:test mobile --network-test')
            ->expectsOutput('🌐 Running Network Condition Test for Mobile')
            ->assertExitCode(0);
    }

    public function test_test_user_creation()
    {
        $this->assertDatabaseMissing('users', [
            'email' => 'newtest@astacala.com'
        ]);

        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'test-token'
                    ]
                ]
            ], 200)
        ]);

        $this->artisan('auth:test mobile --email=newtest@astacala.com --password=NewTest123!')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'newtest@astacala.com',
            'name' => 'Test User (Mobile)'
        ]);
    }

    public function test_results_display_formatting()
    {
        Http::fake([
            '*/api/login' => Http::response([
                'success' => true,
                'data' => [
                    'tokens' => [
                        'access_token' => 'test-token'
                    ]
                ]
            ], 200),

            '*/api/profile' => Http::response([
                'success' => true,
                'data' => ['name' => 'Test User']
            ], 200),

            '*/api/logout' => Http::response(['success' => true], 200)
        ]);

        $this->artisan('auth:test mobile --email=test@astacala.com --password=Test123!@#')
            ->expectsOutput('📊 Mobile Authentication Test Results')
            ->assertExitCode(0);
    }

    public function test_command_with_custom_token()
    {
        Http::fake([
            '*/api/profile' => Http::response([
                'success' => true,
                'data' => ['name' => 'Test User']
            ], 200)
        ]);

        $this->artisan('auth:test mobile --token=custom-token-12345')
            ->expectsOutput('📱 Testing Mobile Authentication')
            ->assertExitCode(0);
    }
}
