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
    private int $warnedAt = 0;

    private int $suppressed = 0;

    public function __construct(private JsonlFiles $files, int|string|Level $level = 'debug')
    {
        parent::__construct($level, true);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->files->append((string) $record->formatted);
        } catch (Throwable) {
            $this->warnStorageFailure();
        }
    }

    private function warnStorageFailure(): void
    {
        $now = time();
        if ($this->warnedAt !== 0 && ($now - $this->warnedAt) < 60) {
            $this->suppressed++;

            return;
        }
        $note = $this->suppressed > 0 ? ' Suppressed '.$this->suppressed.' similar warning(s).' : '';
        $this->suppressed = 0;
        $this->warnedAt = $now;
        error_log('Log explorer: structured log write rejected; run log-explorer:doctor.'.$note);
    }
}
