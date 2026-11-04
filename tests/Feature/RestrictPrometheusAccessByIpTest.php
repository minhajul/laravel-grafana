<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Routing\Route;

/**
 * Run the callback with the given environment variables set, where null means unset,
 * and restore the original environment afterwards.
 *
 * @param  array<string, string|null>  $variables
 */
function withEnvironment(array $variables, Closure $callback): mixed
{
    $originalServer = $_SERVER;
    $originalEnv = $_ENV;
    $originalPutenv = [];

    foreach ($variables as $name => $value) {
        $originalPutenv[$name] = getenv($name);

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    try {
        return $callback();
    } finally {
        $_SERVER = $originalServer;
        $_ENV = $originalEnv;

        foreach ($originalPutenv as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }
}

/**
 * @param  array<string, string|null>  $variables
 * @return array<string, mixed>
 */
function prometheusConfigWithEnvironment(array $variables): array
{
    return withEnvironment($variables, fn (): array => require config_path('prometheus.php'));
}

it('serves metrics to an IP inside an allowed CIDR range', function () {
    config(['prometheus.allowed_ips' => ['172.16.0.0/12']]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])->get('/prometheus');

    $response->assertOk();
});

it('returns 403 to an IP outside the allowlist', function () {
    config(['prometheus.allowed_ips' => ['172.16.0.0/12', '192.0.2.7']]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/prometheus');

    $response->assertForbidden();
});

it('returns 403 to every IP when the allowlist is empty', function () {
    config(['prometheus.allowed_ips' => []]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('/prometheus');

    $response->assertForbidden();
});

it('returns 403 when only a spoofed X-Forwarded-For header is allowed', function () {
    config(['prometheus.allowed_ips' => ['172.16.0.0/12']]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '172.18.0.5')
        ->get('/prometheus');

    $response->assertForbidden();
});

it('serves metrics only to loopback by default', function (string $clientIp, int $expectedStatus) {
    config(['prometheus.allowed_ips' => prometheusConfigWithEnvironment(['PROMETHEUS_ALLOWED_IPS' => null])['allowed_ips']]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $clientIp])->get('/prometheus');

    $response->assertStatus($expectedStatus);
})->with([
    'loopback' => ['127.0.0.1', 200],
    'private proxy address' => ['10.0.0.5', 403],
    'docker bridge network' => ['172.18.0.5', 403],
    'public address' => ['203.0.113.10', 403],
]);

it('lets the docker-compose Prometheus scrape with the .env.example allowlist', function (string $clientIp, int $expectedStatus) {
    $exampleAllowlist = Dotenv::parse((string) file_get_contents(base_path('.env.example')))['PROMETHEUS_ALLOWED_IPS'];
    config(['prometheus.allowed_ips' => prometheusConfigWithEnvironment(['PROMETHEUS_ALLOWED_IPS' => $exampleAllowlist])['allowed_ips']]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => $clientIp])->get('/prometheus');

    $response->assertStatus($expectedStatus);
})->with([
    'loopback' => ['127.0.0.1', 200],
    'docker bridge network' => ['172.18.0.5', 200],
    'docker desktop gateway' => ['192.168.65.1', 200],
    'custom docker address pool' => ['10.0.0.5', 200],
    'documentation range' => ['203.0.113.10', 403],
    'just outside 172.16.0.0/12' => ['172.32.0.1', 403],
]);

it('reads a comma separated allowlist from PROMETHEUS_ALLOWED_IPS', function () {
    $config = prometheusConfigWithEnvironment(['PROMETHEUS_ALLOWED_IPS' => ' 192.0.2.7 , 10.0.0.0/8 ,']);

    expect($config['allowed_ips'])->toBe(['192.0.2.7', '10.0.0.0/8']);
});

it('registers the metrics endpoint only when prometheus is enabled', function (bool $enabled) {
    // Boot a separate app and override the config right after it loads, since .env
    // values would win over environment variables set from inside the test.
    $app = require base_path('bootstrap/app.php');
    $app->afterBootstrapping(LoadConfiguration::class, fn (Application $app) => $app['config']->set('prometheus.enabled', $enabled));
    $app->make(Kernel::class)->bootstrap();

    $metricsRouteIsRegistered = collect($app['router']->getRoutes()->getRoutes())
        ->contains(fn (Route $route): bool => $route->uri() === 'prometheus');

    expect($metricsRouteIsRegistered)->toBe($enabled);
})->with([
    'enabled' => true,
    'disabled' => false,
]);

it('reads PROMETHEUS_ENABLED as a boolean', function (string $value, bool $enabled) {
    expect(prometheusConfigWithEnvironment(['PROMETHEUS_ENABLED' => $value])['enabled'])->toBe($enabled);
})->with([
    'true' => ['true', true],
    'false' => ['false', false],
]);
