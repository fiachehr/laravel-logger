<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Fiachehr\LaravelLogger\Logging\CreateLogger;
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
        $checks['Explicit dashboard Gate defined'] = $gate->has(config('log-explorer.dashboard.gate', 'viewLogExplorer'));
        $checks['Host auth guard configured'] = config('auth.guards.'.(config('log-explorer.dashboard.guard') ?: config('auth.defaults.guard'))) !== null;
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
        foreach ($checks as $name => $ok) {
            $this->line(($ok ? '[OK] ' : '[CHECK] ').$name);
        }
        $this->line('This checks configuration only; verify real guard login and Gate behavior in the host app.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
