<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
 * These endpoints are called by probes and scripts rather than browsers, so they
 * skip the "web" group's session, cookie and CSRF middleware to stay stateless.
 */
Route::withoutMiddleware('web')->group(function (): void {
    Route::get('/', function () {
        return response()->json([
            'status' => 'OK',
            'data' => 'Laravel Grafana api',
            'version' => '1.0.0',
        ]);
    })->name('home');

    Route::get('/log-test', function () {
        abort_unless(app()->isLocal(), 404);

        Log::info('This is a test log from Laravel to Loki! Time: '.now());
        Log::error('Whoops! This is a fake error for testing.');

        return response()->json([
            'status' => 'OK',
            'message' => 'Log has been sent.',
        ]);
    })->name('log-test');
});
