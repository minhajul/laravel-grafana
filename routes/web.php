<?php

use App\Http\Controllers\DatapointController;
use Illuminate\Support\Facades\Route;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\RenderTextFormat;

Route::get('/', DatapointController::class)->name('datapoint.index');

Route::get('/metrics', function () {
    $adapter = new APC();
    $registry = new CollectorRegistry($adapter);
    $renderer = new RenderTextFormat();

    $result = $renderer->render($registry->getMetricFamilySamples());
    return response($result, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
});
