<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/**
 * Render everything recorded in the collector registry, in the Prometheus text format.
 */
function renderedMetrics(): string
{
    return (new RenderTextFormat)->render(app(CollectorRegistry::class)->getMetricFamilySamples());
}

/**
 * Swap in a collector registry whose storage fails every histogram write, like Redis being down.
 */
function useUnavailableMetricStorage(): void
{
    $unavailableStorage = Mockery::mock(Adapter::class);
    $unavailableStorage->shouldReceive('updateHistogram')->andThrow(new StorageException("Can't connect to Redis server."));

    app()->instance(CollectorRegistry::class, new CollectorRegistry($unavailableStorage, false));
}
