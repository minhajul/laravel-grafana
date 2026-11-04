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
     * The default allows loopback and the private ranges Docker networks use,
     * so the docker-compose Prometheus can scrape the app. In production, set
     * it to the IP of your Prometheus server, and configure trusted proxies
     * when the app sits behind a load balancer or reverse proxy.
     */
    'allowed_ips' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('PROMETHEUS_ALLOWED_IPS', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
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
     * Select a cache to store gauges, counters, summaries and histograms between requests.
     * In a multi node setup you should ensure that each node writes to its own
     * cache instance or uses a node specific prefix.
     * Configure the cache store in config/cache.php.
     *
     * to use an in memory adapter for testing use array or null as your store
     * or remove the cache entry all together:
     *  'cache' => null       // InMemory implementation without laravel cache
     *  'cache' => 'array'    // InMemory implementation using laravel cache
     */
    'cache' => env('PROMETHEUS_CACHE_STORE', 'database'),
];
