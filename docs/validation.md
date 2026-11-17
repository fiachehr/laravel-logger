# Validation record — 2026-11-17

## Local environment and executed checks

macOS arm64, PHP 8.4.5, Composer 2.8.6, Node 22.14.0, npm 10.9.2.
Monolog 3.13.0, Pint 1.32.3, PHPStan 2.3.6 (level 6; generic array value-type
annotations are not enforced), PHPUnit 12.5.44.

| Check | Command / setup | Result |
|---|---|---|
| Laravel 12 integration | `composer update --with 'orchestra/testbench:^10.0' --with 'laravel/framework:^12.0' -W --no-interaction`, `composer check` | Laravel 12.72.0 / Testbench 10.12.0; 51 tests passed (259 assertions before final disabled-middleware assertion addition); Pint and PHPStan passed |
| Laravel 13 integration | `composer update --with 'orchestra/testbench:^11.0' --with 'laravel/framework:^13.0' -W --no-interaction`, `composer check` | Laravel 13.42.0 / Testbench 11.3.0; 51 tests, 261 assertions passed; Pint and PHPStan passed |
| Composer metadata | `composer validate --strict` | Valid |
| Composer dependencies | `composer audit` | No security vulnerability advisories found |
| Frontend dependencies | `npm ci` / `npm audit` | No vulnerabilities |
| JavaScript | `node --check resources/assets/explorer-v1.js` | Passed |
| Clean Laravel 13 host | `tools/create-smoke-host.sh /absolute/new-host`, auto-discovery, `config:cache`, `route:cache`, seed, `log-explorer:doctor` | Installed by Composer path repository; all doctor checks passed |
| Clean Laravel 12 host | `LARAVEL_SKELETON_VERSION='^12.0' tools/create-smoke-host.sh /absolute/new-host` | Installed, discovered, caches built, seeded, all doctor checks passed |
| Dedicated port | Both clean hosts: `php artisan log-explorer:serve --port=18765` / `18766` | Actual Laravel servers launched on 127.0.0.1 with host middleware; production refusal and port validation integration-tested |
| Browser on Laravel 13 | `npm test` against cached real smoke host | 5 Playwright tests passed; Chromium, real session/CSRF, filters/paging/grouping/tabs/clipboard/theme/mobile, hostile response rendering and axe checks |
| Browser on Laravel 12 | `LOG_EXPLORER_TEST_URL=http://127.0.0.1:18766 npm test` | 5 Playwright tests passed; same real-host suite |
| Performance | `php tools/benchmark.php /absolute/smoke-host` | 10K/100K/1M datasets measured; methodology and raw results in performance.md / benchmark-results.json |

Core coverage includes secret-key variants and canaries, unknown object hooks,
UTF-8, event bounds and formatter fallback, exact exception origin, bounded chains,
request IDs and route-template privacy, zero SQL enrichment, no auth resolution,
framework 404 reporting policy, queue success/failure and simulated Octane boundaries,
read pagination across appends/rotation, literal filters, cursor tamper/expiry/filter
binding, malformed and oversized fragments, malicious JSON shapes, symlink/hardlink
rejection, private permissions, retention, and four concurrent writer processes.
Dashboard tests cover every route class (page/JSON/assets), missing/denied Gate,
production denial, no-store/CSP/nosniff, rate limits, invalid filters, no export or
mutation routes, and secret canary absence from stored/API/HTML data. Browser XSS
fixtures put hostile content in messages, names, context, traces and raw JSON.

The earlier validation runs exposed real defects in canonical temporary test paths,
route-template redaction, accessible labels and Reset's HTMLFormElement name collision;
all were corrected before the final passing runs. No failed check is presented as passed.

## Coverage limits and publication boundaries

- PHP 8.3 and 8.5 are CI targets; no local binaries were available. The six-cell
  PHP/Laravel workflow must pass before claiming every combination verified.
  Local success is established only for PHP 8.4.5 with Laravel 12 and 13.
- Octane lifecycle events are exercised in-process, not against an actual Octane /
  Swoole / RoadRunner server. Queue event integration and context reset are tested;
  no external Redis/database queue daemon was deployed.
- Browser coverage is Chromium, desktop 1440px and mobile 390px; Firefox/WebKit
  and human assistive-technology review were not run. Axe is automated evidence,
  not a blanket accessibility certification. Real captured screenshots were inspected.
- Benchmarks are warm local filesystem measurements, not network storage,
  production concurrency, cold-disk, hard latency or fsync durability guarantees.
- Nginx/TLS examples were documented, not deployed. Docker daemon was unavailable;
  this implementation and validation require no Docker.
- Packagist submission, webhooks, GitHub Release creation and production deployment
  were not performed or claimed. The owner explicitly authorized branch/tag push.
- Public repository visibility was verified using the connected GitHub repository
  metadata. Packagist package URL returned 404 during setup; submission is manual.
