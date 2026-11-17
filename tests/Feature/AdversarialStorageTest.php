<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Fiachehr\LaravelLogger\Storage\LogSource;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AdversarialStorageTest extends TestCase
{
    public function test_malicious_json_shapes_and_oversized_suffix_cannot_become_records(): void
    {
        Log::channel('observability')->info('valid');
        $path = glob($this->logRoot.'/*')[0];
        $valid = file_get_contents($path);
        $entry = json_decode($valid, true);
        foreach ([['context' => 'bad'], ['exception' => ['trace' => 'bad']], ['exception' => ['trace' => [null]]], ['event' => ['bad']]] as $extra) {
            file_put_contents($path, json_encode(array_replace($entry, $extra))."\n", FILE_APPEND);
        }
        // A valid JSON-looking suffix inside one giant malformed line must not be accepted.
        file_put_contents($path, str_repeat('x', 100000).$valid.$valid, FILE_APPEND);
        foreach (['newest', 'oldest'] as $order) {
            $page = $this->app->make(LogSource::class)->search(['order' => $order]);
            self::assertCount(2, $page['data']);
            self::assertGreaterThanOrEqual(4, $page['meta']['malformed_entries']);
        }
    }

    public function test_cursor_is_bound_to_filters_and_expires(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Log::channel('observability')->info('entry');
        }
        $source = $this->app->make(LogSource::class);
        $page = $source->search([], null, 1);
        $crypt = $this->app->make(Encrypter::class);
        $state = json_decode($crypt->decrypt($page['next_cursor'], false), true);
        $state['expires'] = time() - 1;
        foreach ([$crypt->encrypt(json_encode($state), false), $page['next_cursor']] as $index => $cursor) {
            try {
                $source->search($index === 0 ? [] : ['q' => 'changed'], $cursor);
                self::fail('Cursor must be rejected.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_hardlink_write_is_rejected_without_touching_target(): void
    {
        $files = $this->app->make(JsonlFiles::class);
        $files->root(true);
        $target = tempnam(sys_get_temp_dir(), 'logger-hardlink-');
        file_put_contents($target, 'SECRET_CANARY');
        link($target, $this->logRoot.'/events-'.gmdate('Y-m-d').'.jsonl');
        try {
            try {
                $files->append("{}\n");
                self::fail('Must reject hardlink.');
            } catch (\RuntimeException) {
                self::assertSame('SECRET_CANARY', file_get_contents($target));
            }
        } finally {
            unlink($target);
        }
    }

    public function test_concurrent_writes_are_complete(): void
    {
        $files = $this->app->make(JsonlFiles::class);
        $files->root(true);
        $code = <<<'PHP'
require $argv[1];
$files = new \Fiachehr\LaravelLogger\Storage\JsonlFiles(['root' => $argv[2]]);
for ($i = 0; $i < 100; $i++) {
    $files->append(json_encode(['worker' => $argv[3], 'index' => $i])."\n");
}
PHP;
        $processes = [];
        for ($worker = 0; $worker < 4; $worker++) {
            $processes[] = proc_open([PHP_BINARY, '-r', $code, __DIR__.'/../../vendor/autoload.php', $this->logRoot, (string) $worker], [], $pipes);
        }
        foreach ($processes as $process) {
            self::assertIsResource($process);
            self::assertSame(0, proc_close($process));
        }
        $lines = file(glob($this->logRoot.'/*')[0]);
        self::assertCount(400, $lines);
        $ids = [];
        foreach ($lines as $line) {
            $value = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
            $ids[] = $value['worker'].':'.$value['index'];
        }
        self::assertCount(400, array_unique($ids));
    }
}
