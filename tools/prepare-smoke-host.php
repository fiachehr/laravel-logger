<?php

declare(strict_types=1);

// Test fixture only: never install the demonstration login or Gate in a real app.
$host = realpath($argv[1] ?? '');
if (! $host || ! is_file($host.'/artisan') || ! is_file($host.'/composer.json')) {
    throw new RuntimeException('Expected a newly created Laravel smoke host.');
}
$package = dirname(__DIR__);
$json = json_decode(file_get_contents($host.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$json['repositories']['log-explorer'] = ['type' => 'path', 'url' => $package, 'options' => ['symlink' => true, 'versions' => ['fiachehr/laravel-logger' => '0.2.x-dev']]];
$json['require']['fiachehr/laravel-logger'] = '0.2.x-dev';
file_put_contents($host.'/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
file_put_contents($host.'/config/log-explorer.php', <<<'PHP'
<?php
$config = require base_path('vendor/fiachehr/laravel-logger/config/log-explorer.php');
$config['enabled'] = true;
$config['dashboard']['enabled'] = true;
$config['dashboard']['rate_per_minute'] = 600;
$config['dashboard']['max_results'] = 25;
$config['dashboard']['auth']['mode'] = 'host';
return $config;
PHP);
file_put_contents($host.'/app/Providers/AppServiceProvider.php', <<<'PHP'
<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->configurationIsCached()) {
            config(['logging.channels.observability'=>['driver'=>'custom','via'=>\Fiachehr\LaravelLogger\Logging\CreateLogger::class],
                'auth.defaults.guard'=>'smoke', 'auth.guards.smoke'=>['driver'=>'smoke'],
                'session.driver'=>'file', 'cache.default'=>'file']);
        }
    }
    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::viaRequest('smoke', function ($request) {
            return app()->environment('local') && $request->hasSession() && $request->session()->get('smoke_authorized')
                ? new \Illuminate\Auth\GenericUser(['id'=>1]) : null;
        });
        \Illuminate\Support\Facades\Gate::define('viewLogExplorer', fn($user)=>app()->environment('local') && $user->getAuthIdentifier()===1);
    }
}
PHP);
file_put_contents($host.'/routes/web.php', <<<'PHP'
<?php
use Illuminate\Support\Facades\Route;
// Explicit local-only test login; session authentication is still exercised by the dashboard.
Route::get('/smoke/login', function () { abort_unless(app()->environment('local'),404); return response('<form method="POST">'.csrf_field().'<button>Sign in to smoke fixture</button></form>'); });
Route::post('/smoke/login', function (\Illuminate\Http\Request $request) {
    abort_unless(app()->environment('local'),404);
    $request->session()->regenerate(); $request->session()->put('smoke_authorized',true);
    return redirect('/log-explorer');
});
PHP);
if (getenv('LOG_EXPLORER_SMOKE_AUTH') === 'credentials') {
    // Fixed synthetic credentials only for this disposable test application.
    $configPath = $host.'/config/log-explorer.php';
    $source = file_get_contents($configPath);
    $source = str_replace('return $config;', "\$config['dashboard']['auth']['mode'] = 'credentials';\n".
        "\$config['dashboard']['auth']['username'] = 'smoke-operator';\n".
        "\$config['dashboard']['auth']['password_hash'] = ".var_export(password_hash('Synthetic-browser-password-ONLY', PASSWORD_DEFAULT), true).";\nreturn \$config;", $source);
    file_put_contents($configPath, $source);
}
// Seed using the real package formatter + handler, no raw credential-bearing fixtures.
require $host.'/vendor/autoload.php';
// Package is not installed until the caller runs composer; seed separately with tools/seed-smoke-host.php.
