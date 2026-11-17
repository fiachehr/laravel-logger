<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Console;

use Illuminate\Console\Command;

final class ServeCommand extends Command
{
    protected $signature = 'log-explorer:serve {--port=8765 : Local development port}';

    protected $description = 'Explicitly launch the host Laravel development server on loopback; retain all middleware';

    public function handle(): int
    {
        if (! app()->environment(['local', 'development'])) {
            $this->error('Dedicated development serving is restricted to local/development.');

            return self::FAILURE;
        }
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
        if ($port === false) {
            $this->error('Port must be an integer from 1024 to 65535.');

            return self::FAILURE;
        }
        $this->line('Serving the entire host app on 127.0.0.1:'.$port.'; dashboard authentication still applies.');

        return $this->call('serve', ['--host' => '127.0.0.1', '--port' => $port, '--tries' => 1, '--no-reload' => true]);
    }
}
