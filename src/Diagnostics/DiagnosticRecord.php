<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Diagnostics;

final readonly class DiagnosticRecord
{
    public function __construct(public mixed $value, public string $label, public string $file, public int $line) {}
}
