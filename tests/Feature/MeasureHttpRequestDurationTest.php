<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;

it('labels requests with the method, status and route template', function () {
    Route::post('/orders/{order}', fn () => response()->noContent(201));

    $this->post('/orders/42');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->toContain('laravel_http_request_duration_seconds_count{method="POST",status="201",uri="orders/{order}"} 1');
});

it('labels requests that match no route as unmatched_route', function () {
    $this->get('/does-not-exist');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->toContain('laravel_http_request_duration_seconds_count{method="GET",status="404",uri="unmatched_route"} 1');
});

it('measures from when the request arrived', function () {
    $this->withServerVariables(['REQUEST_TIME_FLOAT' => microtime(true) - 2])->get('/');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)
        ->toContain('laravel_http_request_duration_seconds_bucket{method="GET",status="200",uri="/",le="1"} 0')
        ->toContain('laravel_http_request_duration_seconds_bucket{method="GET",status="200",uri="/",le="2.5"} 1');
});

it('does not record requests to excluded paths', function (string $path) {
    $this->get($path);

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->not->toContain('laravel_http_request_duration_seconds');
})->with([
    'metrics endpoint' => ['/prometheus'],
    'health check' => ['/up'],
]);

it('does not record requests to a custom metrics url', function () {
    config(['prometheus.urls' => ['default' => '/internal/metrics']]);

    $this->get('/internal/metrics');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->not->toContain('laravel_http_request_duration_seconds');
});

it('does not record requests while metrics are disabled', function () {
    config(['prometheus.enabled' => false]);

    $this->get('/');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->not->toContain('laravel_http_request_duration_seconds');
});

it('keeps serving requests and reports the failure while the metric storage is unavailable', function () {
    Exceptions::fake();
    $unavailableStorage = Mockery::mock(Adapter::class);
    $unavailableStorage->shouldReceive('updateHistogram')->andThrow(new StorageException("Can't connect to Redis server."));
    app()->instance(CollectorRegistry::class, new CollectorRegistry($unavailableStorage, false));

    $response = $this->getJson('/');

    $response->assertOk();
    Exceptions::assertReported(StorageException::class);
});
