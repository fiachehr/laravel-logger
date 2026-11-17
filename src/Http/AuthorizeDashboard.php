<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('log-explorer.enabled') && config('log-explorer.dashboard.enabled'), 404);
        $user = Auth::guard(config('log-explorer.dashboard.guard'))->user();
        abort_if($user === null, 401);
        $gate = app(Gate::class);
        $ability = (string) config('log-explorer.dashboard.gate', 'viewLogExplorer');
        abort_unless($gate->has($ability) && $gate->forUser($user)->allows($ability), 403);
        $key = 'log-explorer:'.hash('sha256', get_class($user).':'.$user->getAuthIdentifier());
        $limit = max(1, min(600, (int) config('log-explorer.dashboard.rate_per_minute', 60)));
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response('Dashboard rate limit exceeded.', 429, ['Retry-After' => RateLimiter::availableIn($key), 'Cache-Control' => 'no-store']);
        }
        RateLimiter::hit($key, 60);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

        return $response;
    }
}
