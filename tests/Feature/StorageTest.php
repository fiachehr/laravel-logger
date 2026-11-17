<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Fiachehr\LaravelLogger\Storage\LogSource;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class StorageTest extends TestCase
{
    private function seedLogs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Log::channel('observability')->info('message', ['event' => 'event.'.$i, 'count' => $i]);
        }
    }

    public function test_pagination_is_stable_across_append_and_in_both_orders(): void
    {
        $this->seedLogs(201);
        foreach (['newest', 'oldest'] as $order) {
            $cursor = null;
            $found = [];
            do {
                $result = $this->app->make(LogSource::class)->search(['order' => $order], $cursor, 17);
                foreach ($result['data'] as $item) {
                    $found[] = $item['entry']['context']['count'];
                }
                $cursor = $result['next_cursor'];
                self::assertLessThan(20, count($found) / 17);
            } while ($cursor);
            $expected = range(0, 200);
            if ($order === 'newest') {
                $expected = array_reverse($expected);
            }
            self::assertSame($expected, $found);
        }
        $source = $this->app->make(LogSource::class);
        $page = $source->search([], null, 50);
        $this->seedLogs(1);
        $next = $source->search([], $page['next_cursor'], 50);
        self::assertSame('event.150', $next['data'][0]['entry']['event']);
    }

    public function test_filters_are_literal_bounded_and_cursor_tamper_fails(): void
    {
        config(['log-explorer.storage.max_scan_bytes' => 4096]);
        $this->seedLogs(100);
        $source = $this->app->make(LogSource::class);
        $page = $source->search(['q' => '[.*]']);
        self::assertSame([], $page['data']);
        self::assertLessThanOrEqual(4096, $page['meta']['scanned_bytes']);
        self::assertTrue($page['meta']['bounded']);
        self::assertNotNull($page['next_cursor']);
        $this->expectException(ValidationException::class);
        $source->search([], '../../.env');
    }

    public function test_partial_malformed_and_oversized_records_are_skipped(): void
    {
        $this->seedLogs(2);
        $path = glob($this->logRoot.'/*')[0];
        file_put_contents($path, "invalid\n".str_repeat('x', 100000)."\n{\"partial\":", FILE_APPEND);
        $source = $this->app->make(LogSource::class);
        $result = $source->search([]);
        self::assertCount(2, $result['data']);
        self::assertGreaterThan(0, $result['meta']['malformed_entries']);
        $result = $source->search(['order' => 'oldest']);
        self::assertCount(2, $result['data']);
    }

    public function test_symlinks_and_arbitrary_files_are_not_read(): void
    {
        $files = $this->app->make(JsonlFiles::class);
        $files->root(true);
        $secret = tempnam(sys_get_temp_dir(), 'logger-secret-');
        file_put_contents($secret, 'SECRET_CANARY');
        try {
            symlink($secret, $this->logRoot.'/events-2020-01-01.jsonl');
            file_put_contents($this->logRoot.'/.env', 'SECRET_CANARY');
            self::assertSame([], $files->snapshots('newest'));
            self::assertNull($files->open(['name' => '../../.env', 'inode' => 0, 'size' => 1]));
            self::assertNull($files->open(['name' => 'events-2020-01-01.jsonl', 'inode' => fileinode($secret), 'size' => 1]));
        } finally {
            unlink($secret);
            unlink($this->logRoot.'/.env');
        }
    }

    public function test_writer_rejects_symlink_and_size_quota_and_rotation_prunes_only_logs(): void
    {
        config(['log-explorer.storage.max_file_bytes' => 100]);
        $files = $this->app->make(JsonlFiles::class);
        $files->root(true);
        $this->expectException(\RuntimeException::class);
        $files->append(str_repeat('x', 100)."\n");
    }

    public function test_retention_permissions_and_rotated_snapshot(): void
    {
        $files = $this->app->make(JsonlFiles::class);
        $files->root(true);
        file_put_contents($this->logRoot.'/events-2000-01-01.jsonl', "{}\n");
        file_put_contents($this->logRoot.'/keep.txt', 'keep');
        $this->seedLogs(20);
        self::assertFileDoesNotExist($this->logRoot.'/events-2000-01-01.jsonl');
        self::assertFileExists($this->logRoot.'/keep.txt');
        self::assertSame(0, fileperms($this->logRoot) & 0077);
        $path = glob($this->logRoot.'/*.jsonl')[0];
        self::assertSame(0, fileperms($path) & 0077);
        $source = $this->app->make(LogSource::class);
        $page = $source->search([], null, 1);
        unlink($path);
        $next = $source->search([], $page['next_cursor'], 1);
        self::assertSame([], $next['data']);
        self::assertSame(1, $next['meta']['rotated_files']);
    }
}
