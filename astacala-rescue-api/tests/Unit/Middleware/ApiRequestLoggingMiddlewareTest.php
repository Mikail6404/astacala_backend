<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiRequestLoggingMiddleware;
use App\Services\SuspiciousActivityMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ApiRequestLoggingMiddlewareTest extends TestCase
{
    protected $middleware;

    protected $securityMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityMonitor = Mockery::mock(SuspiciousActivityMonitoringService::class);
        $this->middleware = new ApiRequestLoggingMiddleware($this->securityMonitor);
    }

    /** @test */
    public function it_logs_incoming_api_requests()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'TestAgent/1.0');
        $request->headers->set('X-Platform', 'mobile');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Test response', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get('X-Request-ID'));
    }

    /** @test */
    public function it_logs_authentication_information()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return isset($data['authentication']) &&
                    isset($data['authentication']['authenticated_as']) &&
                    isset($data['authentication']['user_type']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/profile', 'GET');
        $request->merge([
            'authenticated_as' => 'user',
            'user_type' => 'mobile',
            'user_id' => 123,
        ]);

        $this->middleware->handle($request, function ($req) {
            return new Response('Profile data', 200);
        });
    }

    /** @test */
    public function it_sanitizes_sensitive_data_in_request_body()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return ! isset($data['request_body']) ||
                    (isset($data['request_body']['password']) &&
                        $data['request_body']['password'] === '[REDACTED]');
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/some-endpoint', 'POST', [
            'username' => 'testuser',
            'password' => 'secret123',
            'other_field' => 'value',
        ]);

        $this->middleware->handle($request, function ($req) {
            return new Response('Success', 200);
        });
    }

    /** @test */
    public function it_does_not_log_request_body_for_sensitive_endpoints()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return ! isset($data['request_body']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/auth/login', 'POST', [
            'username' => 'testuser',
            'password' => 'secret123',
        ]);

        $this->middleware->handle($request, function ($req) {
            return new Response('Login success', 200);
        });
    }

    /** @test */
    public function it_logs_error_responses_with_warning_level()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::type('array'));

        Log::shouldReceive('warning')
            ->once()
            ->with('API Response', \Mockery::on(function ($data) {
                return $data['status_code'] === 404;
            }));

        $request = Request::create('/api/nonexistent', 'GET');

        $this->middleware->handle($request, function ($req) {
            return new Response('Not Found', 404);
        });
    }

    /** @test */
    public function it_logs_server_errors_with_error_level()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('API Response', \Mockery::on(function ($data) {
                return $data['status_code'] === 500;
            }));

        $request = Request::create('/api/error', 'GET');

        $this->middleware->handle($request, function ($req) {
            return new Response('Internal Server Error', 500);
        });
    }

    /** @test */
    public function it_detects_suspicious_user_agents()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn(['UNUSUAL_USER_AGENT']);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return in_array('UNUSUAL_USER_AGENT', $data['suspicious_indicators']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('User-Agent', 'sqlmap/1.0');

        $this->middleware->handle($request, function ($req) {
            return new Response('Test response', 200);
        });
    }

    /** @test */
    public function it_detects_authentication_probing()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn(['AUTHENTICATION_PROBING']);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return in_array('AUTHENTICATION_PROBING', $data['suspicious_indicators']);
            }));

        Log::shouldReceive('warning')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/auth/login', 'POST');

        $this->middleware->handle($request, function ($req) {
            return new Response('Unauthorized', 401);
        });
    }

    /** @test */
    public function it_detects_potential_sql_injection_attempts()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn(['SQL_INJECTION_PATTERNS']);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) {
                return in_array('SQL_INJECTION_PATTERNS', $data['suspicious_indicators']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::type('array'));

        $request = Request::create('/api/search', 'GET', [
            'query' => "'; DROP TABLE users; --",
        ]);

        $this->middleware->handle($request, function ($req) {
            return new Response('Search results', 200);
        });
    }

    /** @test */
    public function it_blocks_requests_from_blocked_clients()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn(['CLIENT_BLOCKED']);

        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Should not reach here', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Access denied', $responseData['error']);
    }

    /** @test */
    public function it_includes_performance_metrics_in_logs()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::on(function ($data) {
                return isset($data['response_time_ms']) &&
                    isset($data['memory_usage_mb']) &&
                    is_numeric($data['response_time_ms']) &&
                    is_numeric($data['memory_usage_mb']);
            }));

        $request = Request::create('/api/performance-test', 'GET');

        $this->middleware->handle($request, function ($req) {
            // Simulate some processing time
            usleep(1000); // 1ms

            return new Response('Performance test', 200);
        });
    }

    /** @test */
    public function it_handles_json_requests_properly()
    {
        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->twice()
            ->with(\Mockery::any(), \Mockery::type('array'));

        $request = Request::create(
            '/api/json-endpoint',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['key' => 'value'])
        );

        $response = $this->middleware->handle($request, function ($req) {
            return new Response(json_encode(['result' => 'success']), 200, [
                'Content-Type' => 'application/json',
            ]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_preserves_request_id_across_request_response_cycle()
    {
        $customRequestId = 'test-request-123';

        $this->securityMonitor->shouldReceive('analyzeRequest')
            ->once()
            ->andReturn([]);

        Log::shouldReceive('channel')
            ->with('api')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function ($data) use ($customRequestId) {
                return $data['request_id'] === $customRequestId;
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('API Response', \Mockery::on(function ($data) use ($customRequestId) {
                return $data['request_id'] === $customRequestId;
            }));

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-Request-ID', $customRequestId);

        $response = $this->middleware->handle($request, function ($req) {
            return new Response('Test response', 200);
        });

        $this->assertEquals($customRequestId, $response->headers->get('X-Request-ID'));
    }
}
