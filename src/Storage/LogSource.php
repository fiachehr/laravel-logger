<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Storage;

interface LogSource
{
    public function search(array $filters, ?string $cursor = null, int $limit = 50): array;
}
