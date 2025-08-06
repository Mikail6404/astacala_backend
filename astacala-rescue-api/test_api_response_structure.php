<?php

require_once 'bootstrap/app.php';

try {
    echo "Testing API endpoint response structure..." . PHP_EOL;

    $app = require_once 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $controller = new App\Http\Controllers\GibranWebCompatibilityController();
    $request = new Illuminate\Http\Request();
    $response = $controller->getPelaporans($request);

    $data = json_decode($response->getContent(), true);
    if (isset($data['data']) && count($data['data']) > 0) {
        echo "First record structure:" . PHP_EOL;
        $first = $data['data'][0];
        echo "ID: " . ($first['id'] ?? 'missing') . PHP_EOL;
        echo "Reporter Username: " . ($first['reporter_username'] ?? 'missing') . PHP_EOL;
        echo "Reporter Phone: " . ($first['reporter_phone'] ?? 'missing') . PHP_EOL;
        echo "Coordinate Display: " . ($first['coordinate_display'] ?? 'missing') . PHP_EOL;
        echo "Personnel Count: " . ($first['personnel_count'] ?? 'missing') . PHP_EOL;
        echo "All keys: " . implode(', ', array_keys($first)) . PHP_EOL;
        echo "Sample JSON:" . PHP_EOL;
        echo json_encode($first, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo "No data found or empty response" . PHP_EOL;
        echo "Full response: " . $response->getContent() . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
