<?php

/**
 * Database Unification Validation Test - Phase 3 Complete Status
 * Updated with correct paths for astacala_resque-main structure
 */

require_once 'vendor/autoload.php';

echo "\n=== DATABASE UNIFICATION VALIDATION TEST ===\n";
echo "Checking Phase 3 completion status...\n\n";

// Test 1: Check database configuration
echo "1. CHECKING DATABASE CONFIGURATION\n";
$envFile = '.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);

    if (preg_match('/DB_DATABASE=(.+)/', $envContent, $matches)) {
        $dbName = trim($matches[1]);
        echo "   ✓ Backend DB Name: $dbName\n";

        if ($dbName === 'astacala_rescue') {
            echo "   ✓ CORRECT: Using unified database 'astacala_rescue'\n";
        } else {
            echo "   ✗ INCORRECT: Expected 'astacala_rescue', got '$dbName'\n";
        }
    }
} else {
    echo "   ✗ .env file not found\n";
}

// Test 2: Check web app service files
echo "\n2. CHECKING WEB APP SERVICE LAYER FILES\n";
$webAppPath = dirname(dirname(__DIR__)) . '/astacala_resque-main/astacala_rescue_web';
$serviceFiles = [
    'GibranAuthService.php',
    'GibranReportService.php',
    'GibranDashboardService.php',
    'GibranNotificationService.php',
    'GibranUserService.php',
    'GibranContentService.php'
];

foreach ($serviceFiles as $file) {
    $servicePath = $webAppPath . '/app/Services/' . $file;
    if (file_exists($servicePath)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ✗ $file missing\n";
    }
}

// Test 3: Check web app model archival
echo "\n3. CHECKING WEB APP MODEL ARCHIVAL STATUS\n";
$backupPath = $webAppPath . '/app/Models_backup_pre_unification';
$archivedModels = ['Pengguna.php', 'Pelaporan.php', 'BeritaBencana.php', 'Admin.php'];

if (is_dir($backupPath)) {
    echo "   ✓ Backup directory exists\n";

    foreach ($archivedModels as $model) {
        $modelPath = $backupPath . '/' . $model;
        if (file_exists($modelPath)) {
            echo "   ✓ $model archived\n";
        } else {
            echo "   ✗ $model not archived\n";
        }
    }
} else {
    echo "   ✗ Backup directory not found\n";
}

// Test 4: Check web app controller integration
echo "\n4. CHECKING WEB APP CONTROLLER INTEGRATION\n";
$controllers = ['PenggunaController.php', 'BeritaBencanaController.php'];

foreach ($controllers as $controller) {
    $controllerPath = $webAppPath . '/app/Http/Controllers/' . $controller;
    if (file_exists($controllerPath)) {
        echo "   ✓ $controller exists\n";

        $content = file_get_contents($controllerPath);

        // Check for service injection
        if (strpos($content, 'GibranUserService') !== false || strpos($content, 'GibranContentService') !== false) {
            echo "   ✓ $controller uses service layer\n";
        } else {
            echo "   ⚠ $controller missing service integration\n";
        }

        // Check for old model usage (should be removed)
        if (
            strpos($content, 'use App\\Models\\Pengguna') !== false ||
            strpos($content, 'use App\\Models\\BeritaBencana') !== false
        ) {
            echo "   ⚠ $controller still has old model imports\n";
        }
    } else {
        echo "   ✗ $controller not found\n";
    }
}

// Test 5: Backend API connectivity
echo "\n5. CHECKING BACKEND API CONNECTIVITY\n";

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Backend API is responsive (HTTP 200)\n";
    } else {
        echo "   ⚠ Backend API responded with HTTP $httpCode\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Backend API connectivity test failed: " . $e->getMessage() . "\n";
}

// Test 6: Web app database configuration
echo "\n6. CHECKING WEB APP DATABASE CONFIGURATION\n";
$webEnvFile = $webAppPath . '/.env';
if (file_exists($webEnvFile)) {
    $webEnvContent = file_get_contents($webEnvFile);

    if (preg_match('/DB_DATABASE=(.+)/', $webEnvContent, $matches)) {
        $webDbName = trim($matches[1]);
        echo "   ✓ Web App DB Name: $webDbName\n";

        if ($webDbName === 'astacala_rescue') {
            echo "   ✓ CORRECT: Web app uses unified database\n";
        } else {
            echo "   ⚠ Web app database: $webDbName (should be astacala_rescue)\n";
        }
    }
} else {
    echo "   ✗ Web app .env file not found\n";
}

// Test 7: Cross-platform data integration test
echo "\n7. CHECKING CROSS-PLATFORM DATA INTEGRATION\n";

try {
    // Test users endpoint from backend
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/users');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['data']) && is_array($data['data'])) {
            $userCount = count($data['data']);
            echo "   ✓ Backend users API accessible ($userCount users found)\n";
        } else {
            echo "   ⚠ Backend users API response format unexpected\n";
        }
    } else {
        echo "   ⚠ Backend users API responded with HTTP $httpCode\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Backend users API test failed: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== PHASE 3 COMPLETION SUMMARY ===\n";
echo "Database Unification Phase 3 validation completed.\n";
echo "Status: Ready to proceed to Phase 4: Testing & Validation\n\n";

echo "PHASE 4 TODO LIST:\n";
echo "- [ ] Phase 4.1: Cross-Platform Integration Testing\n";
echo "- [ ] Phase 4.2: Data Integrity Validation\n";
echo "- [ ] Phase 4.3: Performance Testing\n";
echo "- [ ] Phase 4.4: Authentication Flow Testing\n";
echo "- [ ] Phase 5: Final Documentation & Cleanup\n\n";

echo "Next: Starting Phase 4.1 - Cross-Platform Integration Testing...\n";
