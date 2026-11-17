<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Closure;
use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DashboardEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('log-explorer.enabled') && config('log-explorer.dashboard.enabled'), 404);
        $login = app(DashboardLogin::class);
        abort_unless(in_array($login->mode(), ['credentials', 'host'], true), 503, 'Dashboard authentication is not configured.');
        if ($login->mode() === 'credentials') {
            abort_unless($login->configured() && $request->hasSession(), 503, 'Configure dashboard credentials and session middleware before signing in.');
            $cacheDriver = config('cache.stores.'.config('cache.default').'.driver');
            $sessionDriver = config('session.driver');
            abort_unless(is_string($cacheDriver) && ! in_array($cacheDriver, ['', 'array', 'null'], true)
                && is_string($sessionDriver) && ! in_array($sessionDriver, ['', 'array', 'null'], true),
                503, 'Select persistent dashboard session and cache drivers.');
        }

        return $next($request);
    }
}
