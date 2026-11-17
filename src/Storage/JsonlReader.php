<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Storage;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Validation\ValidationException;
use Throwable;

final class JsonlReader implements LogSource
{
    public function __construct(private JsonlFiles $files, private Encrypter $crypt) {}

    public function search(array $filters, ?string $cursor = null, int $limit = 50): array
    {
        $order = ($filters['order'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
        ksort($filters);
        $hash = hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        if ($cursor !== null) {
            try {
                $state = json_decode($this->crypt->decrypt($cursor, false), true, 32, JSON_THROW_ON_ERROR);
                if (($state['expires'] ?? 0) < time() || ($state['filters'] ?? '') !== $hash || ! is_array($state['files'] ?? null)) {
                    throw new \RuntimeException;
                }
            } catch (Throwable) {
                throw ValidationException::withMessages(['cursor' => 'Cursor expired or invalid; refresh the search.']);
            }
        } else {
            $state = ['expires' => time() + 900, 'filters' => $hash, 'files' => $this->files->snapshots($order)];
        }
        $limit = max(1, min(100, $limit));
        $items = [];
        $scanned = 0;
        $malformed = 0;
        $rotated = 0;
        $budget = $this->files->limit('max_scan_bytes', 8 * 1024 * 1024, 32 * 1024 * 1024);
        $deadline = hrtime(true) + $this->files->limit('max_scan_ms', 250, 1000) * 1000000;
        while ($state['files'] !== [] && count($items) < $limit && $scanned < $budget && hrtime(true) < $deadline) {
            $file = &$state['files'][0];
            $handle = $this->files->open($file);
            if ($handle === null) {
                $rotated++;
                array_shift($state['files']);
                unset($file);

                continue;
            }
            try {
                // Read one bounded block plus one bounded record to locate line boundaries.
                $remaining = $order === 'newest' ? $file['offset'] : $file['size'] - $file['offset'];
                if ($remaining <= 0) {
                    array_shift($state['files']);
                    unset($file);

                    continue;
                }
                $length = min(65537, $remaining, $budget - $scanned);
                $start = $order === 'newest' ? $file['offset'] - $length : $file['offset'];
                fseek($handle, $start);
                $block = fread($handle, $length);
                if ($block === false || $block === '') {
                    array_shift($state['files']);
                    unset($file);

                    continue;
                }
                $scanned += strlen($block);
                if ($order === 'newest') {
                    $end = strlen($block);
                    // A snapshot ending in a partial line must never display that fragment.
                    if ($file['offset'] === $file['size'] && ! str_ends_with($block, "\n")) {
                        $last = strrpos($block, "\n");
                        $end = $last === false ? 0 : $last + 1;
                        $malformed++;
                    }
                    $prefixEnd = strpos($block, "\n");
                    $safeStart = $start === 0 ? 0 : ($prefixEnd === false ? $end : $prefixEnd + 1);
                    $position = $end;
                    while ($position > $safeStart && count($items) < $limit && hrtime(true) < $deadline) {
                        $previous = strrpos(substr($block, 0, max(0, $position - 1)), "\n");
                        $lineStart = $previous === false ? 0 : $previous + 1;
                        if ($lineStart < $safeStart) {
                            break;
                        }
                        $line = substr($block, $lineStart, $position - $lineStart);
                        $this->collect($line, $filters, $items, $malformed);
                        $position = $lineStart;
                    }
                    $file['offset'] = $start + $position;
                    if ($position === $end || ($position > 0 && $position <= $safeStart)) {
                        // Long/partial records: discard a bounded fragment and continue backwards.
                        $file['offset'] = $start + $safeStart;
                        if ($file['offset'] >= $start + strlen($block)) {
                            $file['offset'] = $start;
                        }
                        $malformed++;
                    }
                } else {
                    $position = 0;
                    while (($newline = strpos($block, "\n", $position)) !== false && count($items) < $limit && hrtime(true) < $deadline) {
                        if ($file['discard'] ?? false) {
                            $file['discard'] = false;
                        } else {
                            $this->collect(substr($block, $position, $newline - $position + 1), $filters, $items, $malformed);
                        }
                        $position = $newline + 1;
                    }
                    if ($position === 0) {
                        $position = strlen($block);
                        $malformed++;
                        $file['discard'] = ! str_ends_with($block, "\n");
                    }
                    $file['offset'] += $position;
                }
            } finally {
                fclose($handle);
            }
            unset($file);
        }
        // Remove exhausted snapshots so the UI knows when it reached the actual end.
        while ($state['files'] !== [] && ($order === 'newest' ? $state['files'][0]['offset'] <= 0 : $state['files'][0]['offset'] >= $state['files'][0]['size'])) {
            array_shift($state['files']);
        }

        return [
            'data' => $items,
            'next_cursor' => $state['files'] === [] ? null : $this->crypt->encrypt(json_encode($state, JSON_THROW_ON_ERROR), false),
            'meta' => ['scanned_bytes' => $scanned, 'malformed_entries' => $malformed, 'rotated_files' => $rotated,
                'bounded' => $scanned >= $budget || hrtime(true) >= $deadline, 'order' => $order],
        ];
    }

    private function collect(string $line, array $filters, array &$items, int &$malformed): void
    {
        if (strlen($line) > 65537 || ! str_ends_with($line, "\n")) {
            $malformed++;

            return;
        }
        try {
            $entry = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $malformed++;

            return;
        }
        if (! is_array($entry) || ($entry['schema_version'] ?? '') !== '1.0' || ! is_string($entry['level'] ?? null)
            || ! is_string($entry['timestamp'] ?? null) || ! is_string($entry['message'] ?? null)) {
            $malformed++;

            return;
        }
        if (! $this->validShape($entry)) {
            $malformed++;

            return;
        }
        foreach (['level', 'service', 'event', 'request_id'] as $field) {
            if (isset($filters[$field]) && ($entry[$field] ?? null) !== $filters[$field]) {
                return;
            }
        }
        foreach (['route' => $entry['context']['route_name'] ?? '', 'status' => $entry['context']['status_code'] ?? '', 'exception' => $entry['exception']['type'] ?? ''] as $field => $value) {
            if (isset($filters[$field]) && (! is_scalar($value) || (string) $value !== (string) $filters[$field])) {
                return;
            }
        }
        if (isset($filters['from']) && strcmp($entry['timestamp'], $filters['from']) < 0) {
            return;
        }
        if (isset($filters['until']) && strcmp($entry['timestamp'], $filters['until']) > 0) {
            return;
        }
        if (isset($filters['q']) && ! str_contains(mb_strtolower($line), mb_strtolower($filters['q']))) {
            return;
        }
        $items[] = ['entry' => $entry, 'raw' => rtrim($line, "\r\n")];
    }

    private function validShape(array $entry): bool
    {
        foreach (['service', 'event', 'request_id', 'environment', 'application'] as $field) {
            if (isset($entry[$field]) && ! is_string($entry[$field])) {
                return false;
            }
        }
        if (isset($entry['context']) && ! is_array($entry['context'])) {
            return false;
        }
        if (isset($entry['exception'])) {
            if (! is_array($entry['exception']) || ! $this->validException($entry['exception'], 0)) {
                return false;
            }
        }

        return true;
    }

    private function validException(array $exception, int $depth): bool
    {
        if ($depth > 5) {
            return false;
        }
        foreach (['type', 'message', 'file'] as $field) {
            if (isset($exception[$field]) && ! is_string($exception[$field])) {
                return false;
            }
        }
        $trace = $exception['trace'] ?? [];
        if (! is_array($trace) || ! array_is_list($trace) || count($trace) > 50) {
            return false;
        }
        foreach ($trace as $frame) {
            if (! is_array($frame)) {
                return false;
            }
            foreach (['file', 'class', 'method'] as $field) {
                if (isset($frame[$field]) && ! is_string($frame[$field])) {
                    return false;
                }
            }
        }

        return ! isset($exception['previous']) || (is_array($exception['previous']) && $this->validException($exception['previous'], $depth + 1));
    }
}
