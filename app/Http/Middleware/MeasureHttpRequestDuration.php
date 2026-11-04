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
     * Record how long the request took.
     *
     * The clock starts at REQUEST_TIME_FLOAT, which PHP sets when the request
     * arrives, so framework bootstrapping and earlier middleware are included.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        $response = $next($request);

        if ($this->shouldMeasure($request)) {
            $this->recordDuration($request, $response, microtime(true) - $startedAt);
        }

        return $response;
    }

    /**
     * Skip the metrics endpoints and the health check, so scrapes don't pollute the histogram.
     */
    private function shouldMeasure(Request $request): bool
    {
        if (! config('prometheus.enabled')) {
            return false;
        }

        $excludedPaths = collect(config('prometheus.urls', []))
            ->map(fn (string $url): string => mb_trim($url, '/'))
            ->push('up')
            ->values()
            ->all();

        return ! $request->is(...$excludedPaths);
    }

    /**
     * Observe the duration, labelled by the route template (e.g. "users/{user}") rather
     * than the path to keep cardinality bounded. A metrics failure is reported but
     * never breaks the request.
     */
    private function recordDuration(Request $request, Response $response, float $durationInSeconds): void
    {
        try {
            app(CollectorRegistry::class)
                ->getOrRegisterHistogram(
                    'laravel',
                    'http_request_duration_seconds',
                    'Duration of HTTP requests',
                    ['method', 'status', 'uri'],
                )
                ->observe($durationInSeconds, [
                    $request->method(),
                    (string) $response->getStatusCode(),
                    $request->route()?->uri() ?? 'unmatched_route',
                ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
