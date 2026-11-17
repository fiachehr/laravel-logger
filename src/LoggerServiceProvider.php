<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger;

use Fiachehr\LaravelLogger\Console\DoctorCommand;
use Fiachehr\LaravelLogger\Console\InstallCommand;
use Fiachehr\LaravelLogger\Console\PasswordCommand;
use Fiachehr\LaravelLogger\Console\PruneCommand;
use Fiachehr\LaravelLogger\Console\ServeCommand;
use Fiachehr\LaravelLogger\Context\CurrentContext;
use Fiachehr\LaravelLogger\Context\ExceptionContexts;
use Fiachehr\LaravelLogger\Context\WorkerContext;
use Fiachehr\LaravelLogger\Logging\JsonFormatter;
use Fiachehr\LaravelLogger\Security\ExceptionNormalizer;
use Fiachehr\LaravelLogger\Security\PrivacyPolicy;
use Fiachehr\LaravelLogger\Storage\JsonlFiles;
use Fiachehr\LaravelLogger\Storage\JsonlReader;
use Fiachehr\LaravelLogger\Storage\LogSource;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\ServiceProvider;

final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/log-explorer.php', 'log-explorer');
        $this->app->scoped(WorkerContext::class);
        $this->app->scoped(ExceptionContexts::class);
        $this->app->bind(PrivacyPolicy::class, fn ($app) => new PrivacyPolicy($app['config']->get('log-explorer.privacy', [])));
        $this->app->bind(ExceptionNormalizer::class, fn ($app) => new ExceptionNormalizer($app->make(PrivacyPolicy::class), $app->basePath()));
        $this->app->singleton(JsonlFiles::class, fn ($app) => new JsonlFiles($app['config']->get('log-explorer.storage', [])));
        $this->app->bind(LogSource::class, JsonlReader::class);
        $this->app->bind(JsonFormatter::class, fn ($app) => new JsonFormatter(
            $app->make(PrivacyPolicy::class), $app->make(ExceptionNormalizer::class), $app->make(CurrentContext::class),
            $app->environment(), (string) $app['config']->get('app.name', 'application'),
            (string) $app['config']->get('log-explorer.service', 'application'),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/log-explorer.php' => config_path('log-explorer.php')], 'log-explorer-config');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'log-explorer');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $events = $this->app->make('events');
        $reset = function (): void {
            $this->app->make(WorkerContext::class)->clear();
            $this->app->make(ExceptionContexts::class)->clear();
        };
        foreach ([Looping::class, JobProcessed::class,
            JobExceptionOccurred::class, JobFailed::class,
            CommandStarting::class, CommandFinished::class,
            RequestHandled::class,
            'Laravel\\Octane\\Events\\RequestReceived', 'Laravel\\Octane\\Events\\RequestTerminated',
            'Laravel\\Octane\\Events\\TaskReceived', 'Laravel\\Octane\\Events\\TaskTerminated',
            'Laravel\\Octane\\Events\\TickReceived', 'Laravel\\Octane\\Events\\TickTerminated'] as $event) {
            $events->listen($event, $reset);
        }
        $events->listen(JobProcessing::class, function ($event): void {
            $context = $this->app->make(WorkerContext::class);
            $context->clear();
            if (! config('log-explorer.enabled', false)) {
                return;
            }
            // Resolve class-name metadata only; never unserialize or log the command payload.
            $class = $event->job instanceof Job
                ? $event->job->resolveQueuedJobClass() : $event->job->getName();
            $class = explode('@', $class, 2)[0];
            $context->values = ['request_id' => bin2hex(random_bytes(16)),
                'job_class' => $class, 'attempt' => $event->job->attempts()];
        });
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, DoctorCommand::class, ServeCommand::class, PasswordCommand::class, PruneCommand::class]);
        }
    }
}
