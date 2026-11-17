<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Diagnostics;

use Illuminate\Support\Facades\Log;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class Diagnostics
{
    public function dump(mixed $value, string $label = 'diagnostic', ?array $source = null): void
    {
        if (! config('log-explorer.enabled') || ! config('log-explorer.diagnostics.enabled')) {
            return;
        }
        $source ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        Log::channel(config('log-explorer.channel'))->debug('Diagnostic event', [
            'event' => 'diagnostic.dump',
            '_diagnostic' => new DiagnosticRecord($value, $label, $source['file'] ?? '', (int) ($source['line'] ?? 0)),
        ]);
    }

    public function dumpAndStop(mixed $value, string $label = 'diagnostic', ?array $source = null): never
    {
        if (! app()->environment(['local', 'development']) || ! config('log-explorer.diagnostics.allow_stop')
            || ! config('log-explorer.enabled') || ! config('log-explorer.diagnostics.enabled')) {
            throw new LogicException('Terminating log diagnostics are disabled.');
        }
        $this->dump($value, $label, $source ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]);
        // An exception unwinds middleware/finally blocks; do not exit a worker process.
        throw new HttpException(500, 'Execution stopped by an explicit development diagnostic.');
    }
}
