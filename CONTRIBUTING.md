# Contributing

Open an issue describing the concrete behavior and proposed change. Keep privacy
controls enabled; new fields need explicit collection rationale and adversarial
canary coverage. Never commit real credentials, private logs or personal data.

Use Composer, PSR-4, PSR-12/Laravel Pint. Run `composer check`, `composer audit`,
`node --check resources/assets/explorer-v1.js` and browser tests for UI changes.
The README documents a clean local smoke host. Add meaningful regression tests,
update documentation/changelog, and state tested PHP/framework versions. Avoid
new runtime/frontend dependencies without a concrete need.

Run framework targets explicitly with `composer update --with 'orchestra/testbench:^10.0'
--with 'laravel/framework:^12.0' -W` or Testbench 11 / Laravel 13, then `composer check`.
CI executes PHP 8.3/8.4/8.5 for both targets. Dependencies are locked for maintainer
reproducibility, not used by consuming applications.
