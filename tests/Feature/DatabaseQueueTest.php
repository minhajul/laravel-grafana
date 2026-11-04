<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;

it('stores dispatched jobs in the jobs table', function () {
    dispatch(static function (): void {})->onConnection('database');

    $this->assertDatabaseCount('jobs', 1);
});

it('moves a job that throws into the failed jobs table', function () {
    Exceptions::fake();
    dispatch(static function (): never {
        throw new RuntimeException('Job failed.');
    })->onConnection('database');

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

    $this->assertDatabaseEmpty('jobs');
    $this->assertDatabaseCount('failed_jobs', 1);
    Exceptions::assertReported(RuntimeException::class);
});

it('stores dispatched batches in the job batches table', function () {
    Bus::batch([static function (): void {}])->onConnection('database')->dispatch();

    $this->assertDatabaseCount('job_batches', 1);
    $this->assertDatabaseCount('jobs', 1);
});
