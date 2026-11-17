# Serving the dashboard

## Mode A: existing Laravel web server (recommended)

Use `/log-explorer` through the application's normal HTTPS endpoint. Define the
Gate, select the host guard, enable both flags, keep the web middleware, cache
configuration/routes, and test authorized and unauthorized requests. Session
cookie configuration remains the operator's responsibility. There are no writable
or streaming endpoints. All dashboard assets require the same login/authorization.

## Mode B: explicitly launched local port

```bash
# APP_ENV must be local or development, with both feature flags and Gate configured:
php artisan log-explorer:serve --port=8765
```

Visit `http://127.0.0.1:8765/log-explorer`, sign in through the host app on that
origin, then open the explorer. This delegates to Laravel's development server
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
still needs a real authentication flow: use a correctly scoped shared host session
or your established authentication proxy/SSO; this example deliberately does not
add a login bypass. Bind loopback or restrict by firewall/VPN, add TLS on any
remote-facing hop, and keep the package Gate enabled. Nginx configuration is
illustrative and was not deployed as part of this package's local validation.
