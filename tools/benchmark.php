<?php

declare(strict_types=1);
use Fiachehr\LaravelLogger\Logging\JsonFormatter;
use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Fiachehr\LaravelLogger\Storage\JsonlReader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Encryption\Encrypter;
use Monolog\Level;
use Monolog\LogRecord;

// Run against an installed local smoke host. Dataset generation is outside timed reads.
$host = realpath($argv[1] ?? '');
if (! $host || ! is_file($host.'/artisan')) {
    throw new RuntimeException('Pass smoke host path.');
}
require $host.'/vendor/autoload.php';
$app = require $host.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$root = realpath(sys_get_temp_dir()).'/logger-benchmark-'.bin2hex(random_bytes(6));
$files = new JsonlFiles(['root' => $root]);
$files->root(true);
$path = $root.'/events-'.gmdate('Y-m-d').'.jsonl';
$reader = new JsonlReader($files, $app->make(Encrypter::class));
$record = ['schema_version' => '1.0', 'timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'), 'level' => 'INFO', 'environment' => 'local', 'application' => 'benchmark', 'service' => 'checkout', 'event' => 'order.completed', 'message' => 'Application log event', 'request_id' => str_repeat('a', 32), 'context' => ['count' => 1, 'status_code' => 200]];
$line = json_encode($record, JSON_UNESCAPED_SLASHES)."\n";
$results = ['php' => PHP_VERSION, 'laravel' => $app->version(), 'method' => 'Sequential generated JSONL; warm filesystem; median of 5 requests, no cache flush. No-match search is a bounded window, not full archive search.', 'datasets' => []];
try {
    foreach ([10000, 100000, 1000000] as $count) {
        $fp = fopen($path, 'wb');
        $chunk = str_repeat($line, 1000);
        for ($i = 0; $i < $count / 1000; $i++) {
            fwrite($fp, $chunk);
        }
        fclose($fp);
        clearstatcache(true, $path);
        $times = [];
        $searches = [];
        $bytes = 0;
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $page = $reader->search([], null, 50);
            $times[] = (hrtime(true) - $start) / 1e6;
            $start = hrtime(true);
            $filtered = $reader->search(['q' => 'no-match-canary'], null, 50);
            $searches[] = (hrtime(true) - $start) / 1e6;
            $bytes = $filtered['meta']['scanned_bytes'];
            if (count($page['data']) !== 50 || count($filtered['data']) !== 0) {
                throw new RuntimeException('Benchmark correctness failed.');
            }
        }
        sort($times);
        sort($searches);
        $results['datasets'][] = ['entries' => $count, 'file_bytes' => filesize($path), 'first_page_ms' => round($times[2], 3), 'no_match_window_ms' => round($searches[2], 3), 'window_bytes' => $bytes, 'peak_memory_bytes' => memory_get_peak_usage(true)];
    }
    $formatter = $app->make(JsonFormatter::class);
    $monologRecord = new LogRecord(new DateTimeImmutable, 'bench', Level::Info, 'message', ['count' => 1]);
    $start = hrtime(true);
    for ($i = 0; $i < 10000; $i++) {
        $formatter->format($monologRecord);
    }
    $results['format_mean_microseconds'] = round((hrtime(true) - $start) / 10000 / 1000, 3);
    unlink($path);
    $start = hrtime(true);
    for ($i = 0; $i < 10000; $i++) {
        $files->append($formatter->format($monologRecord));
    }
    $results['format_and_locked_append_mean_microseconds'] = round((hrtime(true) - $start) / 10000 / 1000, 3);
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} finally {
    if (is_file($path)) {
        unlink($path);
    }rmdir($root);
}
