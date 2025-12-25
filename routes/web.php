<?php

use App\Http\Controllers\DatapointController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

Route::get('/', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Laravel Grafana api',
        'version' => '1.0.0'
    ]);
});

Route::get('/datapoint', DatapointController::class);

Route::get('/log-test', function () {
    Log::info("This is a test log from Laravel to Loki! Time: " . now());
    Log::error("Whoops! This is a fake error for testing.");

    return response()->json([
        'message' => 'Log has been sent.'
    ]);
});

Route::get('/prometheus', function () {
    $registry = app(CollectorRegistry::class);

    $renderer = new RenderTextFormat();

    $result = $renderer->render($registry->getMetricFamilySamples());

    return response($result)
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
