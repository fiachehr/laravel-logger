# JSONL schema 1.0

One UTF-8 JSON object followed by LF. Keys are stable within schema version 1.0;
optional fields may be absent. JSON objects are preserved for empty context.

| Field | Type / meaning |
|---|---|
| schema_version | string, `1.0` |
| timestamp | UTC ISO 8601 with milliseconds, `2026-11-17T12:34:56.789Z` |
| level | DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY |
| environment/application/service | bounded, approved categorical identifiers |
| event | approved categorical identifier, defaults `application.log` |
| message | categorical `Application log event`, or explicitly opted-in redacted text |
| request_id | optional validated opaque correlation ID; queue jobs get independent IDs |
| context | filtered object: operation, method, route_name, route template, status_code, duration_ms, count, code, attempt, job_class; extra fields require safe mode + allowlist |
| exception | optional type, categorical/redacted message, relative origin file, integer line, bounded trace; `application_frame` is explicitly inferred, not the exception origin |
| exception.trace | ordered bounded frames: optional file, line, class, method; never args |
| exception.previous | bounded normalized previous exception chain |
| diagnostic | optional static label, type/class/count/approved-fields summary, helper call file/line |

External paths become basenames; invalid paths become `[external]`. Origin comes
from `Throwable::getFile/getLine`; origin class/method are not fabricated. Inferred
application frames may be unavailable or imperfect (e.g. external basename paths).
Truncated events reduce to schema/timestamp/level/request_id and a fixed
`logging.event_truncated` event/message. Encoding failures use a minimal constant
`logging.encoding_failed` JSON object; timestamp/context may be absent in that
emergency fallback. Such minimal records are skipped by the dashboard reader,
which requires timestamp, level and message. No raw-value error fallback exists.

Extra Monolog `extra` processors are not automatically trusted or serialized.
Context filtering precedes encoding and no PSR-3 placeholder interpolation is
performed on potentially sensitive arbitrary context objects.

## Log source extension

Bind `Fiachehr\LaravelLogger\Storage\LogSource` to your own implementation of
`search(array $filters, ?string $cursor = null, int $limit = 50): array`.
Return `data` as a list of `{entry: object, raw: sanitized JSON string}`, an opaque
`next_cursor` or null, and `meta` with scanned_bytes/malformed_entries/rotated_files/
bounded/order. Enforce the same privacy, authorization-bound source, cursor and
resource guarantees. The dashboard is a single configured source: returning
per-user data requires binding a source that applies the host's own tenant policy.

To customize sanitization, change approved config fields; PrivacyPolicy is deliberately
not an unredacted bypass interface. No optional remote adapters ship in this release.
