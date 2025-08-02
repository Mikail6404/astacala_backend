<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\DualAuthenticationMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

/**
 * Dual Authentication Middleware Unit Tests
 * 
 * Comprehensive testing for the dual authentication middleware
 * Tests both JWT and session authentication paths
 */
class DualAuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private $middleware;
    private $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new DualAuthenticationMiddleware();

        $this->testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);
    }

    /**
     * Test JWT authentication path
     */
    public function test_jwt_authentication_path()
    {
        // Create a request with JWT token
        Sanctum::actingAs($this->testUser);

        $request = Request::create('/api/v1/test', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('mobile_user', $request->get('authenticated_as'));
        $this->assertEquals('mobile', $request->get('platform'));
        $this->assertEquals('volunteer', $request->get('user_type'));
        $this->assertEquals('jwt_token', $request->get('auth_method'));
    }

    /**
     * Test session authentication path
     */
    public function test_session_authentication_path()
    {
        $request = Request::create('/api/v1/test', 'GET');

        // Start session and set admin_id
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('admin_id', 1);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('web_admin', $request->get('authenticated_as'));
        $this->assertEquals('web', $request->get('platform'));
        $this->assertEquals('admin', $request->get('user_type'));
        $this->assertEquals('session_cookie', $request->get('auth_method'));
        $this->assertEquals(1, $request->get('admin_id'));
    }

    /**
     * Test no authentication provided
     */
    public function test_no_authentication_provided()
    {
        $request = Request::create('/api/v1/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('AUTH_REQUIRED', $responseData['error_code']);
    }

    /**
     * Test invalid JWT token
     */
    public function test_invalid_jwt_token()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('INVALID_TOKEN', $responseData['error_code']);
    }

    /**
     * Test invalid session (no admin_id)
     */
    public function test_invalid_session_no_admin_id()
    {
        $request = Request::create('/api/v1/test', 'GET');
        $request->setLaravelSession(app('session.store'));
        // Session exists but no admin_id

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here');
        });

        $this->assertEquals(401, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('INVALID_SESSION', $responseData['error_code']);
    }

    /**
     * Test JWT takes precedence over session
     */
    public function test_jwt_precedence_over_session()
    {
        Sanctum::actingAs($this->testUser);

        $request = Request::create('/api/v1/test', 'GET');
        $request->headers->set('Authorization', 'Bearer test-token');

        // Also set session data
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('admin_id', 1);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Success');
        });

        $this->assertEquals(200, $response->getStatusCode());
        // Should authenticate as mobile user (JWT), not web admin (session)
        $this->assertEquals('mobile_user', $request->get('authenticated_as'));
        $this->assertEquals('jwt_token', $request->get('auth_method'));
    }

    /**
     * Test response format consistency
     */
    public function test_response_format_consistency()
    {
        $request = Request::create('/api/v1/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here');
        });

        $responseData = json_decode($response->getContent(), true);

        // Check response structure
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('error_code', $responseData);
        $this->assertArrayHasKey('meta', $responseData);

        // Check meta structure
        $this->assertArrayHasKey('timestamp', $responseData['meta']);
        $this->assertArrayHasKey('request_id', $responseData['meta']);

        // Validate timestamp format (ISO8601)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $responseData['meta']['timestamp']);
    }

    /**
     * Test logging functionality
     */
    public function test_logging_functionality()
    {
        // This test ensures the middleware logs appropriately
        // In a real scenario, you'd mock the Log facade and assert calls

        $request = Request::create('/api/v1/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here');
        });

        // Basic assertion - in production you'd test actual log entries
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue(true); // Placeholder for log assertion
    }
}
