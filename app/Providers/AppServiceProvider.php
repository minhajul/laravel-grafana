<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Skip metrics if running in console (artisan commands) or if the registry isn't bound
        if ($this->app->runningInConsole() || ! $this->app->bound(CollectorRegistry::class)) {
            return;
        }

        DB::listen(function ($query) {
            $durationInSeconds = $query->time / 1000;

            // Get the SQL verb (select, insert, update) for the label
            $type = mb_strtolower(strtok($query->sql, ' '));

            try {
                // Access the underlying Prometheus Registry directly
                $registry = app(CollectorRegistry::class);

                // Create (or get existing) Histogram
                $histogram = $registry->getOrRegisterHistogram(
                    'laravel',
                    'database_query_duration_seconds',
                    'Duration of database queries',
                    ['sql_type'] // Label names
                );

                // Record the observation
                $histogram->observe($durationInSeconds, [$type]);
            } catch (Throwable $e) {
                // Fail silently so metrics don't break the app
            }
        });
    }
}
