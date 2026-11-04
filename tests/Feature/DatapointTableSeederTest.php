<?php

declare(strict_types=1);

use Database\Seeders\DatapointTableSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

it('seeds datapoints with values between 0 and 90 and timestamps from the last 7 days', function () {
    $this->freezeTime();

    $this->seed(DatapointTableSeeder::class);

    $summary = DB::table('datapoints')
        ->selectRaw('count(*) as total, min(cast(value as real)) as min_value, max(cast(value as real)) as max_value')
        ->selectRaw('min(created_at) as oldest, max(created_at) as newest, count(created_at) as timestamped')
        ->first();

    expect($summary->total)->toBe(10000)
        ->and($summary->timestamped)->toBe(10000)
        ->and($summary->min_value)->toBeGreaterThanOrEqual(0.0)
        ->and($summary->max_value)->toBeLessThanOrEqual(90.0)
        ->and($summary->oldest)->toBeGreaterThanOrEqual(now()->subDays(7)->toDateTimeString())
        ->and($summary->newest)->toBeLessThanOrEqual(now()->toDateTimeString());
});
