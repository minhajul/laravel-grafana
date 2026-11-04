<?php

declare(strict_types=1);

use App\Models\Datapoint;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('exposes the application gauges and the request and query histograms', function () {
    User::factory()->create();
    Datapoint::factory()->count(3)->create();
    $this->getJson('/datapoint');

    // Access control on the endpoint is configured separately; this test covers what it renders.
    $response = $this->withoutMiddleware()->get('/prometheus');

    $response
        ->assertSee('app_users_count 1', false)
        ->assertSee('app_datapoints_count 3', false)
        ->assertSee('laravel_http_request_duration_seconds_count{method="GET",status="200",uri="datapoint"} 1', false)
        ->assertSee('laravel_database_query_duration_seconds_count{sql_type="select"}', false);
});
