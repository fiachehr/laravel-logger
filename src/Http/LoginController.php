<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class LoginController
{
    public function show(Request $request, DashboardLogin $login): Response
    {
        abort_unless($login->mode() === 'credentials', 404);
        if ($login->authenticated($request)) {
            return redirect()->route('log-explorer.index');
        }
        $key = 'log-explorer:login-page:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response('Too many requests. Try again shortly.', 429, ['Retry-After' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return response()->view('log-explorer::login');
    }

    public function store(Request $request, DashboardLogin $login): Response
    {
        abort_unless($login->mode() === 'credentials', 404);
        $ipKey = 'log-explorer:login-ip:'.hash('sha256', (string) $request->ip());
        $accountKey = 'log-explorer:login-account:'.$login->fingerprint();
        $attempts = $login->limit('login_attempts', 5, 10);
        $decay = $login->limit('login_decay_seconds', 60, 900);
        if (RateLimiter::tooManyAttempts($ipKey, $attempts) || RateLimiter::tooManyAttempts($accountKey, $attempts * 4)) {
            return response('Too many sign-in attempts. Try again shortly.', 429, [
                'Retry-After' => max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($accountKey)),
            ]);
        }
        RateLimiter::hit($ipKey, $decay);
        RateLimiter::hit($accountKey, $decay);
        $username = $request->input('username');
        $password = $request->input('password');
        if (! is_string($username) || ! is_string($password) || ! $login->verify($username, $password)) {
            // Do not flash username/password or distinguish unknown users from incorrect passwords.
            return redirect()->route('log-explorer.login')->with('log_explorer_error', 'Invalid username or password.');
        }
        $login->signIn($request);
        RateLimiter::clear($ipKey);

        return redirect()->route('log-explorer.index');
    }

    public function destroy(Request $request, DashboardLogin $login): Response
    {
        abort_unless($login->mode() === 'credentials', 404);
        $login->signOut($request);

        return redirect()->route('log-explorer.login');
    }

    public function stylesheet(DashboardLogin $login): Response
    {
        abort_unless($login->mode() === 'credentials', 404);

        return response(file_get_contents(__DIR__.'/../../resources/assets/login-v1.css'), 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    }
}
