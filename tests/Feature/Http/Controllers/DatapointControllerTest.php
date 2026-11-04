<?php

declare(strict_types=1);

use App\Models\Datapoint;

it('returns the first page of datapoints as a paginated resource collection', function () {
    Datapoint::factory()->count(25)->create();

    $response = $this->getJson(route('api.v1.datapoints.index'));

    $response
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'value', 'cast', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
        ])
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 25);
});

it('returns each datapoint with its value converted to its cast type', function () {
    $this->travelTo('2025-01-01 12:00:00');
    $datapoint = Datapoint::factory()->create(['name' => 'temperature', 'value' => '36.6', 'cast' => 'float']);

    $response = $this->getJson(route('api.v1.datapoints.index'));

    $response->assertJsonPath('data.0', [
        'id' => $datapoint->id,
        'name' => 'temperature',
        'value' => 36.6,
        'cast' => 'float',
        'created_at' => '2025-01-01T12:00:00.000000Z',
        'updated_at' => '2025-01-01T12:00:00.000000Z',
    ]);
});

it('returns datapoints in ascending id order', function () {
    Datapoint::factory()->createMany([['id' => 3], ['id' => 1], ['id' => 2]]);

    $response = $this->getJson(route('api.v1.datapoints.index'));

    expect($response->json('data.*.id'))->toBe([1, 2, 3]);
});

it('returns the remaining datapoints on the second page', function () {
    $datapoints = Datapoint::factory()->count(25)->create();

    $response = $this->getJson(route('api.v1.datapoints.index', ['page' => 2]));

    $response
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('data.0.id', $datapoints[20]->id)
        ->assertJsonPath('links.next', null);
});

it('returns the requested number of datapoints per page and keeps it in pagination links', function () {
    Datapoint::factory()->count(10)->create();

    $response = $this->getJson(route('api.v1.datapoints.index', ['per_page' => 5]));

    $response
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('links.next', route('api.v1.datapoints.index', ['per_page' => 5, 'page' => 2]));
});

it('accepts the maximum page size of 100', function () {
    $response = $this->getJson(route('api.v1.datapoints.index', ['per_page' => 100]));

    $response->assertOk()->assertJsonPath('meta.per_page', 100);
});

it('returns 422 when the page size is invalid', function (mixed $perPage, string $message) {
    $response = $this->getJson(route('api.v1.datapoints.index', ['per_page' => $perPage]));

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page' => $message]);
})->with([
    'zero' => [0, 'The per page field must be at least 1.'],
    'above the maximum' => [101, 'The per page field must not be greater than 100.'],
    'not a number' => ['many', 'The per page field must be an integer.'],
]);

it('does not persist a session', function () {
    config(['session.driver' => 'database']);

    $this->getJson(route('api.v1.datapoints.index'))->assertOk();

    $this->assertDatabaseEmpty('sessions');
});
