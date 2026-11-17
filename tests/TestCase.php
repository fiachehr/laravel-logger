<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests;

use Fiachehr\LaravelLogger\LoggerServiceProvider;
use Fiachehr\LaravelLogger\Logging\CreateLogger;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $logRoot;

    protected function getPackageProviders($app): array
    {
        return [LoggerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->logRoot = realpath(sys_get_temp_dir()).'/laravel-logger-test-'.bin2hex(random_bytes(8));
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('log-explorer.enabled', true);
        $app['config']->set('log-explorer.storage.root', $this->logRoot);
        $app['config']->set('log-explorer.dashboard.enabled', true);
        $app['config']->set('log-explorer.dashboard.middleware', []);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('logging.channels.observability', ['driver' => 'custom', 'via' => CreateLogger::class]);
    }

    protected function authorized(): void
    {
        Gate::define('viewLogExplorer', fn ($user) => $user->getAuthIdentifier() === 7);
        $this->actingAs(new GenericUser(['id' => 7]));
    }

    protected function entries(): array
    {
        $result = [];
        foreach (glob($this->logRoot.'/*.jsonl') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
                $result[] = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
            }
        }

        return $result;
    }

    protected function tearDown(): void
    {
        if (isset($this->logRoot) && is_dir($this->logRoot) && ! is_link($this->logRoot)) {
            foreach (glob($this->logRoot.'/*') ?: [] as $file) {
                if (is_file($file) || is_link($file)) {
                    unlink($file);
                }
            }
            rmdir($this->logRoot);
        }
        parent::tearDown();
    }
}
