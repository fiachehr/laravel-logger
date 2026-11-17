<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class DashboardLogin
{
    private const SESSION_KEY = '_log_explorer_credentials';

    public function mode(): string
    {
        // Published v0.1 configurations have no auth key: retain their host Gate integration.
        return (string) config('log-explorer.dashboard.auth.mode', 'host');
    }

    public function configured(): bool
    {
        $username = config('log-explorer.dashboard.auth.username');
        $hash = config('log-explorer.dashboard.auth.password_hash');

        return is_string($username) && (bool) preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.@-]{2,119}\z/D', $username)
            && is_string($hash) && strlen($hash) <= 255 && (password_get_info($hash)['algo'] ?? null) !== null;
    }

    public function fingerprint(): string
    {
        return hash('sha256', config('log-explorer.dashboard.auth.username')."\0".config('log-explorer.dashboard.auth.password_hash'));
    }

    public function authenticated(Request $request): bool
    {
        if (! $this->configured() || ! $request->hasSession()) {
            return false;
        }
        $state = $request->session()->get(self::SESSION_KEY);
        $now = now()->getTimestamp();
        if (! is_array($state) || ! is_string($state['fingerprint'] ?? null)
            || ! is_string($state['token'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/D', $state['token'])
            || ! hash_equals($this->fingerprint(), $state['fingerprint'])
            || Cache::get($this->cacheKey($state['token'])) !== $state['fingerprint']
            || ! is_int($state['created'] ?? null) || ! is_int($state['seen'] ?? null)
            || $state['created'] > $now || $state['seen'] > $now
            || $now - $state['seen'] >= $this->limit('idle_minutes', 30, 120) * 60
            || $now - $state['created'] >= $this->limit('session_minutes', 480, 1440) * 60) {
            $this->forget($request);

            return false;
        }
        $state['seen'] = $now;
        $request->session()->put(self::SESSION_KEY, $state);

        return true;
    }

    public function verify(string $username, string $password): bool
    {
        if (! $this->configured() || strlen($username) > 120 || strlen($password) > 1024 || $password === '' || str_contains($password, "\0")) {
            return false;
        }
        $hash = (string) config('log-explorer.dashboard.auth.password_hash');
        // Bcrypt truncates after 72 bytes; do not accept an arbitrary appended suffix.
        if (str_starts_with($hash, '$2') && strlen($password) > 72) {
            return false;
        }
        $passwordMatches = password_verify($password, $hash);

        return hash_equals((string) config('log-explorer.dashboard.auth.username'), $username) && $passwordMatches;
    }

    public function signIn(Request $request): void
    {
        $this->forget($request);
        $request->session()->regenerate(true);
        $now = now()->getTimestamp();
        $token = bin2hex(random_bytes(32));
        $stored = Cache::put($this->cacheKey($token), $this->fingerprint(), $this->limit('session_minutes', 480, 1440) * 60);
        abort_unless($stored, 503, 'Dashboard session cache is unavailable.');
        $request->session()->put(self::SESSION_KEY, ['fingerprint' => $this->fingerprint(), 'token' => $token, 'created' => $now, 'seen' => $now]);
    }

    public function signOut(Request $request): void
    {
        $this->forget($request);
        // Keep the application's login and other session data; rotate identifiers and CSRF.
        $request->session()->regenerate(true);
    }

    private function cacheKey(string $token): string
    {
        return 'log-explorer:session:'.hash('sha256', $token);
    }

    private function forget(Request $request): void
    {
        $token = $request->session()->get(self::SESSION_KEY.'.token');
        if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            Cache::forget($this->cacheKey($token));
        }
        $request->session()->forget(self::SESSION_KEY);
    }

    public function limit(string $key, int $default, int $maximum): int
    {
        return max(1, min($maximum, (int) config('log-explorer.dashboard.auth.'.$key, $default)));
    }
}
