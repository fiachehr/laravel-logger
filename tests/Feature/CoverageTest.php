<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Http\CorrelateRequest;
use Fiachehr\LaravelLogger\Security\ExceptionNormalizer;
use Fiachehr\LaravelLogger\Security\PrivacyPolicy;
use Fiachehr\LaravelLogger\Storage\LogSource;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CoverageTest extends TestCase
{
    public function test_install_defaults_are_disabled_and_do_not_mutate_host_channels(): void
    {
        $defaults = require __DIR__.'/../../config/log-explorer.php';
        self::assertFalse($defaults['enabled']);
        self::assertFalse($defaults['dashboard']['enabled']);
        self::assertFalse($defaults['diagnostics']['enabled']);
        self::assertSame('metadata', $defaults['privacy']['payload_level']);
        self::assertSame('categorical', $defaults['privacy']['message_mode']);
        self::assertSame(['web'], $defaults['dashboard']['middleware']);
        config(['log-explorer.enabled' => false]);
        Route::middleware(CorrelateRequest::class)->get('/disabled-capture', fn () => response('ok'));
        $this->get('/disabled-capture')->assertOk()->assertHeaderMissing('X-Request-ID');
    }

    public function test_http_enrichment_does_not_resolve_auth_user_or_execute_sql(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        config(['log-explorer.http.log_completion' => true]);
        Route::middleware(CorrelateRequest::class)->get('/metadata/{id}', function (Request $request) {
            $request->setUserResolver(fn () => throw new \LogicException('Auth must not be resolved by logging'));
            Log::channel('observability')->info('inside', ['event' => 'metadata.test']);

            return response('ok');
        })->name('metadata.show');
        $this->get('/metadata/PRIVATE_CANARY')->assertOk();
        self::assertSame(0, $queries);
        $entries = $this->entries();
        self::assertCount(2, $entries);
        self::assertSame($entries[0]['request_id'], $entries[1]['request_id']);
        self::assertSame(200, $entries[1]['context']['status_code']);
        self::assertArrayHasKey('duration_ms', $entries[1]['context']);
        self::assertStringNotContainsString('PRIVATE_CANARY', json_encode($entries));
    }

    public function test_laravel_reporting_policy_keeps_expected_404_out_of_error_logs(): void
    {
        config(['logging.default' => 'observability']);
        report(new NotFoundHttpException('SECRET_CANARY'));
        self::assertSame([], $this->entries());
        report(new \RuntimeException('SECRET_CANARY'));
        self::assertCount(1, $this->entries());
        self::assertSame('RuntimeException', $this->entries()[0]['exception']['type']);
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($this->entries()));
    }

    public function test_all_exact_filters_and_time_windows_on_actual_schema(): void
    {
        config(['log-explorer.http.trust_request_id' => true]);
        Route::middleware(CorrelateRequest::class)->get('/filter', function () {
            Log::channel('observability')->error('filtered', ['event' => 'filter.test', 'status_code' => 503, 'exception' => new \RuntimeException]);

            return response('ok');
        })->name('filter.route');
        $id = str_repeat('a', 32);
        $this->get('/filter', ['X-Request-ID' => $id])->assertOk();
        $source = $this->app->make(LogSource::class);
        $filters = ['level' => 'ERROR', 'service' => 'application', 'event' => 'filter.test', 'request_id' => $id, 'route' => 'filter.route', 'status' => 503, 'exception' => 'RuntimeException', 'from' => '2000-01-01T00:00:00.000Z', 'until' => '2099-01-01T00:00:00.000Z'];
        self::assertCount(1, $source->search($filters)['data']);
        foreach (['level', 'service', 'event', 'request_id', 'route', 'exception'] as $key) {
            self::assertCount(0, $source->search(array_replace($filters, [$key => 'different']))['data']);
        }
        self::assertCount(0, $source->search(['until' => '2000-01-01T00:00:00.000Z'])['data']);
    }

    public function test_exception_chain_frames_and_paths_are_bounded(): void
    {
        $exception = new \RuntimeException('SECRET_CANARY');
        for ($i = 0; $i < 20; $i++) {
            $exception = new \RuntimeException('SECRET_CANARY', 0, $exception);
        }
        $normalizer = new ExceptionNormalizer(new PrivacyPolicy(['max_trace_frames' => 1, 'max_previous_exceptions' => 2]), $this->app->basePath());
        $entry = $normalizer->normalize($exception);
        self::assertLessThanOrEqual(1, count($entry['trace']));
        self::assertArrayNotHasKey('previous', $entry['previous']['previous']);
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($entry));
        self::assertSame('file.php', $normalizer->path('/a/private/root/file.php'));
        self::assertSame('[external]', $normalizer->path('/a/../file?.php'));
    }
}
