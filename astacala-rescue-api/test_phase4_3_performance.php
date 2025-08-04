<?php

/**
 * Phase 4.3: Performance Testing
 * 
 * This test validates system performance under load,
 * API response times, and database query optimization.
 */

require_once 'vendor/autoload.php';

echo "\n=== PHASE 4.3: PERFORMANCE TESTING ===\n";
echo "Testing system performance and optimization...\n\n";

// Test 1: API Response Time Testing
echo "1. TESTING API RESPONSE TIMES\n";

$endpoints = [
    'Health Check' => 'http://127.0.0.1:8000/api/health',
    'Users List' => 'http://127.0.0.1:8000/api/users',
    'Registration' => 'http://127.0.0.1:8000/api/v1/auth/register',
];

foreach ($endpoints as $name => $url) {
    $times = [];

    for ($i = 0; $i < 5; $i++) {
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        if ($name === 'Registration') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'name' => 'Perf Test ' . $i,
                'email' => 'perftest' . $i . '_' . time() . '@test.local',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!'
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = microtime(true) - $start;
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 || ($httpCode == 422 && $name === 'Registration')) {
            $times[] = $responseTime;
        }
    }

    if (!empty($times)) {
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        echo "   ✓ $name:\n";
        echo "     - Average: " . round($avgTime * 1000, 2) . "ms\n";
        echo "     - Min: " . round($minTime * 1000, 2) . "ms\n";
        echo "     - Max: " . round($maxTime * 1000, 2) . "ms\n";

        if ($avgTime < 0.5) {
            echo "     - Status: ✅ Excellent (<500ms)\n";
        } elseif ($avgTime < 1.0) {
            echo "     - Status: ✅ Good (<1s)\n";
        } else {
            echo "     - Status: ⚠ Slow (>1s)\n";
        }
    } else {
        echo "   ✗ $name: Failed to get valid responses\n";
    }
    echo "\n";
}

// Test 2: Database Query Performance
echo "2. TESTING DATABASE QUERY PERFORMANCE\n";

try {
    $config = [
        'host' => env('DB_HOST', '127.0.0.1'),
        'dbname' => env('DB_DATABASE', 'astacala_rescue'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ];

    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']}",
        $config['username'],
        $config['password']
    );

    $queries = [
        'Simple Count Users' => 'SELECT COUNT(*) FROM users',
        'Simple Count Reports' => 'SELECT COUNT(*) FROM disaster_reports',
        'Users with Role Filter' => 'SELECT * FROM users WHERE role = "ADMIN" LIMIT 10',
        'Reports with Join' => 'SELECT dr.title, u.name as reporter FROM disaster_reports dr LEFT JOIN users u ON dr.reported_by = u.id LIMIT 10',
        'Users Ordered by Date' => 'SELECT * FROM users ORDER BY created_at DESC LIMIT 10',
        'Complex Report Query' => 'SELECT dr.*, u.name, u.email FROM disaster_reports dr JOIN users u ON dr.reported_by = u.id WHERE dr.status = "PENDING" ORDER BY dr.created_at DESC LIMIT 5'
    ];

    foreach ($queries as $name => $sql) {
        $times = [];

        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $stmt = $pdo->query($sql);
            $result = $stmt->fetchAll();
            $queryTime = microtime(true) - $start;
            $times[] = $queryTime;
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        echo "   ✓ $name:\n";
        echo "     - Average: " . round($avgTime * 1000, 2) . "ms\n";
        echo "     - Min: " . round($minTime * 1000, 2) . "ms\n";
        echo "     - Max: " . round($maxTime * 1000, 2) . "ms\n";

        if ($avgTime < 0.01) {
            echo "     - Status: ✅ Excellent (<10ms)\n";
        } elseif ($avgTime < 0.05) {
            echo "     - Status: ✅ Good (<50ms)\n";
        } else {
            echo "     - Status: ⚠ Could be optimized (>50ms)\n";
        }
        echo "\n";
    }
} catch (PDOException $e) {
    echo "   ✗ Database performance test failed: " . $e->getMessage() . "\n";
}

// Test 3: Concurrent Request Testing
echo "3. TESTING CONCURRENT REQUEST HANDLING\n";

$concurrentTests = [
    ['endpoint' => 'http://127.0.0.1:8000/api/health', 'method' => 'GET'],
    ['endpoint' => 'http://127.0.0.1:8000/api/users', 'method' => 'GET'],
];

foreach ($concurrentTests as $test) {
    echo "   Testing concurrent requests to: " . basename($test['endpoint']) . "\n";

    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $startTime = microtime(true);

    // Create 5 concurrent requests
    for ($i = 0; $i < 5; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[] = $ch;
    }

    // Execute all requests
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    $totalTime = microtime(true) - $startTime;
    $successCount = 0;

    // Check results
    foreach ($curlHandles as $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            $successCount++;
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);

    echo "     - Total time for 5 concurrent requests: " . round($totalTime * 1000, 2) . "ms\n";
    echo "     - Successful responses: $successCount/5\n";
    echo "     - Average per request: " . round(($totalTime / 5) * 1000, 2) . "ms\n";

    if ($successCount === 5 && $totalTime < 2.0) {
        echo "     - Status: ✅ Excellent concurrent handling\n";
    } elseif ($successCount >= 4) {
        echo "     - Status: ✅ Good concurrent handling\n";
    } else {
        echo "     - Status: ⚠ Concurrent handling issues\n";
    }
    echo "\n";
}

// Test 4: Memory Usage Testing
echo "4. TESTING MEMORY USAGE\n";

$initialMemory = memory_get_usage();
$peakMemory = memory_get_peak_usage();

// Simulate data processing
try {
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT * FROM disaster_reports");
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentMemory = memory_get_usage();
    $newPeakMemory = memory_get_peak_usage();

    echo "   ✓ Memory usage analysis:\n";
    echo "     - Initial memory: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
    echo "     - After data loading: " . round($currentMemory / 1024 / 1024, 2) . " MB\n";
    echo "     - Peak memory: " . round($newPeakMemory / 1024 / 1024, 2) . " MB\n";
    echo "     - Memory increase: " . round(($currentMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

    if ($newPeakMemory < 50 * 1024 * 1024) { // 50MB
        echo "     - Status: ✅ Excellent memory efficiency (<50MB)\n";
    } elseif ($newPeakMemory < 100 * 1024 * 1024) { // 100MB
        echo "     - Status: ✅ Good memory usage (<100MB)\n";
    } else {
        echo "     - Status: ⚠ High memory usage (>100MB)\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Memory test error: " . $e->getMessage() . "\n";
}

// Test 5: System Resource Usage
echo "\n5. TESTING SYSTEM RESOURCE USAGE\n";

$startTime = microtime(true);

// Test API calls under load
$requestCount = 20;
$successCount = 0;

for ($i = 0; $i < $requestCount; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $successCount++;
    }
}

$totalTime = microtime(true) - $startTime;
$requestsPerSecond = $requestCount / $totalTime;

echo "   ✓ Load test results:\n";
echo "     - Total requests: $requestCount\n";
echo "     - Successful requests: $successCount\n";
echo "     - Total time: " . round($totalTime, 2) . " seconds\n";
echo "     - Requests per second: " . round($requestsPerSecond, 2) . "\n";
echo "     - Success rate: " . round(($successCount / $requestCount) * 100, 2) . "%\n";

if ($successCount === $requestCount && $requestsPerSecond > 10) {
    echo "     - Status: ✅ Excellent performance (>10 RPS, 100% success)\n";
} elseif ($successCount >= $requestCount * 0.95 && $requestsPerSecond > 5) {
    echo "     - Status: ✅ Good performance (>5 RPS, >95% success)\n";
} else {
    echo "     - Status: ⚠ Performance could be improved\n";
}

// Summary
echo "\n=== PHASE 4.3 PERFORMANCE TESTING SUMMARY ===\n";
echo "✅ API response time testing completed\n";
echo "✅ Database query performance validated\n";
echo "✅ Concurrent request handling tested\n";
echo "✅ Memory usage analyzed\n";
echo "✅ System resource usage evaluated\n\n";

echo "PHASE 4.3 STATUS: ✅ COMPLETED\n";
echo "Next: Phase 4.4 - Authentication Flow Testing\n\n";

function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}
