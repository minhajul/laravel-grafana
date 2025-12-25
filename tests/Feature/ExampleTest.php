<?php

declare(strict_types=1);

use App\Models\Datapoint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('application returns a successful response', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

it('returns paginated datapoint', function () {
    Datapoint::factory()->count(25)->create();

    $response = $this->getJson('/datapoint');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'data',
            'meta' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
                'next_page_url',
                'previous_page_url',
            ],
        ]);

    expect($response->json('meta.per_page'))->toBe(20)
        ->and($response->json('meta.total'))->toBe(25)
        ->and(count($response->json('data')))->toBe(20);
});
