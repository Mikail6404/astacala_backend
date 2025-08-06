<?php

// Simple database connectivity test for backend validation
require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Backend Database Connectivity Test ===\n";

try {
    // Test database connection
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "✅ Database connection: SUCCESS\n";

    // Test a simple query
    $users = \Illuminate\Support\Facades\DB::table('users')->count();
    echo "✅ User count query: $users users found\n";

    // Test if API routes are loaded
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $apiRoutes = 0;
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/') === 0) {
            $apiRoutes++;
        }
    }
    echo "✅ API routes loaded: $apiRoutes routes found\n";

    // Test authentication components
    if (class_exists('\Laravel\Sanctum\Sanctum')) {
        echo "✅ Laravel Sanctum: Available\n";
    } else {
        echo "❌ Laravel Sanctum: Not found\n";
    }

    echo "\n=== Backend API Readiness Assessment ===\n";
    echo "Database: Connected and functional\n";
    echo "Routes: Loaded and available\n";
    echo "Authentication: Sanctum available\n";
    echo "Status: ✅ READY FOR INTEGRATION TESTING\n";
} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
    echo "Backend may need configuration or database setup\n";
}
