<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;

it('stores samples recorded through the collector registry in the configured storage', function () {
    app(CollectorRegistry::class)
        ->getOrRegisterCounter('test', 'events_total', 'Test events')
        ->inc();

    $storedMetrics = (new RenderTextFormat)->render(app(Adapter::class)->collect());

    expect($storedMetrics)->toContain('test_events_total 1');
});

it('uses redis storage when configured', function () {
    config(['prometheus.storage' => 'redis']);
    app()->forgetScopedInstances();

    expect(app(Adapter::class))->toBeInstanceOf(Redis::class);
});

it('rejects an unsupported storage', function () {
    config(['prometheus.storage' => 'database']);
    app()->forgetScopedInstances();

    expect(fn () => app(Adapter::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported Prometheus storage [database].');
});

it('records the duration of database queries', function () {
    DB::select('select 1');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->toContain('laravel_database_query_duration_seconds_count{sql_type="select"} 1');
});

it('puts sub-millisecond queries into sub-millisecond buckets', function () {
    event(new QueryExecuted('select 1', [], 0.2, DB::connection()));

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)
        ->toContain('laravel_database_query_duration_seconds_bucket{sql_type="select",le="0.0001"} 0')
        ->toContain('laravel_database_query_duration_seconds_bucket{sql_type="select",le="0.00025"} 1');
});

it('keeps running queries and reports the failure once while the metric storage is unavailable', function () {
    Exceptions::fake();
    $unavailableStorage = Mockery::mock(Adapter::class);
    $unavailableStorage->shouldReceive('updateHistogram')->andThrow(new StorageException("Can't connect to Redis server."));
    app()->instance(CollectorRegistry::class, new CollectorRegistry($unavailableStorage, false));

    $firstResult = DB::select('select 1 as value');
    $secondResult = DB::select('select 2 as value');

    expect([$firstResult[0]->value, $secondResult[0]->value])->toBe([1, 2]);
    Exceptions::assertReportedCount(1);
});

it('retries the metric storage after the failure pause has passed', function () {
    Exceptions::fake();
    $unavailableStorage = Mockery::mock(Adapter::class);
    $unavailableStorage->shouldReceive('updateHistogram')->andThrow(new StorageException("Can't connect to Redis server."));
    app()->instance(CollectorRegistry::class, new CollectorRegistry($unavailableStorage, false));
    DB::select('select 1');

    $this->travel(31)->seconds();
    DB::select('select 1');

    Exceptions::assertReportedCount(2);
});
