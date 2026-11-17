<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Context\WorkerContext;
use Fiachehr\LaravelLogger\Http\CorrelateRequest;
use Fiachehr\LaravelLogger\Logging\JsonFormatter;
use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Monolog\LogRecord;

final class LoggingTest extends TestCase
{
    public function test_all_psr_levels_schema_secrets_and_exception_origin(): void
    {
        $line = __LINE__ + 1;
        $exception = new \RuntimeException('password=SECRET_CANARY', 0, new \LogicException('SECRET_CANARY'));
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            Log::channel('observability')->$level('SECRET_CANARY', ['event' => 'test.failure', 'count' => 3, 'password' => 'SECRET_CANARY', 'bindings' => ['SECRET_CANARY'], 'exception' => $exception]);
        }
        $entries = $this->entries();
        self::assertCount(8, $entries);
        self::assertSame('DEBUG', $entries[0]['level']);
        self::assertSame('EMERGENCY', $entries[7]['level']);
        self::assertSame('1.0', $entries[0]['schema_version']);
        self::assertMatchesRegularExpression('/T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $entries[0]['timestamp']);
        self::assertSame($line, $entries[0]['exception']['line']);
        self::assertSame('LoggingTest.php', basename($entries[0]['exception']['file']));
        self::assertSame(3, $entries[0]['context']['count']);
        $json = json_encode($entries);
        self::assertStringNotContainsString('SECRET_CANARY', $json);
        self::assertStringNotContainsString('"args"', $json);
        self::assertStringNotContainsString($this->app->basePath(), $json);
        self::assertArrayNotHasKey('class', $entries[0]['exception']);
    }

    public function test_unknown_object_hooks_are_not_invoked_and_utf8_is_valid(): void
    {
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                throw new \LogicException;
            }

            public function __toString(): string
            {
                throw new \LogicException;
            }
        };
        config(['log-explorer.privacy.message_mode' => 'redacted']);
        Log::channel('observability')->info("\xC3\x28", ['operation' => $object]);
        self::assertSame([], $this->entries()[0]['context']);
        self::assertCount(1, $this->entries());
    }

    public function test_event_size_and_formatter_failure_have_safe_fallback(): void
    {
        config(['log-explorer.privacy.max_event_bytes' => 512, 'log-explorer.privacy.message_mode' => 'redacted']);
        Log::channel('observability')->info(str_repeat('A', 2000));
        self::assertSame('logging.event_truncated', $this->entries()[0]['event']);
        $formatter = $this->app->make(JsonFormatter::class);
        $this->app->bind(WorkerContext::class, fn () => throw new \RuntimeException('SECRET_CANARY'));
        $raw = $formatter->format(new LogRecord(new \DateTimeImmutable, 'test', Level::Error, 'SECRET_CANARY'));
        self::assertStringNotContainsString('SECRET_CANARY', $raw);
        self::assertSame('logging.encoding_failed', json_decode($raw, true)['event']);
    }

    public function test_request_ids_are_generated_routes_are_templates_and_context_clears(): void
    {
        Route::middleware(CorrelateRequest::class)->get('/users/{id}', function () {
            Log::channel('observability')->info('User request');

            return response('ok');
        })->name('users.show');
        $first = $this->get('/users/secret-person?token=SECRET_CANARY', ['X-Request-ID' => 'untrusted-secret'])->assertOk();
        $id = $first->headers->get('X-Request-ID');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $second = $this->get('/users/another-person')->assertOk();
        self::assertNotSame($id, $second->headers->get('X-Request-ID'));
        Log::channel('observability')->info('outside');
        $entries = $this->entries();
        self::assertSame('/users/{id}', $entries[0]['context']['route']);
        self::assertArrayNotHasKey('request_id', $entries[2]);
        self::assertStringNotContainsString('secret-person', json_encode($entries));
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($entries));
    }

    public function test_request_exception_unwinds_context_and_trusted_ids_are_validated(): void
    {
        config(['log-explorer.http.trust_request_id' => true, 'logging.default' => 'observability']);
        Route::middleware(CorrelateRequest::class)->get('/fail', fn () => throw new \RuntimeException('Handled by framework'));
        $this->get('/fail', ['X-Request-ID' => str_repeat('a', 32)])->assertStatus(500);
        self::assertFalse($this->app['request']->attributes->has('_log_explorer_context'));
        self::assertSame(str_repeat('a', 32), $this->entries()[0]['request_id']);
        Route::middleware(CorrelateRequest::class)->get('/ok', fn () => response('ok'));
        $this->get('/ok', ['X-Request-ID' => str_repeat('a', 32)])->assertHeader('X-Request-ID', str_repeat('a', 32));
        $response = $this->get('/ok', ['X-Request-ID' => 'bad id']);
        self::assertNotSame('bad id', $response->headers->get('X-Request-ID'));
    }

    public function test_workers_clear_success_exception_and_octane_boundaries(): void
    {
        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getName')->andReturn('App\\Jobs\\Example');
        $job->shouldReceive('attempts')->andReturn(2);
        $job->shouldReceive('payload')->andReturn([]);
        foreach ([JobProcessed::class, JobExceptionOccurred::class, JobFailed::class] as $event) {
            event(new JobProcessing('sync', $job));
            Log::channel('observability')->info('inside');
            $instance = $event === JobProcessed::class ? new $event('sync', $job) : new $event('sync', $job, new \RuntimeException);
            event($instance);
            Log::channel('observability')->info('outside');
        }
        $entries = $this->entries();
        self::assertSame(2, $entries[0]['context']['attempt']);
        self::assertArrayNotHasKey('request_id', $entries[1]);
        self::assertNotSame($entries[0]['request_id'], $entries[2]['request_id']);
        self::assertArrayNotHasKey('request_id', $entries[5]);
        $this->app->make(WorkerContext::class)->values = ['job_class' => 'stale'];
        event('Laravel\\Octane\\Events\\RequestReceived');
        self::assertSame([], $this->app->make(WorkerContext::class)->values);
    }

    public function test_package_does_not_change_default_channel_or_register_duplicate_reporters(): void
    {
        self::assertNotSame('observability', config('logging.default'));
        Log::channel('observability')->error('one', ['exception' => new \RuntimeException]);
        self::assertCount(1, $this->entries());
        config(['log-explorer.enabled' => false]);
        Log::forgetChannel('observability');
        Log::channel('observability')->info('disabled');
        self::assertCount(1, $this->entries());
    }

    public function test_storage_failures_warn_at_most_once_per_minute_and_omit_record_data(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'logger-warn-');
        $previous = ini_set('error_log', $log);
        try {
            config(['log-explorer.storage.root' => 'relative-root']);
            $this->app->forgetInstance(JsonlFiles::class);
            Log::forgetChannel('observability');
            for ($i = 0; $i < 5; $i++) {
                Log::channel('observability')->info('SECRET_CANARY');
            }
            $contents = (string) file_get_contents($log);
            self::assertSame(1, substr_count($contents, 'structured log write rejected'));
            self::assertStringNotContainsString('SECRET_CANARY', $contents);
        } finally {
            if (is_string($previous)) {
                ini_set('error_log', $previous);
            }
            unlink($log);
        }
    }
}
