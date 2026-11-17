# Architecture and acceptance plan

Repository audit (2026-11-17): empty Git repository, origin fiachehr/laravel-logger,
PHP CLI 8.4.5, Composer 2.8.6; Docker daemon unavailable.
There is no existing host application, framework, logger, or frontend to preserve.

## Decisions

Use the existing repository identity fiachehr/laravel-logger and MIT license, approved by the maintainer.
GitHub connector confirmed the repository is public and owned by fiachehr.
Target PHP 8.3–8.5, Laravel 12/13, Monolog 3, Testbench 10/11. Distinguish configured
CI coverage from actually executed tests in validation.md. Do not claim future PHP majors.
Sources: https://laravel.com/docs/13.x/releases and https://www.php.net/supported-versions.php.

```mermaid
flowchart LR
    App[Laravel Log / explicit diagnostics] --> Channel[Opt-in Monolog channel]
    HTTP[Correlation middleware] --> Context[Request / worker lifecycle context]
    Context --> Formatter[Bounded schema formatter]
    Channel --> Formatter
    Formatter --> Policy[Allowlist and secret protection]
    Policy --> Files[Locked daily JSONL files]
    Browser[Dashboard] --> Auth[Independent credentials and revocable session OR host guard and Gate]
    Auth --> Query[Validated filters and encrypted cursors]
    Query --> Reader[Bounded canonical file reader]
    Reader --> Files
```

No database, CDN, Node build, outbound requests, source-code browser, downloads,
deletion endpoint, automatic exception reporter, or native dump interception.
The package channel captures records routed to it, including framework-reported
exceptions. Stacks opt in manually. Never register another exception reporter:
Laravel's existing reporting policy decides which exceptions are recorded.

Use categorical messages by default, trusted metadata only, no auth model loads.
Middleware metadata lives on the current Request. Queue metadata uses scoped
storage plus explicit worker reset events. No singleton captures scoped context.
Read pages have byte/time/file/result limits and opaque, expiring snapshot cursors.
Ordering is append order (newest or oldest), not arbitrary timestamp sorting.
Explorer assets are served through authorized routes; only the login stylesheet is public and require no vendor publish.
Plain DOM rendering makes hostile text inert and keeps distribution simple;
the tradeoff is a small hand-maintained interface rather than a frontend framework.

## Phases / acceptance

1. Architecture: audit, version targets, threat model, explicit defaults.
2. Core: privacy policy, JSON schema, channel, HTTP/queue isolation; canary tests.
3. Storage: canonical files, rotation, partial records, bounded cursor search.
4. Dashboard: selected authentication on every log endpoint, accessible table and detail views,
   filters, theme, clipboard, grouping; browser and adversarial tests.
5. Diagnostics / port: explicit helpers; stop restricted to development;
   loopback-only optional launch using host Laravel middleware.
6. Release: test both framework versions, static analysis, audit, benchmark,
   clean-host Composer installation, docs, semver tag, authorized push.

The v0.1.0 initial release is intentionally pre-1.0 while public API usage matures.
Production safety depends on configured dashboard authentication, OS permissions, retention and
operator approval of every extra diagnostic field. Threats are in security.md.

## 0.2 authentication extension

Fresh configurations use a package-owned Blade login with one configuration-backed
operator and a password hash. No Eloquent user model, user migration or host login
is required. The existing Laravel session stores a separate namespace and the host
cache stores revocable token registrations and rate limits. Stateless services
resolve current request/session/config values; no per-request singleton is captured.

Host mode retains the original guard/Gate integration. Published 0.1 configurations
with no auth block fall back to that mode. The new public routes are limited to
credentials sign-in and its static stylesheet; login/logout POSTs require CSRF.
All log routes remain private. Credentials are never request-captured or logged.
