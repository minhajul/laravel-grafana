<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Datapoint;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Redis as PhpRedis;
use Spatie\Prometheus\Facades\Prometheus;
use Throwable;

final class PrometheusServiceProvider extends ServiceProvider
{
    /**
     * How long query metrics stay paused after the metric storage failed, so an outage
     * costs one failed write and one report per interval instead of one per query.
     */
    private const int STORAGE_FAILURE_PAUSE_SECONDS = 30;

    /**
     * Upper bounds, in seconds, of the query duration buckets. Most queries finish well
     * under a millisecond, below the first default bucket (5 ms), so start at 0.1 ms.
     *
     * @var list<float>
     */
    private const array QUERY_DURATION_BUCKETS = [
        0.0001, 0.00025, 0.0005, 0.001, 0.0025, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0,
    ];

    /**
     * The statement types used as "sql_type" label values; any other statement is "other".
     *
     * @var list<string>
     */
    private const array SQL_TYPES = ['select', 'insert', 'update', 'delete'];

    private ?CarbonInterface $queryMetricsPausedUntil = null;

    /**
     * Bind the metric storage and the collector registry that uses it.
     *
     * Application providers register after package providers, so these bindings
     * replace the cache-backed registry bound by spatie/laravel-prometheus.
     */
    public function register(): void
    {
        $this->app->scoped(Adapter::class, fn (): Adapter => $this->makeStorageAdapter());

        $this->app->scoped(CollectorRegistry::class, fn (): CollectorRegistry => new CollectorRegistry(
            $this->app->make(Adapter::class),
            registerDefaultMetrics: false,
        ));
    }

    /**
     * Register the application gauges and the database query listener.
     */
    public function boot(): void
    {
        if (! config('prometheus.enabled')) {
            return;
        }

        // Cache stores such as Redis return cached integers as numeric strings, so cast back.
        Prometheus::addGauge('users_count')
            ->value(fn (): int => (int) Cache::remember('prometheus_users_count', 300, fn (): int => User::query()->count()));

        Prometheus::addGauge('datapoints_count')
            ->value(fn (): int => (int) Cache::remember('prometheus_datapoints_count', 300, fn (): int => Datapoint::query()->count()));

        DB::listen(function (QueryExecuted $query): void {
            $this->recordQueryDuration($query);
        });
    }

    /**
     * Observe how long a query took. A metrics failure is reported but never breaks the query.
     */
    private function recordQueryDuration(QueryExecuted $query): void
    {
        if ($this->queryMetricsPausedUntil?->isFuture()) {
            return;
        }

        try {
            $this->app->make(CollectorRegistry::class)
                ->getOrRegisterHistogram(
                    'laravel',
                    'database_query_duration_seconds',
                    'Duration of database queries',
                    ['sql_type'],
                    self::QUERY_DURATION_BUCKETS,
                )
                ->observe($query->time / 1000, [$this->sqlType($query->sql)]);
        } catch (Throwable $exception) {
            $this->queryMetricsPausedUntil = now()->addSeconds(self::STORAGE_FAILURE_PAUSE_SECONDS);

            report($exception);
        }
    }

    /**
     * Map a statement to a fixed label value, so leading parentheses or whitespace and
     * statements such as CTEs or schema changes can't create new series.
     */
    private function sqlType(string $sql): string
    {
        $keyword = preg_match('/^[\s(]*([a-z]+)/i', $sql, $matches) === 1 ? mb_strtolower($matches[1]) : null;

        return in_array($keyword, self::SQL_TYPES, true) ? $keyword : 'other';
    }

    /**
     * Build the metric storage selected by the "prometheus.storage" option.
     */
    private function makeStorageAdapter(): Adapter
    {
        $storage = config('prometheus.storage');

        return match ($storage) {
            'redis' => $this->makeRedisAdapter(),
            'memory' => new InMemory,
            default => throw new InvalidArgumentException("Unsupported Prometheus storage [{$storage}]."),
        };
    }

    /**
     * Build promphp's Redis storage on top of a Laravel Redis connection, so it shares
     * that connection's settings (REDIS_URL, TLS, timeouts, key prefix) and socket.
     *
     * It updates samples with server-side Lua scripts, so concurrent workers never
     * overwrite each other's increments. It is built when metrics are first recorded.
     */
    private function makeRedisAdapter(): Redis
    {
        $connectionName = config('prometheus.redis_connection');
        $client = $this->app->make('redis')->connection($connectionName)->client();

        if (! $client instanceof PhpRedis) {
            throw new InvalidArgumentException(
                "Prometheus Redis storage needs the phpredis client, but connection [{$connectionName}] uses ".get_debug_type($client).'.',
            );
        }

        return Redis::fromExistingConnection($client);
    }
}
