# Laravel Logger

Privacy-first structured logs. A focused, read-only event explorer. No database,
CDN, external telemetry, or frontend build required by your application.

[![CI](https://github.com/fiachehr/laravel-logger/actions/workflows/ci.yml/badge.svg)](https://github.com/fiachehr/laravel-logger/actions/workflows/ci.yml)

![Log Explorer, captured in a clean Laravel application](docs/screenshots/explorer-desktop.png)

- Opt-in Monolog channel preserving Laravel / PSR-3 log levels and structured context.
- Metadata allowlist, categorical messages by default, bounded exception traces.
- Request / queue correlation with lifecycle cleanup, explicit safe diagnostic helpers.
- Authorized table, literal search, filters, snapshot pagination, request grouping,
  exception/context/JSON/raw views, clipboard, UTC/local timestamps, light/dark themes.
- Canonical local JSONL files, daily retention, locked writes, byte/time/result quotas.
- Nothing is enabled merely by installing the package.

**Initial release: v0.1.0.** Review the [security policy](docs/security.md) and
[validation record](docs/validation.md) before production use. This is a log
explorer, not an APM or distributed tracing backend.

## Compatibility

| Component | Dependency range | Verification |
|---|---|---|
| PHP | 8.3, 8.4, 8.5 | 8.3, 8.4, 8.5 integration matrix passed on GitHub Actions |
| Laravel | 12.x, 13.x | Both integration suites run locally; exact versions in validation record |
| Monolog | 3.x | Tested resolved version in validation record |
| Testbench | 10.x / 11.x | Laravel 12 / 13 respectively |

All six PHP/Laravel pairs and the real-host browser job passed in
[the initial CI run](https://github.com/fiachehr/laravel-logger/actions/runs/44025230219).
No claims for PHP 8.6+, Laravel <=11, Monolog 1/2 or Windows file storage.
Local file storage is validated on macOS and Linux.

## Install and opt in

After the maintainer submits the repository to Packagist:

```bash
composer require fiachehr/laravel-logger:^0.1
php artisan log-explorer:install
```

Before Packagist submission, add a Composer VCS repository pointing to
`https://github.com/fiachehr/laravel-logger`, then require the tagged version.
This needs access to the GitHub repository. See [release instructions](docs/releasing.md).

Laravel auto-discovers the provider. The installer asks before publishing a missing
configuration file, preserves an existing file, and never edits `.env`, host
logging, middleware, or authorization. For noninteractive installation:

```bash
php artisan vendor:publish --tag=log-explorer-config
```

Add a channel to `config/logging.php`:

```php
'channels' => [
    // ...existing channels
    'observability' => [
        'driver' => 'custom',
        'via' => \Fiachehr\LaravelLogger\Logging\CreateLogger::class,
        'level' => env('LOG_EXPLORER_LEVEL', 'debug'),
    ],
],
```

Set `LOG_EXPLORER_ENABLED=true`. Choose one integration strategy:

- Set `LOG_CHANNEL=observability` to route ordinary `Log::info/error/...` calls
  and Laravel-reported exceptions to this channel application-wide.
- Add `observability` **once** to an existing stack, alongside `single` or `daily`,
  to keep existing outputs. Those outputs retain their own privacy behavior.
- Use `Log::channel('observability')` for explicitly selected events.

Do not add the same structured destination through multiple nested stack paths.
Other channels, direct Monolog instances, native stderr and external transports
are covered only if explicitly routed here. There is no global logger interception
and no additional exception reporter that would duplicate Laravel reporting.

```php
Log::info('Checkout finished', ['event' => 'checkout.completed', 'count' => 3]);
Log::error('Checkout failed', ['event' => 'checkout.failed', 'exception' => $exception]);
```

Messages default to `Application log event`; event identifiers are the human-readable
category. Supply static, developer-controlled event/operation names. Never put
credentials or user input into categorical identifier fields.

## Request correlation

Register `\Fiachehr\LaravelLogger\Http\CorrelateRequest::class` in the host's
`web` and/or `api` middleware groups using `bootstrap/app.php`'s
`withMiddleware`. Register it **once**, after routing is available, so route
names/templates can be captured. Do not append it to the global pre-routing stack
if you need route metadata. Captured paths are route templates, never actual
request URLs or query strings. No request/response body, headers, IP address,
user model or SQL bindings are collected.

IDs are random by default. Enable `http.trust_request_id` only behind a trusted
edge that strips/replaces client IDs. Validation does not make an untrusted ID
trustworthy. A response header is optional; completion events are off by default.
Framework exception reporting after middleware unwinds retains the failing
request's metadata through a scoped, weak exception association.

Queue identity and attempt are captured from queue metadata, not job serialization.
Queue, console and Octane boundary listeners clear scoped context. See validation
limits for actual Octane server coverage.

## Enable the dashboard securely

Define a host authorization Gate, for example in `AppServiceProvider::boot`:

```php
Gate::define('viewLogExplorer', fn (User $user): bool => $user->is_log_operator);
```

`is_log_operator` is an example host-owned boolean. Use your own permission policy.
Choose a guard with `dashboard.guard` if you do not use the default web guard.
Then set `LOG_EXPLORER_DASHBOARD_ENABLED=true` and visit `/log-explorer` after
signing in to your application. There is **no implicit local or production allow**:
missing login returns 401, missing/denied Gate returns 403. Both environment flags
must be on. JSON and assets have the same authorization and rate limit.

```bash
php artisan config:cache
php artisan route:cache
php artisan log-explorer:doctor
```

`doctor` prints check names and outcomes, not credentials, paths, log contents or
user details. Missing storage before the first log is a check failure; initialize
it through an actual structured log write or create the private directory yourself.
It cannot prove your real login or role policy.

Dashboard assets ship with the package and need no publish, Node build or CDN.
The UI shows entries in file append order, newest/oldest, with no unbounded total
count. “Next window” may contain zero matches: it continues bounded scanning.
Refresh starts a new snapshot; cursors expire after 15 minutes. Changing filters
starts a new search. IDs can group events within the same configured source;
there is no tenant isolation policy or automatic cross-service aggregation.

## Explicit diagnostics

```php
use function Fiachehr\LaravelLogger\log_dump;
use function Fiachehr\LaravelLogger\log_dd;

log_dump($value, 'checkout.state'); // continues; disabled until diagnostics.enabled=true
log_dd($value, 'checkout.failure'); // restricted; see below
```

Values produce type/class/count summaries, not raw variable dumps. The `safe`
payload mode adds explicitly approved array fields; unknown objects never invoke
`__toString`, `jsonSerialize`, model serialization or debug hooks. Limits apply to
cycles and nesting too. Labels must be static categorical identifiers.

`log_dd` always throws `LogicException` in production or when disabled. With
logging + diagnostics + `allow_stop` enabled in `local`/`development`, it writes
one sanitized event and throws an HTTP 500 exception to unwind execution without
exiting the worker. A caller may catch that exception; it is not a process kill.
Do not use it in queue workers. The host may also report the thrown stop exception.
Native `dump()` and `dd()` are unchanged.

## Configuration and operations

One publishable file: [config/log-explorer.php](config/log-explorer.php).
Defaults: disabled features; `metadata` payloads; categorical messages; 15 frames;
2 previous exceptions; 5 nested levels; 30 context items; 1,000 bytes/string;
16 KiB/event; 64 MiB/day/file; 14-day retention; 8 MiB and 250 ms/read;
50 results/page; 60 authorized requests/minute. Hard ceilings also apply.

`none` excludes user context; `metadata` allows fixed operational fields; `safe`
adds `privacy.safe_fields` recursively. Sensitive-key protection applies in all
modes, even if a secret key is accidentally added to an allowlist. Scalar dumps
contain their type only. `message_mode=redacted` is an explicit free-text opt-in;
regex cannot guarantee removal of arbitrary secrets from prose. Prefer defaults
in production. Approved custom fields require your own privacy review.

Storage must be a dedicated canonical absolute directory, private to the app,
outside the web root. Symlinked roots/parents are rejected; configure their
resolved canonical location. New directory/files use 0700/0600. Protect parent
directories from untrusted writes. Existing permissive root modes are reported
by doctor rather than silently changed. At the daily size cap, writes are rejected
with a constant stderr warning; they are not silently redirected elsewhere.
Retention runs on writes, once per date per handler instance/process. Use an
operator-managed retention job if an idle application's files must expire on time.
Rate limiting uses the host's Laravel cache; choose a shared persistent cache for
multiple workers. File/array cache limitations are host deployment decisions.

- [Security, threat model and production checklist](docs/security.md)
- [Schema and extension point](docs/schema.md)
- [Local port / production reverse proxy](docs/deployment.md)
- [Upgrade, rollback and uninstall](docs/lifecycle.md)
- [Performance methodology and measured results](docs/performance.md)
- [Release and Packagist checklist](docs/releasing.md)
- [Exact validation record and limits](docs/validation.md)

## Development

```bash
composer install
composer check
composer audit
npm ci
npx playwright install chromium
# Must be a NEW absolute directory, never a production app:
tools/create-smoke-host.sh /absolute/new-smoke-host
php /absolute/new-smoke-host/artisan log-explorer:serve --port=18765
# From another terminal:
npm test
php tools/benchmark.php /absolute/new-smoke-host
```

The smoke fixture includes a clearly local-only demonstration login and Gate.
It is test tooling, not an authentication implementation to copy into production.
Browser tests exercise login/CSRF, the actual authorized API, paging, filters,
clipboard, tabs, grouping, XSS rendering and axe accessibility at desktop/mobile
sizes. Hostile response fixtures supplement real-host cases. Screenshots above
are real captures of synthetic sanitized events, not mockups.

MIT · [Contributing](CONTRIBUTING.md) · [Security reporting](SECURITY.md)
