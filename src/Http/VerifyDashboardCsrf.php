<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

final class VerifyDashboardCsrf
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('_token');
        if (! is_string($token) || $token === '') {
            $header = $request->headers->get('X-CSRF-TOKEN');
            $token = is_string($header) ? $header : null;
        }
        $expected = $request->hasSession() ? $request->session()->token() : null;
        if (! is_string($expected) || ! is_string($token) || ! hash_equals($expected, $token)) {
            throw new TokenMismatchException('Dashboard session token expired.');
        }

        return $next($request);
    }
}
