<?php

declare(strict_types=1);

use App\Models\Datapoint;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('exposes the application gauges and the request and query histograms', function () {
    User::factory()->create();
    Datapoint::factory()->count(3)->create();
    $this->getJson('/');

    $response = $this->get('/prometheus');

    $response
        ->assertSee('app_users_count 1', false)
        ->assertSee('app_datapoints_count 3', false)
        ->assertSee('laravel_http_request_duration_seconds_count{method="GET",status="200",uri="/"} 1', false)
        ->assertSee('laravel_database_query_duration_seconds_count{sql_type="select"}', false);
});

it('renders gauges when the cache returns the cached counts as numeric strings', function () {
    // Stores such as Redis hand back cached integers as strings.
    Cache::put('prometheus_users_count', '5', 300);
    Cache::put('prometheus_datapoints_count', '7', 300);

    $response = $this->get('/prometheus');

    $response
        ->assertOk()
        ->assertSee('app_users_count 5', false)
        ->assertSee('app_datapoints_count 7', false);
});
