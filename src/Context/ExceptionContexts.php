<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Context;

use Throwable;
use WeakMap;

final class ExceptionContexts
{
    /** @var WeakMap<Throwable, array> */
    private WeakMap $contexts;

    public function __construct()
    {
        $this->contexts = new WeakMap;
    }

    public function remember(Throwable $exception, array $context): void
    {
        // Laravel may report outside the middleware stack, after its finally block.
        $this->contexts[$exception] = $context;
    }

    public function take(Throwable $exception): ?array
    {
        $context = $this->contexts[$exception] ?? null;
        unset($this->contexts[$exception]);

        return $context;
    }

    public function clear(): void
    {
        $this->contexts = new WeakMap;
    }
}
