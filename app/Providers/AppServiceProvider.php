<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Spatie\Prometheus\Facades\Prometheus;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole() || ! $this->app->bound(CollectorRegistry::class)) {
            return;
        }

        Prometheus::addGauge('users_count')
            ->value(fn () => \App\Models\User::count());

        Prometheus::addGauge('datapoints_count')
            ->value(fn () => \App\Models\Datapoint::count());

        try {
            $registry = app(CollectorRegistry::class);

            $histogram = $registry->getOrRegisterHistogram(
                'laravel',
                'database_query_duration_seconds',
                'Duration of database queries',
                ['sql_type']
            );
        } catch (Throwable $exception) {
            Log::error('Failed to register Prometheus DB histogram: ', [$exception->getMessage()]);

            return;
        }

        static $recording = false;

        DB::listen(function ($query) use ($histogram, &$recording) {
            if ($recording) {
                return;
            }

            $recording = true;

            $durationInSeconds = $query->time / 1000;

            $type = mb_strtolower(strtok($query->sql, ' '));

            try {
                $histogram->observe($durationInSeconds, [$type]);
            } catch (Throwable $exception) {
                Log::error('Error recording DB query duration: ', [$exception->getMessage()]);
            } finally {
                $recording = false;
            }
        });
    }
}
