<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Security;

use Throwable;

final class ExceptionNormalizer
{
    public function __construct(private PrivacyPolicy $policy, private string $basePath) {}

    public function path(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', $this->basePath), '/').'/';
        $relative = str_starts_with($file, $base) ? substr($file, strlen($base)) : basename($file);

        return preg_match('#\A[a-zA-Z0-9_./-]{1,250}\z#D', $relative) && ! str_contains($relative, '..')
            ? $relative : '[external]';
    }

    public function normalize(Throwable $exception, int $depth = 0): array
    {
        $frames = [];
        foreach (array_slice($exception->getTrace(), 0, $this->policy->limit('max_trace_frames', 15, 50)) as $frame) {
            $frames[] = array_filter([
                'file' => isset($frame['file']) ? $this->path($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'class' => isset($frame['class']) ? $this->policy->identifier($frame['class']) : null,
                'method' => isset($frame['function']) ? $this->policy->identifier($frame['function']) : null,
            ], static fn ($value) => $value !== null);
        }
        $result = [
            'type' => $this->policy->identifier(get_class($exception)),
            'message' => $this->policy->message($exception->getMessage()),
            'file' => $this->path($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $frames,
        ];
        foreach ($frames as $frame) {
            if (isset($frame['file']) && ! str_starts_with($frame['file'], 'vendor/') && str_contains($frame['file'], '/')) {
                $result['application_frame'] = $frame;
                break;
            }
        }
        if ($exception->getPrevious() !== null && $depth < $this->policy->limit('max_previous_exceptions', 2, 5)) {
            $result['previous'] = $this->normalize($exception->getPrevious(), $depth + 1);
        }

        return $result;
    }
}
