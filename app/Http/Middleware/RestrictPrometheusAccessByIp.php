<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only lets clients whose IP matches an entry of `prometheus.allowed_ips`
 * (single addresses or CIDR ranges) read the metrics endpoint.
 *
 * Unlike Spatie's AllowIps middleware this fails closed: an empty allowlist
 * denies everyone instead of exposing the metrics to the whole internet.
 */
final class RestrictPrometheusAccessByIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $allowedIps */
        $allowedIps = config('prometheus.allowed_ips', []);

        $clientIp = $request->ip();

        if ($clientIp === null || ! IpUtils::checkIp($clientIp, $allowedIps)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
