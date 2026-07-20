<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
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
            ->value(fn () => Cache::remember('prometheus_users_count', 300, fn () => \App\Models\User::count()));

        Prometheus::addGauge('datapoints_count')
            ->value(fn () => Cache::remember('prometheus_datapoints_count', 300, fn () => \App\Models\Datapoint::count()));

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

        DB::listen(function ($query) use ($histogram) {
            if (Context::has('prometheus_db_recording')) {
                return;
            }

            Context::add('prometheus_db_recording', true);

            $durationInSeconds = $query->time / 1000;

            $type = mb_strtolower(strtok($query->sql, ' '));

            try {
                $histogram->observe($durationInSeconds, [$type]);
            } catch (Throwable $exception) {
                Log::error('Error recording DB query duration: ', [$exception->getMessage()]);
            } finally {
                Context::forget('prometheus_db_recording');
            }
        });
    }
}
