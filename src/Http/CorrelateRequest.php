<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Closure;
use Fiachehr\LaravelLogger\Context\ExceptionContexts;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CorrelateRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('log-explorer.enabled', false)) {
            return $next($request);
        }
        $incoming = $request->header('X-Request-ID');
        $id = config('log-explorer.http.trust_request_id', false) && is_string($incoming)
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{15,63}\z/D', $incoming)
            ? $incoming : bin2hex(random_bytes(16));
        $route = $request->route();
        $metadata = ['request_id' => $id, 'method' => $request->method()];
        if ($route instanceof Route) {
            $metadata['route_name'] = $route->getName();
            $metadata['route'] = '/'.ltrim($route->uri(), '/');
        }
        $request->attributes->set('_log_explorer_context', $metadata);
        $start = hrtime(true);
        try {
            $response = $next($request);
            if (config('log-explorer.http.response_header', true)) {
                $response->headers->set('X-Request-ID', $id);
            }
            if (config('log-explorer.enabled') && config('log-explorer.http.log_completion')) {
                Log::channel(config('log-explorer.channel'))->info('HTTP request completed', [
                    'event' => 'http.completed',
                    'status_code' => $response->getStatusCode(),
                    'duration_ms' => round((hrtime(true) - $start) / 1e6, 2),
                ]);
            }

            return $response;
        } catch (\Throwable $exception) {
            app(ExceptionContexts::class)->remember($exception, $metadata);
            throw $exception;
        } finally {
            $request->attributes->remove('_log_explorer_context');
        }
    }
}
