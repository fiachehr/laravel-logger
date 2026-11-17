<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Diagnostics\Diagnostics;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Fiachehr\LaravelLogger\log_dd;
use function Fiachehr\LaravelLogger\log_dump;

final class DiagnosticsAndConsoleTest extends TestCase
{
    public function test_dump_is_opt_in_and_only_summarizes_values(): void
    {
        log_dump(['password' => 'SECRET_CANARY']);
        self::assertSame([], $this->entries());
        config(['log-explorer.diagnostics.enabled' => true]);
        $line = __LINE__ + 1;
        log_dump(['password' => 'SECRET_CANARY', 'count' => 3], 'checkout.state');
        self::assertSame('array', $this->entries()[0]['diagnostic']['summary']['type']);
        self::assertSame($line, $this->entries()[0]['diagnostic']['line']);
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($this->entries()));
        self::assertArrayNotHasKey('fields', $this->entries()[0]['diagnostic']['summary']);
        config(['log-explorer.privacy.payload_level' => 'safe']);
        // Recreate the channel to adopt changed formatter policy, as a host config reload would.
        Log::forgetChannel('observability');
        $cycle = ['count' => 1, 'password' => 'SECRET_CANARY'];
        $cycle['operation'] = &$cycle;
        log_dump($cycle, 'cycle');
        self::assertLessThan(16384, strlen(json_encode($this->entries()[1])));
    }

    public function test_production_stop_is_rejected_even_with_all_toggles_enabled(): void
    {
        config(['log-explorer.diagnostics.enabled' => true, 'log-explorer.diagnostics.allow_stop' => true]);
        $this->app->instance('env', 'production');
        $this->expectException(\LogicException::class);
        log_dd('SECRET_CANARY');
    }

    public function test_local_stop_throws_after_safe_logging_without_exiting_worker(): void
    {
        config(['log-explorer.diagnostics.enabled' => true, 'log-explorer.diagnostics.allow_stop' => true]);
        $this->app->instance('env', 'local');
        try {
            $this->app->make(Diagnostics::class)->dumpAndStop('SECRET_CANARY');
            self::fail('Must interrupt execution');
        } catch (HttpException $e) {
            self::assertSame(500, $e->getStatusCode());
        }
        self::assertCount(1, $this->entries());
        self::assertStringNotContainsString('SECRET_CANARY', json_encode($this->entries()));
    }

    public function test_port_is_local_only_and_validated(): void
    {
        $this->artisan('log-explorer:serve')->assertFailed();
        $this->app->instance('env', 'local');
        $this->artisan('log-explorer:serve', ['--port' => '0'])->assertFailed();
        $this->artisan('log-explorer:serve', ['--port' => '8765;bad'])->assertFailed();
        $this->app->make(Kernel::class)->registerCommand(new class extends Command
        {
            protected $signature = 'serve {--host=} {--port=} {--tries=} {--no-reload}';

            public function handle(): int
            {
                Assert::assertSame('127.0.0.1', $this->option('host'));
                Assert::assertSame(8765, $this->option('port'));
                Assert::assertTrue($this->option('no-reload'));

                return 0;
            }
        });
        $this->artisan('log-explorer:serve', ['--port' => '8765'])->assertSuccessful();
    }

    public function test_installer_does_not_overwrite_and_doctor_does_not_expose_configuration(): void
    {
        $path = config_path('log-explorer.php');
        $existing = is_file($path) ? file_get_contents($path) : null;
        file_put_contents($path, "<?php return ['marker'=>'PRIVATE_CANARY'];");
        try {
            $this->artisan('log-explorer:install')->expectsOutput('Existing configuration preserved.')->assertSuccessful();
            self::assertStringContainsString('PRIVATE_CANARY', file_get_contents($path));
        } finally {
            if ($existing === null) {
                unlink($path);
            } else {
                file_put_contents($path, $existing);
            }
        }
        $this->authorized();
        Log::channel('observability')->info('initialize storage');
        $this->artisan('log-explorer:doctor')->assertSuccessful();
    }
}
