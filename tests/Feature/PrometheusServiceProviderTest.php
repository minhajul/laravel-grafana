<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;
use Redis as PhpRedis;

it('stores samples recorded through the collector registry in the configured storage', function () {
    app(CollectorRegistry::class)
        ->getOrRegisterCounter('test', 'events_total', 'Test events')
        ->inc();

    $storedMetrics = (new RenderTextFormat)->render(app(Adapter::class)->collect());

    expect($storedMetrics)->toContain('test_events_total 1');
});

it('uses redis storage on the configured laravel redis connection when configured', function () {
    $client = Mockery::mock(PhpRedis::class);
    $client->shouldReceive('isConnected')->andReturnTrue();

    RedisFacade::shouldReceive('connection')->once()->with('metrics')->andReturn(
        Mockery::mock(Connection::class)->shouldReceive('client')->andReturn($client)->getMock(),
    );

    config(['prometheus.storage' => 'redis', 'prometheus.redis_connection' => 'metrics']);
    app()->forgetScopedInstances();

    expect(app(Adapter::class))->toBeInstanceOf(Redis::class);
});

it('rejects a redis connection that does not use the phpredis client', function () {
    RedisFacade::shouldReceive('connection')->andReturn(
        Mockery::mock(Connection::class)->shouldReceive('client')->andReturn(new stdClass)->getMock(),
    );

    config(['prometheus.storage' => 'redis']);
    app()->forgetScopedInstances();

    expect(fn () => app(Adapter::class))
        ->toThrow(InvalidArgumentException::class, 'needs the phpredis client');
});

it('rejects an unsupported storage', function () {
    config(['prometheus.storage' => 'database']);
    app()->forgetScopedInstances();

    expect(fn () => app(Adapter::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported Prometheus storage [database].');
});

it('records the duration of database queries', function () {
    // The first query lazily runs the migrations; start counting from fresh in-memory storage after it.
    DB::select('select 0');
    app()->forgetScopedInstances();

    DB::select('select 1');

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->toContain('laravel_database_query_duration_seconds_count{sql_type="select"} 1');
});

it('labels queries with a fixed set of statement types', function (string $sql, string $sqlType) {
    event(new QueryExecuted($sql, [], 1.0, DB::connection()));

    $metrics = (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());

    expect($metrics)->toContain("laravel_database_query_duration_seconds_count{sql_type=\"{$sqlType}\"} 1");
})->with([
    'select' => ['select * from "users"', 'select'],
    'uppercase select' => ['SELECT 1', 'select'],
    'select after a newline' => ["\n    select 1", 'select'],
    'parenthesized union' => ['(select * from "users") union (select * from "users")', 'select'],
    'insert' => ['insert into "users" ("name") values (?)', 'insert'],
    'update' => ['update "users" set "name" = ?', 'update'],
    'delete' => ['delete from "users" where "id" = ?', 'delete'],
    'common table expression' => ['with "recent" as (select 1) select * from "recent"', 'other'],
    'schema change' => ['create table "users" ("id" integer primary key)', 'other'],
    'empty statement' => ['', 'other'],
]);

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
