<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Closure;
use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $login = app(DashboardLogin::class);
        if ($login->mode() === 'credentials') {
            if (! $login->authenticated($request)) {
                if ($request->routeIs('log-explorer.index') && ! $request->expectsJson()) {
                    return redirect()->route('log-explorer.login');
                }
                abort(401);
            }
            $key = 'log-explorer:credentials:'.$login->fingerprint();
        } else {
            $user = Auth::guard(config('log-explorer.dashboard.guard'))->user();
            abort_if($user === null, 401);
            $gate = app(Gate::class);
            $ability = (string) config('log-explorer.dashboard.gate', 'viewLogExplorer');
            abort_unless($gate->has($ability) && $gate->forUser($user)->allows($ability), 403);
            $key = 'log-explorer:'.hash('sha256', get_class($user).':'.$user->getAuthIdentifier());
        }
        if ($request->routeIs('log-explorer.logout')) {
            return $next($request);
        }
        $limit = max(1, min(600, (int) config('log-explorer.dashboard.rate_per_minute', 60)));
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response('Dashboard rate limit exceeded.', 429, ['Retry-After' => RateLimiter::availableIn($key), 'Cache-Control' => 'no-store']);
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
