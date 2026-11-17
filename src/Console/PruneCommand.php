<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Illuminate\Console\Command;
use Throwable;

final class PruneCommand extends Command
{
    protected $signature = 'log-explorer:prune';

    protected $description = 'Delete expired daily log files without waiting for the next write';

    public function handle(JsonlFiles $files): int
    {
        try {
            $removed = $files->pruneExpired();
        } catch (Throwable) {
            $this->error('Log storage is unavailable. Run log-explorer:doctor.');

            return self::FAILURE;
        }
        $this->line('Removed '.$removed.' expired log file(s).');

        return self::SUCCESS;
    }
}
