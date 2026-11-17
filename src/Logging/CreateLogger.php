<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Logging;

use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class CreateLogger
{
    public function __invoke(array $config): Logger
    {
        if (! config('log-explorer.enabled', false)) {
            return new Logger('observability', [new NullHandler]);
        }
        $handler = new JsonlHandler(app(JsonlFiles::class), $config['level'] ?? config('log-explorer.level', 'debug'));
        $handler->setFormatter(app(JsonFormatter::class));

        return new Logger('observability', [$handler]);
    }
}
