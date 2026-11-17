<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Context;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

final class CurrentContext
{
    public function __construct(private Application $app) {}

    public function get(?\Throwable $exception = null): array
    {
        if ($exception !== null) {
            $remembered = $this->app->make(ExceptionContexts::class)->take($exception);
            if ($remembered !== null) {
                return $remembered;
            }
        }
        if ($this->app->bound('request')) {
            $request = $this->app->make('request');
            if ($request instanceof Request && $request->attributes->has('_log_explorer_context')) {
                return $request->attributes->get('_log_explorer_context');
            }
        }

        return $this->app->make(WorkerContext::class)->values;
    }
}
