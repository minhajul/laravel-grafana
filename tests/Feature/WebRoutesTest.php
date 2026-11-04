<?php

declare(strict_types=1);

it('returns the application status as JSON', function () {
    $response = $this->getJson(route('home'));

    $response->assertOk()->assertExactJson([
        'status' => 'OK',
        'data' => 'Laravel Grafana api',
        'version' => '1.0.0',
    ]);
});

it('does not persist a session for the status endpoint', function () {
    config(['session.driver' => 'database']);

    $this->getJson(route('home'))->assertOk();

    $this->assertDatabaseEmpty('sessions');
});
