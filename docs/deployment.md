# Serving the dashboard

## Mode A: existing Laravel web server (recommended)

Use `/log-explorer` through the application's normal HTTPS endpoint. Define the
independent username/password hash (or select host mode with a guard/Gate),
enable both flags, keep the web middleware, cache configuration/routes, and test
login, sign-out and unauthorized requests. Session cookie configuration remains
the operator's responsibility. Log access is read-only; POST login/logout only
change authentication state. Only the credentials login page and its stylesheet
are available before login; all log data and explorer assets stay protected.

## Mode B: explicitly launched local port

```bash
# APP_ENV must be local or development, with both feature flags and the selected authentication configured:
php artisan log-explorer:serve --port=8765
```

Visit `http://127.0.0.1:8765/log-explorer`, use the built-in credentials sign-in on that
origin, then open the explorer. In host mode, use the host application login
instead. This delegates to Laravel's development server
with `--host=127.0.0.1 --tries=1 --no-reload`; no background service is spawned by
package installation and there is no arbitrary host/interface option. It serves
the **whole application**, not just the explorer. It stays in the foreground;
Ctrl+C stops it. The command rejects production and invalid ports. This is not
production-grade serving or a security boundary. If your host customizes the
`serve` command, verify its behavior before use.

## Operator-managed production port / reverse proxy

A package route cannot reserve a network port. Run your existing supported
Laravel web server/PHP-FPM stack and optionally expose a separate listener in
Nginx. Example inside an HTTPS-terminating reverse proxy:

```nginx
# Example only: replace upstream and configure your TLS/session domain policy.
upstream laravel_app { server 127.0.0.1:8080; }
server {
    listen 127.0.0.1:8765;
    server_name localhost;
    location = /log-explorer { proxy_pass http://laravel_app; }
    location /log-explorer/ {
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://laravel_app;
    }
    location / { return 404; }
}
```

The upstream is an operator-managed Laravel-capable web server, **not** `artisan
serve`. Ensure the actual trusted proxy and HTTPS headers agree with your topology.
Never trust arbitrary public X-Forwarded-* headers. A dedicated log-only origin
can use the built-in credentials sign-in at `/log-explorer/login`. Ensure Laravel
can persist its session and cache on this origin. Host mode instead needs a
correctly scoped host session or established authentication/SSO flow. Bind loopback
or restrict by firewall/VPN, add TLS on any remote-facing hop, and keep the selected
package authentication enabled. Nginx configuration is illustrative and was not deployed as part of this package's local validation.
