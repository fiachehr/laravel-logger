<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Storage;

use RuntimeException;

final class JsonlFiles
{
    public function __construct(private array $config) {}

    public function limit(string $name, int $default, int $maximum): int
    {
        return max(1, min($maximum, (int) ($this->config[$name] ?? $default)));
    }

    public function root(bool $create = false): ?string
    {
        $root = rtrim((string) ($this->config['root'] ?? ''), DIRECTORY_SEPARATOR);
        if ($root === '' || ! str_starts_with($root, DIRECTORY_SEPARATOR) || str_contains($root, '://') || is_link($root)) {
            throw new RuntimeException('Invalid log root.');
        }
        if (! is_dir($root)) {
            if (! $create) {
                return null;
            }
            if (! @mkdir($root, 0700, true) && ! is_dir($root)) {
                throw new RuntimeException('Log directory unavailable.');
            }
        }
        $canonical = realpath($root);
        if ($canonical === false || $canonical !== $root) {
            throw new RuntimeException('Log root must be canonical and not symlinked.');
        }

        return $canonical;
    }

    public function append(string $line): void
    {
        if (strlen($line) > 65537 || ! str_ends_with($line, "\n") || substr_count($line, "\n") !== 1) {
            throw new RuntimeException('Invalid JSONL record.');
        }
        $root = $this->root(true);
        $name = 'events-'.gmdate('Y-m-d').'.jsonl';
        $path = $root.'/'.$name;
        clearstatcache(true, $path);
        if (is_link($path) || (file_exists($path) && ! is_file($path))) {
            throw new RuntimeException('Non-regular log target rejected.');
        }
        $handle = @fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException('Log file unavailable.');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Log lock unavailable.');
            }
            clearstatcache(true, $path);
            $stat = fstat($handle);
            $actual = @lstat($path);
            if ($stat === false || $actual === false || $stat['nlink'] !== 1 || ($stat['mode'] & 0170000) !== 0100000
                || $actual['ino'] !== $stat['ino'] || $actual['dev'] !== $stat['dev'] || is_link($path)) {
                throw new RuntimeException('Unsafe log file.');
            }
            @chmod($path, 0600);
            if ($this->limit('max_file_bytes', 64 * 1024 * 1024, 1024 * 1024 * 1024) < $stat['size'] + strlen($line)) {
                throw new RuntimeException('Daily log size quota exceeded.');
            }
            if (fwrite($handle, $line) !== strlen($line)) {
                throw new RuntimeException('Incomplete log write.');
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        // Cheap per-process daily retention; readers remain safe when another worker rotates.
        $this->prune($root);
    }

    private ?string $lastPruneDate = null;

    private function prune(string $root): void
    {
        $today = gmdate('Y-m-d');
        if ($this->lastPruneDate === $today) {
            return;
        }
        $this->lastPruneDate = $today;
        $cutoff = gmdate('Y-m-d', time() - $this->limit('retention_days', 14, 365) * 86400);
        $visited = 0;
        foreach (new \DirectoryIterator($root) as $file) {
            if (++$visited > $this->limit('max_directory_entries', 256, 2048)) {
                break;
            }
            if (! $file->isLink() && $file->isFile() && preg_match('/\Aevents-(\d{4}-\d{2}-\d{2})\.jsonl\z/D', $file->getFilename(), $m) && $m[1] <= $cutoff) {
                @unlink($file->getPathname());
            }
        }
    }

    public function snapshots(string $order): array
    {
        $root = $this->root();
        if ($root === null) {
            return [];
        }
        $files = [];
        $visited = 0;
        foreach (new \DirectoryIterator($root) as $file) {
            if (++$visited > $this->limit('max_directory_entries', 256, 2048)) {
                throw new RuntimeException('Log directory entry quota exceeded.');
            }
            if (! preg_match('/\Aevents-\d{4}-\d{2}-\d{2}\.jsonl\z/D', $file->getFilename()) || $file->isLink() || ! $file->isFile()) {
                continue;
            }
            $files[] = ['name' => $file->getFilename(), 'size' => $file->getSize(), 'inode' => $file->getInode()];
        }
        usort($files, static fn ($a, $b) => strcmp($b['name'], $a['name']));
        $files = array_slice($files, 0, $this->limit('max_files', 14, 31));
        if ($order === 'oldest') {
            $files = array_reverse($files);
        }
        foreach ($files as &$file) {
            $file['offset'] = $order === 'oldest' ? 0 : $file['size'];
        }

        return $files;
    }

    /** @return resource|null */
    public function open(array $snapshot)
    {
        $root = $this->root();
        if ($root === null || ! preg_match('/\Aevents-\d{4}-\d{2}-\d{2}\.jsonl\z/D', (string) ($snapshot['name'] ?? ''))) {
            return null;
        }
        $path = $root.'/'.$snapshot['name'];
        clearstatcache(true, $path);
        if (is_link($path) || realpath($path) !== $path || ! is_file($path)) {
            return null;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $stat = fstat($handle);
        if ($stat === false || $stat['nlink'] !== 1 || $stat['ino'] !== $snapshot['inode'] || $stat['size'] < $snapshot['size'] || ($stat['mode'] & 0170000) !== 0100000) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
