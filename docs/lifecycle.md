# Upgrade, rollback, uninstall

Use semantic version tags and review CHANGELOG before updating. In 0.x releases,
minor version changes may break APIs/configuration; patches are compatible fixes.
The JSON schema has its own version. Keep a copy of host configuration before
upgrading and compare the package's new defaults manually; publishing never
merges or overwrites your existing configuration automatically.

Upgrade with `composer update fiachehr/laravel-logger`, then clear/rebuild host
configuration/routes and restart long-running workers/Octane. Run doctor, log a
synthetic event, verify its privacy, sign in with an authorized account, and check
that another account is denied. No migrations or persisted record changes occur.

Rollback by restoring the previous Composer constraint/lockfile and running
`composer install`, rebuilding host caches and restarting workers. Logs remain
on disk; new optional schema fields may not be understood by older versions.
Pin the previous known tag rather than editing installed vendor files.

Uninstall: remove the channel from stacks/defaults first, remove correlation
middleware and diagnostic calls/imports, disable both flags, then
`composer remove fiachehr/laravel-logger`. Remove published config and host Gate (if using host mode)
when no longer used; rebuild caches and restart workers. Logs are never deleted
by uninstall. Follow your own privacy/retention policy to archive/delete them.

## Troubleshooting

- 404: check both enable flags and cached routes/prefix configuration.
- 401: sign in using the configured host guard on the correct origin.
- 403: define the configured Gate and verify the user's permission.
- 422: invalid filter/cursor; reset search. Cursors expire after 15 minutes and
  cannot be reused with different filters.
- 429: pause polling; review shared cache and the configured rate budget.
- 503: run doctor; check canonical storage, permissions and directory-entry quota.
- Missing records: check channel/stack selection, level threshold, enable flag,
  daily size cap and stderr. A disabled package channel discards records routed
  to it. Refresh after rotation. Schedule `log-explorer:prune` for idle retention.
  The reader only understands
  this package's files/schema and skips partial or malformed data.
- No request route: register correlation as route middleware, not before routing.
- No exception correlation: ensure the request uses correlation middleware and
  the host's exception reporter routes through the structured channel.
- No diagnostic values: metadata mode intentionally shows a type/count summary.
  Approve selected fields in safe mode rather than logging object graphs.

## 0.1 to 0.2

Merge the new `dashboard.auth` block into a published configuration to explicitly
choose `credentials` or `host`. Published configurations missing the block retain
host mode; fresh configs select credentials. Independent mode requires a username,
supported hash, `web` sessions and persistent cache; remove host login middleware
only when intentionally selecting independent access. Keep host authorization for
host mode. Rebuild caches/restart workers in the deployment runtime. Changing the
username/hash invalidates existing independent sessions. Do not overwrite published
configuration blindly. The JSON schema and log files remain compatible.
