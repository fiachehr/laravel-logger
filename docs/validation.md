# Validation record — 0.2.0

Executed 2026-11-17 on macOS arm64 with PHP 8.4.5, Composer 2.8.6 and Node 22.14.0.

- `composer check`: Pint, PHPStan level 6, and **67 PHP tests / 400 assertions**
  passed with Laravel 13.43.0 / Testbench 11.3.0. An earlier 66-test run also
  passed with Laravel 12.73.0 / Testbench 10.12.0 before the final transient-store
  configuration regression case was added. Final matrix results are recorded separately.
- `npm test`: **9 Chromium browser tests** passed across two real Laravel 13.43.0
  hosts with cached configuration/routes, one using host guard/Gate mode and one
  using independent credentials. The credentials host used file sessions/cache
  and had no database file at its configured database path during the passing run.
- New tests cover all protected log endpoint classes, mandatory login/logout CSRF,
  host-auth isolation, generic failures without credential logging/flash input,
  IP/account attempt budgets, idle/absolute expiry, username rotation, cache loss,
  failed token registration, revoked-session replay and host-session preservation.
  The password command was exercised with hidden synthetic input and its output
  verified as a hash without the plaintext password.
- The standalone Blade login was browser-checked with axe and keyboard navigation
  in desktop light/dark and 390px mobile layouts. Real screenshots were captured
  and inspected. Existing table/filter/detail/XSS/clipboard tests still pass.
- `composer validate --strict`, `composer audit`, `npm audit` and asset formatting
  checks passed; dependency audits reported no vulnerabilities.
- Provider discovery, `config:cache`, `route:cache`, loopback-only development
  serving and mode-aware `log-explorer:doctor` passed in both clean host modes.

The extension does not change the JSON schema, formatter or reader. Previously
published file-reader benchmark results remain their own measured run; they do not
measure the new authentication/cache overhead. Human assistive-technology testing,
Firefox/WebKit, actual Octane servers and production reverse-proxy deployment remain
outside this validation. No real operator credentials were used or committed.

Remote CI results for the 0.2 change will be linked after execution. The initial
0.1 compatibility evidence below is historical, not a substitute for checking the
new authentication code on the supported PHP/Laravel matrix.

---

## Initial 0.1 validation — 2026-11-17

## Local environment and executed checks

macOS arm64, PHP 8.4.5, Composer 2.8.6, Node 22.14.0, npm 10.9.2.
Monolog 3.13.0, Pint 1.32.3, PHPStan 2.3.6 (level 6; generic array value-type
annotations are not enforced), PHPUnit 12.5.44.

| Check                  | Command / setup                                                                                                             | Result                                                                                                                                                 |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Laravel 12 integration | `composer update --with 'orchestra/testbench:^10.0' --with 'laravel/framework:^12.0' -W --no-interaction`, `composer check` | Laravel 12.72.0 / Testbench 10.12.0; 51 tests passed (259 assertions before final disabled-middleware assertion addition); Pint and PHPStan passed     |
| Laravel 13 integration | `composer update --with 'orchestra/testbench:^11.0' --with 'laravel/framework:^13.0' -W --no-interaction`, `composer check` | Laravel 13.42.0 / Testbench 11.3.0; 51 tests, 261 assertions passed; Pint and PHPStan passed                                                           |
| Composer metadata      | `composer validate --strict`                                                                                                | Valid                                                                                                                                                  |
| Composer dependencies  | `composer audit`                                                                                                            | No security vulnerability advisories found                                                                                                             |
| Frontend dependencies  | `npm ci` / `npm audit`                                                                                                      | No vulnerabilities                                                                                                                                     |
| JavaScript             | `node --check resources/assets/explorer-v1.js`                                                                              | Passed                                                                                                                                                 |
| Clean Laravel 13 host  | `tools/create-smoke-host.sh /absolute/new-host`, auto-discovery, `config:cache`, `route:cache`, seed, `log-explorer:doctor` | Installed by Composer path repository; all doctor checks passed                                                                                        |
| Clean Laravel 12 host  | `LARAVEL_SKELETON_VERSION='^12.0' tools/create-smoke-host.sh /absolute/new-host`                                            | Installed, discovered, caches built, seeded, all doctor checks passed                                                                                  |
| Dedicated port         | Both clean hosts: `php artisan log-explorer:serve --port=18765` / `18766`                                                   | Actual Laravel servers launched on 127.0.0.1 with host middleware; production refusal and port validation integration-tested                           |
| Browser on Laravel 13  | `npm test` against cached real smoke host                                                                                   | 5 Playwright tests passed; Chromium, real session/CSRF, filters/paging/grouping/tabs/clipboard/theme/mobile, hostile response rendering and axe checks |
| Browser on Laravel 12  | `LOG_EXPLORER_TEST_URL=http://127.0.0.1:18766 npm test`                                                                     | 5 Playwright tests passed; same real-host suite                                                                                                        |
| Performance            | `php tools/benchmark.php /absolute/smoke-host`                                                                              | 10K/100K/1M datasets measured; methodology and raw results in performance.md / benchmark-results.json                                                  |

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

## Remote compatibility CI

[Run 44025230219](https://github.com/fiachehr/laravel-logger/actions/runs/44025230219)
passed for commit `254d62c3ae8ad1589b7e8ae32c142a9f908a1c69`:

| PHP minor | Laravel 12 | Laravel 13 |
| --------- | ---------- | ---------- |
| 8.3       | Passed     | Passed     |
| 8.4       | Passed     | Passed     |
| 8.5       | Passed     | Passed     |

Every matrix job passed strict Composer validation, dependency resolution, Pint,
PHPStan, all PHP tests, and Composer audit. The separate Linux browser job also
passed npm install/audit, clean host creation with config/route caching, the five
Playwright/axe tests and artifact upload. Actions are pinned to verified release
commit SHAs. This is executed evidence, rather than merely a configured matrix.

## Coverage limits and publication boundaries

- Local binaries were available only for PHP 8.4.5. PHP 8.3 and 8.5 were verified
  remotely on Linux in all six PHP/Laravel combinations above. Windows file
  storage is not supported or tested in this initial POSIX implementation.
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
