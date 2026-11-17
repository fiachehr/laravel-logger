<?php

declare(strict_types=1);
use Fiachehr\LaravelLogger\Context\WorkerContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;

$host = realpath($argv[1] ?? '');
if (! $host || ! is_file($host.'/artisan')) {
    throw new RuntimeException('Expected smoke host path.');
}
require $host.'/vendor/autoload.php';
$app = require $host.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Local smoke fixture only.');
}
$names = ['checkout.completed', 'notification.queued', 'catalog.refreshed', 'payment.retry', 'authentication.failed'];
$levels = ['info', 'info', 'notice', 'warning', 'error'];
for ($i = 0; $i < 113; $i++) {
    $app->make(WorkerContext::class)->values = ['request_id' => str_pad(dechex(intdiv($i, 3) + 1), 32, '0', STR_PAD_LEFT)];
    $exception = $i % 5 === 4 ? new RuntimeException('password=SECRET_CANARY') : null;
    Log::channel('observability')->log($levels[$i % 5], 'SECRET_CANARY', array_filter(['event' => $names[$i % 5], 'operation' => 'checkout', 'status_code' => $i % 5 === 4 ? 500 : 200, 'count' => $i, 'exception' => $exception], fn ($v) => $v !== null));
}
$app->make(WorkerContext::class)->clear();
echo "Seeded 113 sanitized events.\n";
