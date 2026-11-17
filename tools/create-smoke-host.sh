#!/usr/bin/env bash
set -eu
if [ "$#" -ne 1 ] || [ -e "$1" ]; then
    echo 'Usage: tools/create-smoke-host.sh /absolute/path/to/NEW-host-directory' >&2
    exit 1
fi
package_root="$(cd "$(dirname "$0")/.." && pwd -P)"
target="$1"
composer create-project laravel/laravel "$target" "${LARAVEL_SKELETON_VERSION:-^13.0}" --prefer-dist --no-interaction
php "$package_root/tools/prepare-smoke-host.php" "$target"
composer --working-dir="$target" update fiachehr/laravel-logger --with-all-dependencies --no-interaction
php "$target/artisan" config:cache
php "$target/artisan" route:cache
php "$package_root/tools/seed-smoke-host.php" "$target"
php "$target/artisan" log-explorer:doctor
printf 'Smoke host ready. Run: php %s/artisan log-explorer:serve --port=18765\n' "$target"
