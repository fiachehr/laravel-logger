# Validation record — 0.2.1

Automated validation uses PHP and Composer only. PHPUnit runs the package unit
and Laravel feature tests through Orchestra Testbench. The Blade dashboard ships
with its CSS and vanilla JavaScript; there is no frontend build step.

## Local checks — 2026-11-17

Environment: macOS arm64, PHP 8.4.5, Composer 2.8.6,
Laravel 13.43.0 / Testbench 11.3.0.

| Check | Command | Result |
| --- | --- | --- |
| Composer metadata | `composer validate --strict` | Passed |
| PHP style, static analysis and tests | `composer check` | Pint passed; PHPStan level 6 passed; 67 tests / 400 assertions passed |
| Dependency audit | `composer audit` | No security vulnerability advisories found |

The 0.2.1 changes remove the previous browser automation and its dependencies,
configuration and CI job. Runtime PHP, Blade views and bundled dashboard assets
are unchanged. Screenshots remain documentation assets.

## PHP test coverage

The suite covers:

- Secret-key variants, adversarial privacy canaries, bounded encoding, exception
  origins/traces, request IDs and route-template privacy.
- Request/queue lifecycle context cleanup and simulated Octane boundaries.
- File permissions, retention, quotas, concurrent writers, bounded pagination,
  rotation, cursor tampering/expiry, malformed data and symlink/hardlink rejection.
- Dashboard pages, JSON and assets; authorization, security headers, filters and
  rate limits; absence of log export or mutation routes.
- Independent credentials, mandatory login/logout CSRF, host-auth isolation,
  generic login failures, IP/account budgets, idle/absolute expiry, credential
  rotation, cache failure, revoked-session replay and host-session preservation.
- Hidden-input password hashing without plaintext output and fail-closed
  configuration/diagnostics behavior.

## Compatibility matrix

CI runs the PHP suite, Composer validation, Pint, PHPStan and Composer audit for
PHP 8.3/8.4/8.5 with Laravel 12/Testbench 10 and Laravel 13/Testbench 11.

The previous 0.2 source matrix
([run 44147519382](https://github.com/fiachehr/laravel-logger/actions/runs/44147519382),
commit `75a497fc142b1b437103fdfb30515aaa0cb9dada`) passed all six combinations,
including 67 PHP tests. This is historical PHP compatibility evidence, not a
claim that the 0.2.1 workflow has already run. Results for the current revision
must be checked separately in GitHub Actions.

## Coverage limits

- PHPUnit covers HTTP behavior in Laravel's test application; it does not execute
  client-side interactions, clipboard APIs or visual/accessibility behavior.
- Local validation uses PHP 8.4. Other supported PHP/framework combinations are
  exercised by the remote matrix. Windows file storage is not supported/tested
  in this POSIX implementation.
- Octane lifecycle events are exercised in-process, not on an actual Octane,
  Swoole or RoadRunner server. No external queue daemon is required by the tests.
- Previously measured file-reader benchmarks remain in
  [performance.md](performance.md). They describe warm local filesystem reads,
  not production concurrency, cold-disk latency or authentication/cache overhead.
- Reverse-proxy/TLS deployment and human assistive-technology review are outside
  the automated suite. No real operator credentials are used by tests.
