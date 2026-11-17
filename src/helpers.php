<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger;

use Fiachehr\LaravelLogger\Diagnostics\Diagnostics;

function log_dump(mixed $value, string $label = 'diagnostic'): void
{
    app(Diagnostics::class)->dump($value, $label, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
}

function log_dd(mixed $value, string $label = 'diagnostic'): never
{
    app(Diagnostics::class)->dumpAndStop($value, $label, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
}
