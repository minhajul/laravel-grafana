<?php

use App\Http\Controllers\DatapointController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

Route::get('/', DatapointController::class)->name('datapoint.index');

Route::get('/log-test', function () {
    Log::info("This is a test log from Laravel to Loki! Time: " . now());
    Log::error("Whoops! This is a fake error for testing.");

    return response()->json([
        'message' => 'Log has been sent.'
    ]);
});

Route::get('/prometheus', function () {
    // 1. Get the registry we wrote to in AppServiceProvider
    $registry = app(CollectorRegistry::class);

    // 2. Create a Renderer (converts data to text)
    $renderer = new RenderTextFormat();

    // 3. Render and return with correct headers
    $result = $renderer->render($registry->getMetricFamilySamples());

    return response($result)
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
