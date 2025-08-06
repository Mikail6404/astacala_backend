<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAuthenticationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auth:test {platform} {--email=} {--password=} {--token=} {--load-test} {--network-test}';

    /**
     * The console command description.
     */
    protected $description = 'Comprehensive authentication testing for mobile and web platforms';

    protected $baseUrl;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('app.url');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Astacala Rescue Authentication Test Suite');
        $this->info('==========================================');

        $platform = $this->argument('platform');

        if (! in_array($platform, ['mobile', 'web', 'both'])) {
            $this->error('Platform must be: mobile, web, or both');

            return 1;
        }

        if ($this->option('load-test')) {
            return $this->runLoadTest($platform);
        }

        if ($this->option('network-test')) {
            return $this->runNetworkTest($platform);
        }

        if ($platform === 'both') {
            $this->testMobileAuthentication();
            $this->newLine();
            $this->testWebAuthentication();
        } elseif ($platform === 'mobile') {
            $this->testMobileAuthentication();
        } else {
            $this->testWebAuthentication();
        }

        $this->newLine();
        $this->info('✅ Authentication testing completed');

        return 0;
    }

    protected function testMobileAuthentication()
    {
        $this->info('📱 Testing Mobile Authentication');
        $this->info('------------------------------');

        $email = $this->option('email') ?? 'mobile-test@astacala.com';
        $password = $this->option('password') ?? 'Test123!@#';
        $token = $this->option('token');

        // Create test user if not exists
        $this->createTestUser($email, $password, 'mobile');

        $results = [
            'registration' => false,
            'login' => false,
            'authenticated_request' => false,
            'rate_limiting' => false,
            'logout' => false,
        ];

        // Test 1: Mobile Login
        $this->info('1. Testing mobile login...');
        $loginResult = $this->testMobileLogin($email, $password);

        if ($loginResult['success']) {
            $results['login'] = true;
            $token = $loginResult['token'];
            $this->info('   ✅ Login successful');
            $this->line('   🔑 Token: '.substr($token, 0, 20).'...');
        } else {
            $this->error('   ❌ Login failed: '.$loginResult['message']);

            return;
        }

        // Test 2: Authenticated Request
        $this->info('2. Testing authenticated request...');
        $profileResult = $this->testMobileProfile($token);

        if ($profileResult['success']) {
            $results['authenticated_request'] = true;
            $this->info('   ✅ Authenticated request successful');
            $this->line('   👤 User: '.$profileResult['data']['name'] ?? 'Unknown');
        } else {
            $this->error('   ❌ Authenticated request failed: '.$profileResult['message']);
        }

        // Test 3: Rate Limiting
        $this->info('3. Testing rate limiting...');
        $rateLimitResult = $this->testMobileRateLimit($email, $password);

        if ($rateLimitResult['triggered']) {
            $results['rate_limiting'] = true;
            $this->info('   ✅ Rate limiting working');
            $this->line('   ⏱️  Rate limit headers detected');
        } else {
            $this->warn('   ⚠️  Rate limiting not triggered (may need more requests)');
        }

        // Test 4: Invalid Token
        $this->info('4. Testing invalid token handling...');
        $invalidResult = $this->testInvalidToken();

        if (! $invalidResult['success']) {
            $this->info('   ✅ Invalid token properly rejected');
        } else {
            $this->error('   ❌ Invalid token was accepted');
        }

        // Test 5: Logout
        $this->info('5. Testing logout...');
        $logoutResult = $this->testMobileLogout($token);

        if ($logoutResult['success']) {
            $results['logout'] = true;
            $this->info('   ✅ Logout successful');
        } else {
            $this->error('   ❌ Logout failed: '.$logoutResult['message']);
        }

        // Test 6: Token After Logout
        $this->info('6. Testing token after logout...');
        $afterLogoutResult = $this->testMobileProfile($token);

        if (! $afterLogoutResult['success']) {
            $this->info('   ✅ Token properly invalidated after logout');
        } else {
            $this->error('   ❌ Token still valid after logout');
        }

        $this->displayResults('Mobile', $results);
    }

    protected function testWebAuthentication()
    {
        $this->info('🌐 Testing Web Authentication');
        $this->info('----------------------------');

        $this->warn('Web authentication testing requires browser session management.');
        $this->info('For comprehensive web testing, use:');
        $this->line('- Browser developer tools');
        $this->line('- Postman with session cookies');
        $this->line('- Laravel Dusk for automated browser testing');

        // Test basic web endpoints accessibility
        $this->info('Testing web endpoint accessibility...');

        try {
            $response = Http::timeout(5)->get($this->baseUrl.'/login');

            if ($response->successful()) {
                $this->info('   ✅ Web login page accessible');
            } else {
                $this->error('   ❌ Web login page not accessible');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Web endpoint error: '.$e->getMessage());
        }
    }

    protected function testMobileLogin($email, $password)
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl.'/api/auth/login', [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'token' => $data['data']['tokens']['accessToken'] ?? null,
                    'data' => $data,
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'Login failed',
                    'status' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testMobileProfile($token)
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl.'/api/auth/me');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()['data'] ?? [],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'Profile request failed',
                    'status' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testMobileRateLimit($email, $password)
    {
        $this->info('   Sending rapid login requests to trigger rate limiting...');

        $rateLimitTriggered = false;
        $headers = null;

        // Send 10 rapid requests
        for ($i = 1; $i <= 10; $i++) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'X-Platform' => 'mobile',
                        'Accept' => 'application/json',
                    ])
                    ->post($this->baseUrl.'/api/auth/login', [
                        'email' => 'invalid-'.$i.'@example.com',
                        'password' => 'wrong-password',
                    ]);

                $headers = $response->headers();

                if ($response->status() === 429) {
                    $rateLimitTriggered = true;
                    $this->line("   Rate limit triggered on attempt $i");
                    break;
                }

                if (
                    isset($headers['X-RateLimit-Remaining'][0]) &&
                    (int) $headers['X-RateLimit-Remaining'][0] < 3
                ) {
                    $this->line('   Rate limit approaching: '.$headers['X-RateLimit-Remaining'][0].' remaining');
                }
            } catch (\Exception $e) {
                $this->line("   Request $i failed: ".$e->getMessage());
            }

            usleep(100000); // 100ms delay
        }

        return [
            'triggered' => $rateLimitTriggered,
            'headers' => $headers,
        ];
    }

    protected function testInvalidToken()
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer invalid-token-12345',
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl.'/api/auth/me');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testMobileLogout($token)
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl.'/api/auth/logout');

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Logout successful' : 'Logout failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function createTestUser($email, $password, $type = 'mobile')
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->info("Creating test user: $email");

            User::create([
                'name' => 'Test User ('.ucfirst($type).')',
                'email' => $email,
                'password' => bcrypt($password),
                'email_verified_at' => now(),
            ]);
        }
    }

    protected function runLoadTest($platform)
    {
        $this->info('🚀 Running Load Test for '.ucfirst($platform).' Authentication');
        $this->info('==================================================');

        $concurrentUsers = 20;
        $requestsPerUser = 5;

        $this->info("Simulating $concurrentUsers concurrent users, $requestsPerUser requests each");

        $startTime = microtime(true);
        $results = [];

        // Create test users
        for ($i = 1; $i <= $concurrentUsers; $i++) {
            $email = "loadtest{$i}@astacala.com";
            $this->createTestUser($email, 'LoadTest123!', $platform);
        }

        $this->withProgressBar(range(1, $concurrentUsers), function ($userIndex) use ($requestsPerUser, &$results) {
            $email = "loadtest{$userIndex}@astacala.com";
            $password = 'LoadTest123!';

            $userResults = [
                'login_times' => [],
                'profile_times' => [],
                'errors' => 0,
            ];

            for ($j = 1; $j <= $requestsPerUser; $j++) {
                // Test login
                $loginStart = microtime(true);
                $loginResult = $this->testMobileLogin($email, $password);
                $loginTime = (microtime(true) - $loginStart) * 1000;

                if ($loginResult['success']) {
                    $userResults['login_times'][] = $loginTime;

                    // Test profile request
                    $profileStart = microtime(true);
                    $profileResult = $this->testMobileProfile($loginResult['token']);
                    $profileTime = (microtime(true) - $profileStart) * 1000;

                    if ($profileResult['success']) {
                        $userResults['profile_times'][] = $profileTime;
                    } else {
                        $userResults['errors']++;
                    }

                    // Logout
                    $this->testMobileLogout($loginResult['token']);
                } else {
                    $userResults['errors']++;
                }

                usleep(rand(100000, 500000)); // Random delay 100-500ms
            }

            $results[$userIndex] = $userResults;
        });

        $totalTime = (microtime(true) - $startTime);

        $this->newLine(2);
        $this->displayLoadTestResults($results, $totalTime, $concurrentUsers, $requestsPerUser);

        return 0;
    }

    protected function runNetworkTest($platform)
    {
        $this->info('🌐 Running Network Condition Test for '.ucfirst($platform));
        $this->info('=============================================');

        $testScenarios = [
            'normal' => ['timeout' => 10, 'description' => 'Normal conditions'],
            'slow' => ['timeout' => 30, 'description' => 'Slow network (30s timeout)'],
            'very_slow' => ['timeout' => 60, 'description' => 'Very slow network (60s timeout)'],
        ];

        $email = 'networktest@astacala.com';
        $password = 'NetworkTest123!';
        $this->createTestUser($email, $password, $platform);

        foreach ($testScenarios as $scenario => $config) {
            $this->info("\nTesting: ".$config['description']);

            $startTime = microtime(true);

            try {
                $response = Http::timeout($config['timeout'])
                    ->withHeaders([
                        'X-Platform' => $platform,
                        'Accept' => 'application/json',
                    ])
                    ->post($this->baseUrl.'/api/auth/login', [
                        'email' => $email,
                        'password' => $password,
                    ]);

                $responseTime = (microtime(true) - $startTime) * 1000;

                if ($response->successful()) {
                    $this->info('   ✅ Success - Response time: '.round($responseTime, 2).'ms');
                } else {
                    $this->error('   ❌ Failed - Status: '.$response->status());
                }
            } catch (\Exception $e) {
                $responseTime = (microtime(true) - $startTime) * 1000;
                $this->error('   ❌ Timeout/Error after '.round($responseTime, 2).'ms: '.$e->getMessage());
            }
        }

        return 0;
    }

    protected function displayResults($platform, $results)
    {
        $this->newLine();
        $this->info("📊 $platform Authentication Test Results");
        $this->info('=====================================');

        $total = count($results);
        $passed = count(array_filter($results));
        $percentage = round(($passed / $total) * 100, 1);

        foreach ($results as $test => $result) {
            $status = $result ? '✅' : '❌';
            $this->line("$status ".ucwords(str_replace('_', ' ', $test)));
        }

        $this->newLine();
        $color = $percentage >= 80 ? 'info' : ($percentage >= 60 ? 'warn' : 'error');
        $this->{$color}("Overall Success Rate: $passed/$total ($percentage%)");
    }

    protected function displayLoadTestResults($results, $totalTime, $concurrentUsers, $requestsPerUser)
    {
        $this->info('📊 Load Test Results');
        $this->info('===================');

        $allLoginTimes = [];
        $allProfileTimes = [];
        $totalErrors = 0;

        foreach ($results as $userResult) {
            $allLoginTimes = array_merge($allLoginTimes, $userResult['login_times']);
            $allProfileTimes = array_merge($allProfileTimes, $userResult['profile_times']);
            $totalErrors += $userResult['errors'];
        }

        $totalRequests = $concurrentUsers * $requestsPerUser * 2; // login + profile
        $successfulRequests = $totalRequests - $totalErrors;
        $successRate = round(($successfulRequests / $totalRequests) * 100, 1);

        $this->line('Total Time: '.round($totalTime, 2).' seconds');
        $this->line("Concurrent Users: $concurrentUsers");
        $this->line('Requests per User: '.($requestsPerUser * 2).' (login + profile)');
        $this->line("Total Requests: $totalRequests");
        $this->line("Successful Requests: $successfulRequests");
        $this->line("Failed Requests: $totalErrors");
        $this->line("Success Rate: $successRate%");
        $this->line('Requests per Second: '.round($totalRequests / $totalTime, 2));

        if (! empty($allLoginTimes)) {
            $avgLogin = round(array_sum($allLoginTimes) / count($allLoginTimes), 2);
            $maxLogin = round(max($allLoginTimes), 2);
            $minLogin = round(min($allLoginTimes), 2);

            $this->newLine();
            $this->line('Login Performance:');
            $this->line("  Average: {$avgLogin}ms");
            $this->line("  Min: {$minLogin}ms");
            $this->line("  Max: {$maxLogin}ms");
        }

        if (! empty($allProfileTimes)) {
            $avgProfile = round(array_sum($allProfileTimes) / count($allProfileTimes), 2);
            $maxProfile = round(max($allProfileTimes), 2);
            $minProfile = round(min($allProfileTimes), 2);

            $this->newLine();
            $this->line('Profile Request Performance:');
            $this->line("  Average: {$avgProfile}ms");
            $this->line("  Min: {$minProfile}ms");
            $this->line("  Max: {$maxProfile}ms");
        }

        // Performance recommendations
        $this->newLine();
        if ($successRate >= 95 && ! empty($allLoginTimes) && array_sum($allLoginTimes) / count($allLoginTimes) < 1000) {
            $this->info('🎉 Excellent performance! System handles load well.');
        } elseif ($successRate >= 80) {
            $this->warn('⚠️ Good performance with some issues. Consider optimization.');
        } else {
            $this->error('❌ Poor performance. System needs optimization for production load.');
        }
    }
}
