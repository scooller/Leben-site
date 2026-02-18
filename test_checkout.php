<?php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Create a fake request to test the controller
$request = \Illuminate\Http\Request::create(
    '/api/v1/checkout',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode(['plant_id' => 1, 'quantity' => 1, 'gateway' => 'transbank'])
);

try {
    // Test the controller directly
    $controller = new \App\Http\Controllers\Api\CheckoutController;
    $response = $controller->initiate($request);

    echo 'Response Status: '.$response->status()."\n";
    echo "Response Body:\n";
    echo json_encode($response->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} catch (\Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "Trace:\n".$e->getTraceAsString()."\n";
}
