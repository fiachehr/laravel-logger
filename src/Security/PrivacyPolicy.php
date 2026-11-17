<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Security;

final class PrivacyPolicy
{
    private const METADATA = ['operation', 'method', 'route_name', 'route', 'status_code', 'duration_ms', 'count', 'code', 'attempt', 'job_class'];

    public function __construct(private array $config = []) {}

    public function limit(string $key, int $default, int $maximum): int
    {
        return max(1, min($maximum, (int) ($this->config[$key] ?? $default)));
    }

    public function mode(): string
    {
        $mode = $this->config['payload_level'] ?? 'metadata';

        return in_array($mode, ['none', 'metadata', 'safe'], true) ? $mode : 'none';
    }

    public function sensitive(string $key): bool
    {
        $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?? '');

        return (bool) preg_match('/password|passwd|pwd|token|secret|apikey|authorization|cookie|session|csrf|mfa|otp|privatekey|creditcard|cardnumber|cvv|iban|bank|email|phone|address|birth|ssn|binding|payload|body|query/', $key);
    }

    public function identifier(mixed $value, string $fallback = 'unknown'): string
    {
        return is_string($value) && preg_match('/\A[a-zA-Z][a-zA-Z0-9_.:\\\\-]{0,119}\z/D', $value)
            ? $value : $fallback;
    }

    public function text(string $text): string
    {
        $text = substr($text, 0, $this->limit('max_string_bytes', 1000, 8000));
        $text = preg_replace('/[\x00-\x1f\x7f]/', ' ', $text) ?? '[omitted]';
        $patterns = [
            '/-----BEGIN [^-]*PRIVATE KEY-----.*?(?:-----END [^-]*PRIVATE KEY-----|$)/s',
            '/\b(?:Bearer|Basic)\s+[A-Za-z0-9+\/_=.\-]+/i',
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',
            '/\b(?:password|passwd|access[_-]?token|refresh[_-]?token|token|secret|api[_-]?key|authorization|cookie|session|otp|iban|phone|address)\b["\s]*[:=]\s*(?:"[^"]*"|\x27[^\x27]*\x27|[^\s,;]+)/i',
            '/\b(?:\d[ -]?){13,19}\b/',
            '#https?://[^\s]+#i',
            '#(?:[A-Za-z]:[\\\\/]|/)(?:[^\s/]+/)+[^\s]*#',
        ];
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[redacted]', $text) ?? '[omitted]';
        }

        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    public function message(string $message): string
    {
        return ($this->config['message_mode'] ?? 'categorical') === 'redacted'
            ? $this->text($message) : 'Application log event';
    }

    public function context(array $context): array
    {
        if ($this->mode() === 'none') {
            return [];
        }
        $allowed = self::METADATA;
        if ($this->mode() === 'safe') {
            $allowed = array_merge($allowed, (array) ($this->config['safe_fields'] ?? []));
        }
        $budget = $this->limit('max_items', 30, 200);

        return $this->filter($context, $allowed, 0, $budget);
    }

    private function filter(array $values, array $allowed, int $depth, int &$budget): array
    {
        if ($depth >= $this->limit('max_depth', 5, 8)) {
            return [];
        }
        $result = [];
        $visited = 0;
        foreach ($values as $key => $value) {
            if (++$visited > $this->limit('max_items', 30, 200) || $budget-- <= 0) {
                break;
            }
            if (! is_string($key) || $this->sensitive($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            if (is_array($value) && $this->mode() === 'safe') {
                $result[$key] = $this->filter($value, $allowed, $depth + 1, $budget);
            } elseif (is_string($value)) {
                // Built-in metadata strings are identifiers, never arbitrary user input.
                if ($key === 'route') {
                    $result[$key] = preg_match('#\A/[a-zA-Z0-9_{}?./-]{0,240}\z#D', $value) && ! str_contains($value, '..') ? $value : '[omitted]';
                } else {
                    $result[$key] = in_array($key, ['operation', 'method', 'route_name', 'code', 'job_class'], true)
                        ? $this->identifier($value, '[omitted]') : $this->text($value);
                }
            } elseif (is_int($value) || is_bool($value) || $value === null || (is_float($value) && is_finite($value))) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function summary(mixed $value): array
    {
        $summary = ['type' => get_debug_type($value)];
        if (is_object($value)) {
            $summary['type'] = 'object';
            $summary['class'] = $this->identifier(get_class($value));
        } elseif (is_array($value)) {
            $summary['count'] = count($value);
            if ($this->mode() === 'safe') {
                $summary['fields'] = $this->context($value);
            }
        }

        return $summary;
    }
}
