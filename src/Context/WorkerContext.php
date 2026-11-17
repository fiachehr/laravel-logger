<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Context;

final class WorkerContext
{
    public array $values = [];

    public function clear(): void
    {
        $this->values = [];
    }
}
