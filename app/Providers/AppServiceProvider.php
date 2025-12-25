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
        if ($this->app->runningInConsole() || ! $this->app->bound(CollectorRegistry::class)) {
            return;
        }

        DB::listen(function ($query) {
            $durationInSeconds = $query->time / 1000;

            $type = mb_strtolower(strtok($query->sql, ' '));

            try {
                $registry = app(CollectorRegistry::class);

                $histogram = $registry->getOrRegisterHistogram(
                    'laravel',
                    'database_query_duration_seconds',
                    'Duration of database queries',
                    ['sql_type']
                );

                $histogram->observe($durationInSeconds, [$type]);
            } catch (Throwable $e) {
                //
            }
        });
    }
}
