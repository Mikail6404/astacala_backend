<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Carbon\Carbon;

class BenchmarkAuthenticationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'auth:benchmark {--users=10} {--duration=60} {--endpoints=all} {--output=console}';

    /**
     * The console command description.
     */
    protected $description = 'Performance benchmarking for authentication endpoints';

    protected $baseUrl;
    protected $metrics = [];
    protected $startTime;

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
        $this->info('⚡ Astacala Rescue Authentication Performance Benchmark');
        $this->info('==================================================');

        $users = (int) $this->option('users');
        $duration = (int) $this->option('duration');
        $endpoints = $this->option('endpoints');
        $output = $this->option('output');

        $this->info("Configuration:");
        $this->line("- Concurrent Users: $users");
        $this->line("- Test Duration: {$duration}s");
        $this->line("- Endpoints: $endpoints");
        $this->line("- Output Format: $output");

        $this->newLine();

        // Initialize metrics
        $this->initializeMetrics();

        // Create benchmark users
        $this->info('Creating benchmark users...');
        $this->createBenchmarkUsers($users);

        // Run benchmark
        $this->info('Starting performance benchmark...');
        $this->startTime = microtime(true);

        if ($endpoints === 'all' || $endpoints === 'login') {
            $this->benchmarkLoginEndpoint($users, $duration);
        }

        if ($endpoints === 'all' || $endpoints === 'profile') {
            $this->benchmarkProfileEndpoint($users, $duration);
        }

        if ($endpoints === 'all' || $endpoints === 'mixed') {
            $this->benchmarkMixedWorkload($users, $duration);
        }

        // Generate report
        $this->generateBenchmarkReport($output);

        $this->info('✅ Benchmarking completed');
        return 0;
    }

    protected function initializeMetrics()
    {
        $this->metrics = [
            'login' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'response_times' => [],
                'errors' => [],
                'throughput' => 0,
                'avg_response_time' => 0,
                'min_response_time' => PHP_FLOAT_MAX,
                'max_response_time' => 0,
                'p95_response_time' => 0,
                'p99_response_time' => 0
            ],
            'profile' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'response_times' => [],
                'errors' => [],
                'throughput' => 0,
                'avg_response_time' => 0,
                'min_response_time' => PHP_FLOAT_MAX,
                'max_response_time' => 0,
                'p95_response_time' => 0,
                'p99_response_time' => 0
            ],
            'mixed' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'response_times' => [],
                'errors' => [],
                'throughput' => 0,
                'avg_response_time' => 0,
                'min_response_time' => PHP_FLOAT_MAX,
                'max_response_time' => 0,
                'p95_response_time' => 0,
                'p99_response_time' => 0
            ],
            'system' => [
                'memory_usage' => [],
                'cpu_usage' => [],
                'database_queries' => [],
                'cache_hits' => 0,
                'cache_misses' => 0
            ]
        ];
    }

    protected function createBenchmarkUsers($count)
    {
        $this->withProgressBar(range(1, $count), function ($index) {
            $email = "benchmark{$index}@astacala.com";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Benchmark User {$index}",
                    'password' => bcrypt('Benchmark123!'),
                    'email_verified_at' => now(),
                ]
            );
        });

        $this->newLine();
        $this->info("Created $count benchmark users");
    }

    protected function benchmarkLoginEndpoint($users, $duration)
    {
        $this->info('🔐 Benchmarking Login Endpoint');
        $this->info('-----------------------------');

        $endTime = time() + $duration;
        $requestCount = 0;

        $this->withProgressBar([], function () use ($users, $endTime, &$requestCount) {
            while (time() < $endTime) {
                $userIndex = rand(1, $users);
                $email = "benchmark{$userIndex}@astacala.com";
                $password = 'Benchmark123!';

                $startTime = microtime(true);
                $memoryBefore = memory_get_usage();

                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'X-Platform' => 'mobile',
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ])
                        ->post($this->baseUrl . '/api/login', [
                            'email' => $email,
                            'password' => $password
                        ]);

                    $responseTime = (microtime(true) - $startTime) * 1000;
                    $memoryAfter = memory_get_usage();

                    $this->recordMetrics('login', $response, $responseTime, $memoryAfter - $memoryBefore);
                } catch (\Exception $e) {
                    $responseTime = (microtime(true) - $startTime) * 1000;
                    $this->recordError('login', $e->getMessage(), $responseTime);
                }

                $requestCount++;
                usleep(rand(10000, 50000)); // 10-50ms random delay
            }
        });

        $this->newLine();
        $this->info("Completed $requestCount login requests in {$duration}s");
    }

    protected function benchmarkProfileEndpoint($users, $duration)
    {
        $this->info('👤 Benchmarking Profile Endpoint');
        $this->info('-------------------------------');

        // First, login to get tokens
        $tokens = [];
        for ($i = 1; $i <= min($users, 10); $i++) {
            $email = "benchmark{$i}@astacala.com";
            $password = 'Benchmark123!';

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-Platform' => 'mobile',
                        'Accept' => 'application/json'
                    ])
                    ->post($this->baseUrl . '/api/login', [
                        'email' => $email,
                        'password' => $password
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $tokens[] = $data['data']['tokens']['access_token'] ?? null;
                }
            } catch (\Exception $e) {
                $this->warn("Failed to get token for user $i: " . $e->getMessage());
            }
        }

        if (empty($tokens)) {
            $this->error('No valid tokens obtained for profile testing');
            return;
        }

        $endTime = time() + $duration;
        $requestCount = 0;

        $this->withProgressBar([], function () use ($tokens, $endTime, &$requestCount) {
            while (time() < $endTime) {
                $token = $tokens[array_rand($tokens)];

                $startTime = microtime(true);
                $memoryBefore = memory_get_usage();

                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                            'X-Platform' => 'mobile',
                            'Accept' => 'application/json'
                        ])
                        ->get($this->baseUrl . '/api/profile');

                    $responseTime = (microtime(true) - $startTime) * 1000;
                    $memoryAfter = memory_get_usage();

                    $this->recordMetrics('profile', $response, $responseTime, $memoryAfter - $memoryBefore);
                } catch (\Exception $e) {
                    $responseTime = (microtime(true) - $startTime) * 1000;
                    $this->recordError('profile', $e->getMessage(), $responseTime);
                }

                $requestCount++;
                usleep(rand(10000, 50000)); // 10-50ms random delay
            }
        });

        $this->newLine();
        $this->info("Completed $requestCount profile requests in {$duration}s");
    }

    protected function benchmarkMixedWorkload($users, $duration)
    {
        $this->info('🔄 Benchmarking Mixed Workload');
        $this->info('-----------------------------');

        $endTime = time() + $duration;
        $requestCount = 0;

        // Get some tokens for authenticated requests
        $tokens = [];
        for ($i = 1; $i <= min($users, 5); $i++) {
            $email = "benchmark{$i}@astacala.com";
            $password = 'Benchmark123!';

            try {
                $response = Http::timeout(10)
                    ->withHeaders(['X-Platform' => 'mobile', 'Accept' => 'application/json'])
                    ->post($this->baseUrl . '/api/login', [
                        'email' => $email,
                        'password' => $password
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $tokens[] = $data['data']['tokens']['access_token'] ?? null;
                }
            } catch (\Exception $e) {
                // Continue with fewer tokens
            }
        }

        $this->withProgressBar([], function () use ($users, $tokens, $endTime, &$requestCount) {
            while (time() < $endTime) {
                $action = rand(1, 100);

                if ($action <= 40) {
                    // 40% login requests
                    $this->performLoginRequest($users);
                } elseif ($action <= 80 && !empty($tokens)) {
                    // 40% profile requests (if tokens available)
                    $this->performProfileRequest($tokens);
                } else {
                    // 20% mixed other authenticated requests
                    if (!empty($tokens)) {
                        $this->performMixedRequest($tokens);
                    } else {
                        $this->performLoginRequest($users);
                    }
                }

                $requestCount++;
                usleep(rand(5000, 25000)); // 5-25ms random delay
            }
        });

        $this->newLine();
        $this->info("Completed $requestCount mixed requests in {$duration}s");
    }

    protected function performLoginRequest($users)
    {
        $userIndex = rand(1, $users);
        $email = "benchmark{$userIndex}@astacala.com";
        $password = 'Benchmark123!';

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Platform' => 'mobile', 'Accept' => 'application/json'])
                ->post($this->baseUrl . '/api/login', [
                    'email' => $email,
                    'password' => $password
                ]);

            $responseTime = (microtime(true) - $startTime) * 1000;
            $memoryAfter = memory_get_usage();

            $this->recordMetrics('mixed', $response, $responseTime, $memoryAfter - $memoryBefore);
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->recordError('mixed', $e->getMessage(), $responseTime);
        }
    }

    protected function performProfileRequest($tokens)
    {
        $token = $tokens[array_rand($tokens)];

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json'
                ])
                ->get($this->baseUrl . '/api/profile');

            $responseTime = (microtime(true) - $startTime) * 1000;
            $memoryAfter = memory_get_usage();

            $this->recordMetrics('mixed', $response, $responseTime, $memoryAfter - $memoryBefore);
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->recordError('mixed', $e->getMessage(), $responseTime);
        }
    }

    protected function performMixedRequest($tokens)
    {
        // Simulate other authenticated endpoints that might exist
        $endpoints = ['/api/profile', '/api/profile', '/api/dashboard'];
        $endpoint = $endpoints[array_rand($endpoints)];
        $token = $tokens[array_rand($tokens)];

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Platform' => 'mobile',
                    'Accept' => 'application/json'
                ])
                ->get($this->baseUrl . $endpoint);

            $responseTime = (microtime(true) - $startTime) * 1000;
            $memoryAfter = memory_get_usage();

            $this->recordMetrics('mixed', $response, $responseTime, $memoryAfter - $memoryBefore);
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->recordError('mixed', $e->getMessage(), $responseTime);
        }
    }

    protected function recordMetrics($endpoint, $response, $responseTime, $memoryUsage)
    {
        $this->metrics[$endpoint]['total_requests']++;
        $this->metrics[$endpoint]['response_times'][] = $responseTime;

        if ($response->successful()) {
            $this->metrics[$endpoint]['successful_requests']++;
        } else {
            $this->metrics[$endpoint]['failed_requests']++;
            $this->metrics[$endpoint]['errors'][] = [
                'status' => $response->status(),
                'message' => $response->json()['message'] ?? 'Unknown error',
                'response_time' => $responseTime
            ];
        }

        // Update min/max response times
        if ($responseTime < $this->metrics[$endpoint]['min_response_time']) {
            $this->metrics[$endpoint]['min_response_time'] = $responseTime;
        }
        if ($responseTime > $this->metrics[$endpoint]['max_response_time']) {
            $this->metrics[$endpoint]['max_response_time'] = $responseTime;
        }

        // Record system metrics
        $this->metrics['system']['memory_usage'][] = $memoryUsage;
    }

    protected function recordError($endpoint, $message, $responseTime)
    {
        $this->metrics[$endpoint]['total_requests']++;
        $this->metrics[$endpoint]['failed_requests']++;
        $this->metrics[$endpoint]['errors'][] = [
            'message' => $message,
            'response_time' => $responseTime
        ];
    }

    protected function calculateStatistics()
    {
        foreach (['login', 'profile', 'mixed'] as $endpoint) {
            if (empty($this->metrics[$endpoint]['response_times'])) {
                continue;
            }

            $responseTimes = $this->metrics[$endpoint]['response_times'];
            sort($responseTimes);

            $count = count($responseTimes);
            $this->metrics[$endpoint]['avg_response_time'] = array_sum($responseTimes) / $count;

            // Calculate percentiles
            $this->metrics[$endpoint]['p95_response_time'] = $responseTimes[floor($count * 0.95)] ?? 0;
            $this->metrics[$endpoint]['p99_response_time'] = $responseTimes[floor($count * 0.99)] ?? 0;

            // Calculate throughput (requests per second)
            $totalTime = microtime(true) - $this->startTime;
            $this->metrics[$endpoint]['throughput'] = $this->metrics[$endpoint]['total_requests'] / $totalTime;
        }
    }

    protected function generateBenchmarkReport($outputFormat)
    {
        $this->calculateStatistics();

        $this->newLine();
        $this->info('📊 Performance Benchmark Report');
        $this->info('===============================');

        foreach (['login', 'profile', 'mixed'] as $endpoint) {
            if ($this->metrics[$endpoint]['total_requests'] === 0) {
                continue;
            }

            $this->displayEndpointMetrics($endpoint);
        }

        $this->displaySystemMetrics();
        $this->displayRecommendations();

        if ($outputFormat === 'json') {
            $this->saveJsonReport();
        } elseif ($outputFormat === 'csv') {
            $this->saveCsvReport();
        }
    }

    protected function displayEndpointMetrics($endpoint)
    {
        $metrics = $this->metrics[$endpoint];

        $this->newLine();
        $this->info(strtoupper($endpoint) . ' Endpoint Metrics:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $successRate = $metrics['total_requests'] > 0
            ? round(($metrics['successful_requests'] / $metrics['total_requests']) * 100, 2)
            : 0;

        $this->line("Total Requests: " . number_format($metrics['total_requests']));
        $this->line("Successful: " . number_format($metrics['successful_requests']));
        $this->line("Failed: " . number_format($metrics['failed_requests']));
        $this->line("Success Rate: {$successRate}%");
        $this->line("Throughput: " . round($metrics['throughput'], 2) . " req/s");

        if (!empty($metrics['response_times'])) {
            $this->line("Response Times:");
            $this->line("  Average: " . round($metrics['avg_response_time'], 2) . "ms");
            $this->line("  Min: " . round($metrics['min_response_time'], 2) . "ms");
            $this->line("  Max: " . round($metrics['max_response_time'], 2) . "ms");
            $this->line("  95th Percentile: " . round($metrics['p95_response_time'], 2) . "ms");
            $this->line("  99th Percentile: " . round($metrics['p99_response_time'], 2) . "ms");
        }

        if (!empty($metrics['errors'])) {
            $this->line("Common Errors:");
            $errorCounts = [];
            foreach ($metrics['errors'] as $error) {
                $key = $error['message'] ?? 'Unknown';
                $errorCounts[$key] = ($errorCounts[$key] ?? 0) + 1;
            }
            foreach (array_slice($errorCounts, 0, 5, true) as $error => $count) {
                $this->line("  {$error}: {$count} times");
            }
        }
    }

    protected function displaySystemMetrics()
    {
        $this->newLine();
        $this->info('SYSTEM Metrics:');
        $this->line('━━━━━━━━━━━━━━━━');

        if (!empty($this->metrics['system']['memory_usage'])) {
            $memoryUsage = $this->metrics['system']['memory_usage'];
            $avgMemory = array_sum($memoryUsage) / count($memoryUsage);
            $maxMemory = max($memoryUsage);

            $this->line("Memory Usage:");
            $this->line("  Average per request: " . $this->formatBytes($avgMemory));
            $this->line("  Peak per request: " . $this->formatBytes($maxMemory));
        }

        $totalTime = microtime(true) - $this->startTime;
        $this->line("Total Test Duration: " . round($totalTime, 2) . "s");
    }

    protected function displayRecommendations()
    {
        $this->newLine();
        $this->info('🎯 Performance Recommendations:');
        $this->line('═══════════════════════════════');

        $recommendations = [];

        // Check response times
        foreach (['login', 'profile', 'mixed'] as $endpoint) {
            $metrics = $this->metrics[$endpoint];

            if ($metrics['avg_response_time'] > 1000) {
                $recommendations[] = "⚠️ {$endpoint} endpoint avg response time is high (" . round($metrics['avg_response_time'], 2) . "ms). Consider optimization.";
            }

            if ($metrics['p95_response_time'] > 2000) {
                $recommendations[] = "⚠️ {$endpoint} endpoint 95th percentile is very high (" . round($metrics['p95_response_time'], 2) . "ms). Check for bottlenecks.";
            }

            $successRate = $metrics['total_requests'] > 0
                ? ($metrics['successful_requests'] / $metrics['total_requests']) * 100
                : 100;

            if ($successRate < 95) {
                $recommendations[] = "❌ {$endpoint} endpoint success rate is low ({$successRate}%). Investigate errors.";
            }

            if ($metrics['throughput'] < 10) {
                $recommendations[] = "⚠️ {$endpoint} endpoint throughput is low (" . round($metrics['throughput'], 2) . " req/s). Consider scaling.";
            }
        }

        if (empty($recommendations)) {
            $this->info("✅ All metrics look healthy! System is performing well.");
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }

        // General recommendations
        $this->newLine();
        $this->line("💡 General Optimization Tips:");
        $this->line("• Implement Redis caching for frequently accessed data");
        $this->line("• Use database connection pooling");
        $this->line("• Consider API rate limiting to prevent abuse");
        $this->line("• Monitor database query performance");
        $this->line("• Implement proper error handling and logging");
    }

    protected function formatBytes($bytes)
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    protected function saveJsonReport()
    {
        $filename = 'auth_benchmark_' . date('Y-m-d_H-i-s') . '.json';
        $path = storage_path('logs/' . $filename);

        file_put_contents($path, json_encode($this->metrics, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("📄 JSON report saved to: {$path}");
    }

    protected function saveCsvReport()
    {
        $filename = 'auth_benchmark_' . date('Y-m-d_H-i-s') . '.csv';
        $path = storage_path('logs/' . $filename);

        $csv = fopen($path, 'w');

        // Write header
        fputcsv($csv, [
            'Endpoint',
            'Total Requests',
            'Successful',
            'Failed',
            'Success Rate %',
            'Throughput (req/s)',
            'Avg Response Time (ms)',
            'Min Response Time (ms)',
            'Max Response Time (ms)',
            'P95 Response Time (ms)',
            'P99 Response Time (ms)'
        ]);

        // Write data
        foreach (['login', 'profile', 'mixed'] as $endpoint) {
            $metrics = $this->metrics[$endpoint];

            if ($metrics['total_requests'] === 0) continue;

            $successRate = round(($metrics['successful_requests'] / $metrics['total_requests']) * 100, 2);

            fputcsv($csv, [
                $endpoint,
                $metrics['total_requests'],
                $metrics['successful_requests'],
                $metrics['failed_requests'],
                $successRate,
                round($metrics['throughput'], 2),
                round($metrics['avg_response_time'], 2),
                round($metrics['min_response_time'], 2),
                round($metrics['max_response_time'], 2),
                round($metrics['p95_response_time'], 2),
                round($metrics['p99_response_time'], 2)
            ]);
        }

        fclose($csv);

        $this->newLine();
        $this->info("📊 CSV report saved to: {$path}");
    }
}
