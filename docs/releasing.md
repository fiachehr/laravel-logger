# Release procedure

1. Run `composer validate --strict`, `composer check`, `composer audit`, `npm ci`,
   `npm audit`, JavaScript syntax check and real-host browser tests. Test framework
   12/Testbench 10 and 13/Testbench 11. Inspect the six-cell PHP matrix before
   advertising full version coverage; record gaps in validation.md.
2. Recreate the local smoke host from scratch. Check Composer auto-discovery,
   cached config/routes, doctor, login/denial, UI and dedicated loopback serving.
   Re-run benchmarks if reader, formatter or storage behavior changes.
3. Review every file for secrets and scope. Update changelog, validation record
   and real screenshots. `git archive` should include source, config, routes,
   license and bundled assets, with no vendor, node_modules or local .env.
4. With owner authorization, commit and create an annotated semantic version tag,
   e.g. `git tag -a v0.1.0 -m 'v0.1.0: initial privacy-first log explorer'`, then
   push the branch and tag. Compare local and remote commit/tag refs. Never
   force-push an existing published version. A GitHub Release is a separate action.
5. Verify remote CI. Publishing tags is not proof that CI ran or passed. If a
   remote check fails, fix it and publish an appropriate new patch tag; do not
   move a released tag.

## Packagist checklist (maintainer action)

- GitHub connector confirmed ownership and **public** repository visibility during
  initial release preparation. Recheck visibility before subsequent publication;
  SSH push access alone does not prove it.
- `fiachehr/laravel-logger` follows the existing repository identity. The Packagist
  package URL returned HTTP 404 during initial setup on 2026-11-17; availability
  and vendor ownership must be rechecked during submission. No reservation is implied.
- Confirm MIT license (approved by owner in the implementation session), valid
  composer.json metadata, provider discovery, package contents and semantic tags.
- Submit the public repository URL via the maintainer's Packagist account.
  This session did not authorize submission or provide Packagist credentials.
- Configure GitHub/Packagist auto-updates only with explicit authorization.
- Verify Composer can resolve/install the tag from Packagist in a clean host.

Before Packagist registration, a consumer with repository access can use:

```bash
composer config repositories.laravel-logger vcs https://github.com/fiachehr/laravel-logger
composer require fiachehr/laravel-logger:^0.1
```

The path-repository development fixture does not prove Packagist registration.
A git push/tag does not create a GitHub Release or enable private vulnerability
reporting. Those remain separate maintainer operations.
