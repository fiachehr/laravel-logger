<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Fiachehr\LaravelLogger\Logging\CreateLogger;
use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Access\Gate;

final class DoctorCommand extends Command
{
    protected $signature = 'log-explorer:doctor';

    protected $description = 'Check log explorer setup without printing private configuration or log contents';

    public function handle(JsonlFiles $files, Gate $gate): int
    {
        $checks = [];
        $checks['Logging explicitly enabled'] = (bool) config('log-explorer.enabled');
        $channel = config('logging.channels.'.config('log-explorer.channel', 'observability'), []);
        $checks['Custom channel configured'] = ($channel['via'] ?? null) === CreateLogger::class;
        $login = app(DashboardLogin::class);
        $checks['Supported dashboard authentication mode'] = in_array($login->mode(), ['credentials', 'host'], true);
        if ($login->mode() === 'credentials') {
            $checks['Independent username and password hash configured'] = $login->configured();
            $checks['Web session middleware selected'] = in_array('web', config('log-explorer.dashboard.middleware', []), true);
            $cacheDriver = config('cache.stores.'.config('cache.default').'.driver');
            $checks['Persistent cache selected for sessions and login limits'] = is_string($cacheDriver) && ! in_array($cacheDriver, ['array', 'null'], true);
        } else {
            $checks['Explicit dashboard Gate defined'] = $gate->has(config('log-explorer.dashboard.gate', 'viewLogExplorer'));
            $checks['Host auth guard configured'] = config('auth.guards.'.(config('log-explorer.dashboard.guard') ?: config('auth.defaults.guard'))) !== null;
        }
        $checks['Application encryption key present'] = (bool) config('app.key');
        try {
            $root = $files->root();
            $checks['Canonical log directory exists and is writable'] = $root !== null && is_writable($root);
            $checks['Log directory permissions are private'] = $root !== null && (fileperms($root) & 0077) === 0;
            $snapshots = $files->snapshots('newest');
            $checks['Daily file quotas available'] = count(array_filter($snapshots, fn ($file) => $file['size'] >= $files->limit('max_file_bytes', 64 * 1024 * 1024, 1024 * 1024 * 1024))) === 0;
        } catch (\Throwable) {
            $checks['Storage safe and readable'] = false;
        }
        $checks['Bundled dashboard assets available'] = is_readable(__DIR__.'/../../resources/assets/explorer-v1.js') && is_readable(__DIR__.'/../../resources/assets/explorer-v1.css');
        if ($login->mode() === 'credentials') {
            $checks['Bundled Blade login and stylesheet available'] = is_readable(__DIR__.'/../../resources/views/login.blade.php') && is_readable(__DIR__.'/../../resources/assets/login-v1.css');
        }
        foreach ($checks as $name => $ok) {
            $this->line(($ok ? '[OK] ' : '[CHECK] ').$name);
        }
        $this->line('This checks configuration only; verify the selected dashboard login, authorization and sign-out in a browser.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
