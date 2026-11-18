# Changelog

## 0.3.1 — 2026-11-18

- Remove the remaining `.github` directory and GitHub Actions workflow.
- Remove the CI badge and update development instructions for manually run checks.

## 0.3.0 — 2026-11-18

- Add browser-saved display settings for system/light/dark themes, accent colors,
  severity coloring, table density, panel width, event inspector layout and messages.
- Persist timezone selection, support resetting display defaults, and pause polling
  while settings are open. Existing saved light/dark theme choices are preserved.
- Remove standalone docs/screenshots, smoke/benchmark tools, community templates
  and redundant root Markdown files; consolidate essential guidance into README
  and SECURITY. Keep the PHP/Laravel CI workflow.

## 0.2.2 — 2026-11-17

- Roll back a short JSONL append so a failed write cannot join the next record.
- Limit storage-failure stderr warnings to one per minute per process.
- Tighten an existing log directory to owner-only mode, or refuse it.
- Keep dashboard search text out of the address bar and submit it as a CSRF-protected POST.
- Add `log-explorer:prune` so retention can run when nothing is being written.
- Fix the severity list so DEBUG is a real option.

## 0.2.1 — 2026-11-17

- Removed browser automation, JavaScript dependency manifests and the frontend CI job.
- Standardized automated tests on PHPUnit with Orchestra Testbench; development
  and CI require only PHP and Composer.
- Updated development, contribution and release instructions to match the PHP-only
  toolchain. The built-in Blade dashboard and its bundled assets are unchanged.

## 0.2.0 — 2026-11-17

- Added a built-in Blade username/password login, independent from application
  users and host Gates, plus package sign-out and a hidden-input password-hash command.
- Added revocable cache-backed session tokens, credential-rotation invalidation,
  idle/absolute timeouts, mandatory sign-in/out CSRF and IP/account login limits.
- Retained host guard/Gate mode and published 0.1 configuration fallback; fresh
  configurations default to separate credentials with both feature flags disabled.
- Added mode-aware doctor/installer guidance, session-expiry feedback, real-host
  browser/auth security tests, login screenshots and setup/URL/troubleshooting docs.
- Reworked README navigation, installation, panel access, examples and project footer.

## 0.1.0 — 2026-11-17

Initial opt-in Laravel 12/13 package, MIT licensed:

- Privacy-first Monolog 3 JSONL channel with stable schema, bounded exception
  origins/traces and defensive encoding fallback.
- Metadata allowlists, categorical messages, recursive secret-key protection,
  safe explicit diagnostic helpers and developer-only execution interruption.
- Request and queue correlation with lifecycle cleanup and deferred exception context.
- Locked private daily files, retention, daily size quota and bounded snapshot
  reader with encrypted expiring cursors, literal filters and append-order sorting.
- Explicitly authorized responsive event explorer with details, trace, context,
  JSON/raw views, request grouping, keyboard controls, clipboard and two themes.
- Installer, doctor, loopback-only development serving, clean-host tooling,
  security/regression tests, browser/axe checks, compatibility CI and benchmarks.

Pre-1.0 API; see README.md for validation scope and integration limits.
No Packagist submission, hosted service, automatic global logger interception,
native dump interception, remote adapters or tenant isolation in this release.
