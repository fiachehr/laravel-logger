<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DashboardTest extends TestCase
{
    public function test_published_01_dashboard_configurations_keep_host_authentication_without_the_new_auth_block(): void
    {
        $dashboard = config('log-explorer.dashboard');
        unset($dashboard['auth']);
        config(['log-explorer.dashboard' => $dashboard]);
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->authorized();
        $this->getJson('/log-explorer/api/entries')->assertOk();
        $this->get('/log-explorer/login')->assertNotFound();
    }

    public function test_pages_api_and_assets_require_enabled_authenticated_explicit_authorization(): void
    {
        foreach (['/log-explorer', '/log-explorer/api/entries', '/log-explorer/assets/explorer-v1.js', '/log-explorer/assets/explorer-v1.css'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->postJson('/log-explorer/api/entries', ['q' => 'SECRET_CANARY'])->assertUnauthorized();
        $this->actingAs(new GenericUser(['id' => 7]));
        $this->getJson('/log-explorer/api/entries')->assertForbidden();
        Gate::define('viewLogExplorer', fn () => false);
        $this->get('/log-explorer')->assertForbidden();
        $this->authorized();
        $page = $this->get('/log-explorer')->assertOk()->assertSee('Event stream');
        self::assertStringContainsString('<option>DEBUG</option>', $page->getContent());
        self::assertStringNotContainsString('</option>DEBUG', $page->getContent());
        self::assertStringContainsString('name="csrf-token"', $page->getContent());
        $this->getJson('/log-explorer/api/entries')->assertOk();
        config(['log-explorer.dashboard.enabled' => false]);
        $this->getJson('/log-explorer/api/entries')->assertNotFound();
        config(['log-explorer.dashboard.enabled' => true]);
        $this->app->instance('env', 'production');
        Gate::define('viewLogExplorer', fn () => false);
        $this->getJson('/log-explorer/api/entries')->assertForbidden();
    }

    public function test_validation_rate_limit_headers_no_downloads_and_no_secret_canaries(): void
    {
        $this->authorized();
        Log::channel('observability')->error('SECRET_CANARY', ['password' => 'SECRET_CANARY']);
        $response = $this->getJson('/log-explorer/api/entries')->assertOk();
        self::assertStringNotContainsString('SECRET_CANARY', $response->getContent());
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        foreach (['q='.str_repeat('x', 121), 'level=EVIL', 'status=999', 'request_id=bad', 'cursor=../../.env', 'q[]=object', 'from=yesterday'] as $query) {
            $this->getJson('/log-explorer/api/entries?'.$query)->assertUnprocessable();
        }
        $this->get('/log-explorer/assets/.env')->assertNotFound();
        $this->get('/log-explorer/download')->assertNotFound();
        $this->post('/log-explorer/api/entries', ['q' => 'SECRET_CANARY'])->assertStatus(419);
        $js = file_get_contents(__DIR__.'/../../resources/assets/explorer-v1.js');
        self::assertStringNotContainsString('${location.pathname}?${params}', $js);
        self::assertStringContainsString('method: "POST"', $js);
        config(['log-explorer.dashboard.rate_per_minute' => 1]);
        $this->getJson('/log-explorer/api/entries')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_sensitive_logs_are_never_embedded_in_initial_html(): void
    {
        $this->authorized();
        Log::channel('observability')->info('SECRET_CANARY');
        $response = $this->get('/log-explorer')->assertOk();
        self::assertStringNotContainsString('SECRET_CANARY', $response->getContent());
        self::assertStringNotContainsString('Application log event', $response->getContent());
        self::assertStringNotContainsString('innerHTML', file_get_contents(__DIR__.'/../../resources/assets/explorer-v1.js'));
        $this->get('/log-explorer/assets/explorer-v1.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
    }
}
