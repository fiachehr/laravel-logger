# Changelog

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

Pre-1.0 API; see docs/validation.md for actual tested combinations and limits.
No Packagist submission, hosted service, automatic global logger interception,
native dump interception, remote adapters or tenant isolation in this release.
