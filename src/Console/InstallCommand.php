<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Fiachehr\LaravelLogger\Logging\CreateLogger;
use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'log-explorer:install';

    protected $description = 'Publish missing configuration; show opt-in steps without changing host logging or environment';

    public function handle(): int
    {
        $target = config_path('log-explorer.php');
        if (! is_file($target)) {
            if (! $this->input->isInteractive() || ! $this->confirm('Publish config/log-explorer.php? All features remain disabled.', true)) {
                $this->line('No files changed. Use vendor:publish --tag=log-explorer-config when ready.');

                return self::SUCCESS;
            }
            $this->call('vendor:publish', ['--tag' => 'log-explorer-config']);
        } else {
            $this->line('Existing configuration preserved.');
        }
        $this->line('Add an observability custom channel using '.CreateLogger::class.'.');
        $this->line('Enable LOG_EXPLORER_ENABLED, define viewLogExplorer Gate, then opt into the dashboard.');
        $this->line('See README.md for the channel, stack and middleware examples.');

        return self::SUCCESS;
    }
}
