<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('PROMETHEUS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => 'prometheus',
    ],

    /*
     * Only these IPs or CIDR ranges will be allowed to visit the above urls,
     * as a comma separated list. No IP is allowed when the list is empty;
     * use "0.0.0.0/0,::/0" to deliberately allow every IP.
     *
     * The default only allows loopback, so an unconfigured app behind a proxy on
     * a private network doesn't expose its metrics. .env.example adds the private
     * ranges Docker networks use, so the docker-compose Prometheus can scrape the
     * app. In production, set it to the IP of your Prometheus server, and configure
     * trusted proxies when the app sits behind a load balancer or reverse proxy.
     */
    'allowed_ips' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('PROMETHEUS_ALLOWED_IPS', '127.0.0.1,::1')),
    ))),

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => 'app',

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        App\Http\Middleware\RestrictPrometheusAccessByIp::class,
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
     * when the storage above is "redis". It must use the phpredis client. Keys get
     * the connection's own prefix followed by "PROMETHEUS_".
     */
    'redis_connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
];
