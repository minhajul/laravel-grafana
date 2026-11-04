<?php

declare(strict_types=1);

return [
    'enabled' => true,

    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => 'prometheus',
    ],

    /*
     * Only these IP's will be allowed to visit the above urls.
     * All IP's are allowed when empty.
     */
    'allowed_ips' => [
        // '1.2.3.4',
    ],

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => 'app',

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        Spatie\Prometheus\Http\Middleware\AllowIps::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => Spatie\Prometheus\Actions\RenderCollectorsAction::class,
    ],

    /**
     * Allow storage to be wiped after a render of data in metrics controller.
     */
    'wipe_storage_after_rendering' => false,

    /**
     * Where gauges, counters, summaries and histograms are stored between requests.
     *
     * Supported: "redis", "memory"
     *
     * "redis" updates every sample atomically on the server, so concurrent workers
     * never overwrite each other's increments. "memory" only keeps samples for
     * the current process, which suits tests and CI where Redis isn't running.
     */
    'storage' => env('PROMETHEUS_STORAGE', 'redis'),

    /**
     * The connection from the "redis" section of config/database.php that is used
     * when the storage above is "redis". Keys are prefixed with "PROMETHEUS_".
     */
    'redis_connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
];
