# Security and privacy

## Trust boundaries

Host configuration, Gate definitions, OS account and installed PHP code are trusted.
Log messages, context, cursors, query parameters and JSONL contents are untrusted.
The operator owns data approval and filesystem permission policy. An attacker who
can run PHP as the app user can bypass any package-level policy; this package is
not a sandbox. A privileged log operator may view all records in the configured
source. Tenant-scoped authorization/storage is not implemented in v0.1.0.

| Threat | Controls / residual risk |
|---|---|
| Credentials and personal data | Metadata allowlist, normalized sensitive-key denylist, categorical messages by default; no bodies, SQL, auth models or object serialization. Extra approved identifiers/fields are the host's responsibility. |
| Free-text secrets | Categorical defaults; opt-in bounded redaction as defense in depth only. Regex cannot reliably remove arbitrary secrets, names, addresses or arbitrary encodings from prose. |
| Log forging / hostile Unicode | JSON encoding with control escaping, malformed UTF-8 substitution and one line/event; strict size caps, partial/malformed entries skipped. |
| Stored XSS | Blade escaping, DOM text nodes, no HTML insertion, safe highlighting/tokenization and CSP; no source-code viewer or externally loaded assets. |
| Path traversal / symlinks / IDOR | Fixed daily filenames, canonical private root, regular-file and inode checks, encrypted expiring snapshot cursors bound to filters; API takes no paths or arbitrary entry IDs. Protect roots/ancestors from untrusted local writers. |
| SSRF | No remote source URLs, URL fetches, CDN dependencies or download adapters. |
| Unauthorized exposure / privilege escalation | Both flags off by default; explicit host guard + existing Gate required for HTML, API, assets; no local bypass or default role policy; dedicated port gives no privileges. |
| CSRF / sessions | Read-only GET dashboard; no mutation/export/deletion routes. Host `web` stack supplies session/CSRF behavior; configure Secure, HttpOnly, SameSite cookies and TLS. |
| Denial of service | Rate limits, query validation, bounded files/entries/bytes/time/JSON depth, no regex search, per-file write quota; cache must work and be shared across workers. Post-decode and disk syscall costs mean time quotas are cooperative, not hard real-time deadlines. |
| Dump capture | Disabled by default; type summaries, recursive approved fields only; stop rejected outside explicitly enabled local/development. |
| Context leakage | Request attributes with finally cleanup, weak association for deferred exception reports, scoped worker context, explicit queue/console/Octane reset events; no singleton holds a resolved scoped object. |
| Cache / clickjacking | Private no-store responses including assets, nosniff, no-referrer, frame deny and restrictive route-local CSP. Host policies may be stricter. |

There is no database lookup to enrich auth data. The dashboard's own authentication
may legitimately query the host database. The package never lazily resolves an auth
model for logging. Environment, app/service and developer event identifiers are
approved configuration/metadata; do not put user secrets in these names.

## Production checklist

- Keep dashboard off until host authentication and Gate are tested with allowed
  and denied accounts. Use a least-privilege log-operator role.
- Use TLS, secure session cookies, and a shared cache for rate limiting. Restrict
  network exposure through your web server where appropriate.
- Retain default categorical messages. Review every safe field and call site;
  never send sensitive data to other unprotected channels in a stack.
- Keep storage outside the public root, private and canonical; monitor daily quota,
  disk space and constant stderr write-rejection warnings. Bound storage backups too.
- Treat existing log archives as sensitive; this package does not sanitize legacy
  single/daily files or rewrite historical data.
- Verify `doctor`, real host login/denial, a structured log write, queue behavior,
  configuration cache, and local privacy canaries after deployment.
- Do not deploy the test smoke login/provider. Runtime Octane/server variants need
  host integration tests beyond the simulated lifecycle event tests shipped here.

No package can promise that arbitrary application-provided free text is safe.
When unsure, omit the value and use a fixed event code.
