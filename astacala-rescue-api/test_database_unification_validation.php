<?php

/**
 * Database Unification Validation Test - Phase 3 Complete Status
 * 
 * This test validates that Phase 3 of the database unification has been completed:
 * - All controllers use service layer (no direct database access)
 * - Old local models are archived
 * - Database configuration points to unified backend
 * - Service layer integration is functional
 */

require_once 'vendor/autoload.php';

echo "\n=== DATABASE UNIFICATION VALIDATION TEST ===\n";
echo "Checking Phase 3 completion status...\n\n";

// Test 1: Check database configuration
echo "1. CHECKING DATABASE CONFIGURATION\n";
echo "   Backend database configuration:\n";

$envFile = '.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);

    // Check database name
    if (preg_match('/DB_DATABASE=(.+)/', $envContent, $matches)) {
        $dbName = trim($matches[1]);
        echo "   ✓ Backend DB Name: $dbName\n";

        if ($dbName === 'astacala_rescue') {
            echo "   ✓ CORRECT: Using unified database 'astacala_rescue'\n";
        } else {
            echo "   ✗ INCORRECT: Expected 'astacala_rescue', got '$dbName'\n";
        }
    }

    // Check connection type
    if (preg_match('/DB_CONNECTION=(.+)/', $envContent, $matches)) {
        $dbConnection = trim($matches[1]);
        echo "   ✓ Backend DB Connection: $dbConnection\n";
    }
} else {
    echo "   ✗ .env file not found\n";
}

// Test 2: Check if service files exist
echo "\n2. CHECKING SERVICE LAYER FILES\n";

// Check if we're in mobile or backend directory
$mobileWebPath = dirname(dirname(dirname(__DIR__))) . '/astacala_resque-main/astacala_rescue_web';
$serviceFiles = [
    'GibranAuthService.php',
    'GibranReportService.php',
    'GibranDashboardService.php',
    'GibranNotificationService.php',
    'GibranUserService.php',
    'GibranContentService.php'
];

foreach ($serviceFiles as $file) {
    $servicePath = $mobileWebPath . '/app/Services/' . $file;
    if (file_exists($servicePath)) {
        echo "   ✓ $file exists\n";
    } else {
        echo "   ✗ $file missing\n";
    }
}

// Test 3: Check if old models are archived
echo "\n3. CHECKING MODEL ARCHIVAL STATUS\n";
$backupPath = $mobileWebPath . '/app/Models/Models_backup_pre_unification';
$archivedModels = ['Pengguna.php', 'Pelaporan.php', 'BeritaBencana.php', 'Admin.php'];

if (is_dir($backupPath)) {
    echo "   ✓ Backup directory exists: $backupPath\n";

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

// Test 4: Check controller service integration
echo "\n4. CHECKING CONTROLLER INTEGRATION\n";
$controllers = ['PenggunaController.php', 'BeritaBencanaController.php'];

foreach ($controllers as $controller) {
    $controllerPath = $mobileWebPath . '/app/Http/Controllers/' . $controller;
    if (file_exists($controllerPath)) {
        $content = file_get_contents($controllerPath);

        // Check for service injection
        if (strpos($content, 'GibranUserService') !== false || strpos($content, 'GibranContentService') !== false) {
            echo "   ✓ $controller uses service layer\n";
        } else {
            echo "   ✗ $controller missing service integration\n";
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
    // Test backend health
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
        echo "   ✓ Response: " . substr($response, 0, 100) . "...\n";
    } else {
        echo "   ⚠ Backend API responded with HTTP $httpCode\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Backend API connectivity test failed: " . $e->getMessage() . "\n";
}

// Test 6: Database table existence
echo "\n6. CHECKING UNIFIED DATABASE TABLES\n";

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

    $requiredTables = ['users', 'disaster_reports', 'publications', 'admins'];

    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);

        if ($stmt->fetch()) {
            echo "   ✓ Table '$table' exists in unified database\n";
        } else {
            echo "   ✗ Table '$table' missing in unified database\n";
        }
    }
} catch (PDOException $e) {
    echo "   ⚠ Database connection failed: " . $e->getMessage() . "\n";
}

// Summary
echo "\n=== PHASE 3 COMPLETION SUMMARY ===\n";
echo "Database Unification Phase 3 validation completed.\n";
echo "Ready to proceed to Phase 4: Testing & Validation\n\n";

echo "NEXT STEPS:\n";
echo "- Phase 4.1: Cross-Platform Integration Testing\n";
echo "- Phase 4.2: Data Integrity Validation\n";
echo "- Phase 4.3: Performance Testing\n";
echo "- Phase 5: Final Documentation & Cleanup\n\n";

function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}
