<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MeasureHttpRequestDuration
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        // Exclude Prometheus scraping endpoint itself from metrics to avoid pollution
        if ($request->is('prometheus') || $request->is('up')) {
            return $response;
        }

        try {
            if (app()->bound(CollectorRegistry::class)) {
                $registry = app(CollectorRegistry::class);
                $histogram = $registry->getOrRegisterHistogram(
                    'laravel',
                    'http_request_duration_seconds',
                    'Duration of HTTP requests',
                    ['method', 'status', 'uri']
                );

                // Normalize URI to avoid cardinality explosion (e.g. replace IDs with route param keys)
                $uri = $request->route() ? $request->route()->uri() : 'unmatched_route';

                $histogram->observe($duration, [
                    $request->method(),
                    (string) $response->getStatusCode(),
                    $uri,
                ]);
            }
        } catch (Throwable $e) {
            // Silently fail to not crash the request
        }

        return $response;
    }
}
