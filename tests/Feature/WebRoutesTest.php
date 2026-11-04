<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

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

it('hides the log test endpoint outside the local environment', function () {
    $response = $this->getJson(route('log-test'));

    $response->assertNotFound();
});

it('writes an info and an error log entry from the log test endpoint in the local environment', function () {
    $this->app->detectEnvironment(fn (): string => 'local');
    Log::spy();

    $response = $this->getJson(route('log-test'));

    $response->assertOk()->assertExactJson([
        'status' => 'OK',
        'message' => 'Log has been sent.',
    ]);
    Log::shouldHaveReceived('info')->once();
    Log::shouldHaveReceived('error')->once()->with('Whoops! This is a fake error for testing.');
});
