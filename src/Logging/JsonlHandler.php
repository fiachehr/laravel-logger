<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Logging;

use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class JsonlHandler extends AbstractProcessingHandler
{
    public function __construct(private JsonlFiles $files, int|string|Level $level = 'debug')
    {
        parent::__construct($level, true);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->files->append((string) $record->formatted);
        } catch (Throwable) {
            error_log('Log explorer: structured log write rejected; run log-explorer:doctor.');
        }
    }
}
