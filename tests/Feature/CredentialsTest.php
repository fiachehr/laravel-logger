<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Tests\Feature;

use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Fiachehr\LaravelLogger\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CredentialsTest extends TestCase
{
    private const PASSWORD = 'Synthetic-test-password-ONLY';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('log-explorer.dashboard.middleware', ['web']);
        $app['config']->set('log-explorer.dashboard.auth.mode', 'credentials');
        $app['config']->set('log-explorer.dashboard.auth.username', 'log-operator');
        $app['config']->set('log-explorer.dashboard.auth.password_hash', password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]));
        mkdir($this->logRoot, 0700);
        mkdir($this->logRoot.'/sessions', 0700);
        $app['config']->set('session.driver', 'file');
        $app['config']->set('session.files', $this->logRoot.'/sessions');
        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', $this->logRoot.'/cache');
    }

    private function csrf(): string
    {
        $this->get('/log-explorer/login')->assertOk();
        $this->rememberCookie();

        return session()->token();
    }

    private function rememberCookie(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->withCredentials();
    }

    private function signIn(): void
    {
        $token = $this->csrf();
        $this->post('/log-explorer/login', ['username' => 'log-operator', 'password' => self::PASSWORD, '_token' => $token])
            ->assertRedirect('/log-explorer');
        $this->rememberCookie();
    }

    public function test_blade_login_is_public_but_every_log_endpoint_requires_its_own_session(): void
    {
        $this->get('/log-explorer')->assertRedirect('/log-explorer/login');
        $this->get('/log-explorer/login')->assertOk()->assertSee('Sign in to your logs')->assertSee('name="password"', false);
        $this->get('/log-explorer/login/assets/login-v1.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->authorized(); // Host login and even its allowed Gate do not grant credential-mode access.
        foreach (['/log-explorer/api/entries', '/log-explorer/assets/explorer-v1.js', '/log-explorer/assets/explorer-v1.css'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->signIn();
        Log::channel('observability')->info('ready', ['event' => 'test.ready']);
        $this->get('/log-explorer')->assertOk()->assertSee('Sign out');
        $this->getJson('/log-explorer/api/entries')->assertOk()->assertJsonPath('data.0.entry.event', 'test.ready');
        $this->postJson('/log-explorer/api/entries', ['q' => 'test.ready'])->assertStatus(419);
        $this->postJson('/log-explorer/api/entries', ['q' => 'test.ready'], ['X-CSRF-TOKEN' => session()->token()])
            ->assertOk()->assertJsonPath('data.0.entry.event', 'test.ready');
        $this->get('/log-explorer/assets/explorer-v1.js')->assertOk();
    }

    public function test_login_and_logout_always_require_csrf_even_when_host_csrf_is_exempted(): void
    {
        $this->post('/log-explorer/login', ['username' => 'log-operator', 'password' => self::PASSWORD])->assertStatus(419);
        $this->post('/log-explorer/login', ['_token' => 'invalid'])->assertStatus(419);
        $this->signIn();
        $this->post('/log-explorer/logout')->assertStatus(419);
        $this->getJson('/log-explorer/api/entries')->assertOk();
        $this->post('/log-explorer/logout', ['_token' => session()->token()])->assertRedirect('/log-explorer/login');
        $this->rememberCookie();
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->get('/log-explorer/logout')->assertStatus(405);
    }

    public function test_session_rotates_and_logout_preserves_host_login_and_other_session_data(): void
    {
        $this->authorized();
        $token = $this->csrf();
        session()->put('host_application_marker', 'keep-me');
        session()->save();
        $before = session()->getId();
        $this->post('/log-explorer/login', ['username' => 'log-operator', 'password' => self::PASSWORD, '_token' => $token])->assertRedirect('/log-explorer');
        $this->rememberCookie();
        self::assertNotSame($before, session()->getId());
        self::assertNotSame($token, session()->token());
        self::assertSame(7, auth()->id());
        $beforeLogout = session()->getId();
        $this->post('/log-explorer/logout', ['_token' => session()->token()])->assertRedirect('/log-explorer/login');
        $this->rememberCookie();
        self::assertNotSame($beforeLogout, session()->getId());
        self::assertSame('keep-me', session()->get('host_application_marker'));
        self::assertSame(7, auth()->id());
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
    }

    public function test_invalid_credentials_are_generic_and_never_flashed_or_logged(): void
    {
        $token = $this->csrf();
        foreach ([['username' => 'wrong-user', 'password' => self::PASSWORD], ['username' => 'log-operator', 'password' => 'SECRET_CANARY'], ['username' => ['array'], 'password' => ['array']]] as $values) {
            $this->post('/log-explorer/login', $values + ['_token' => $token])->assertRedirect('/log-explorer/login')
                ->assertSessionHas('log_explorer_error', 'Invalid username or password.');
            self::assertFalse(session()->has('_old_input'));
        }
        $response = $this->get('/log-explorer/login')->assertOk();
        self::assertStringNotContainsString('SECRET_CANARY', $response->getContent());
        self::assertStringNotContainsString(self::PASSWORD, $response->getContent());
        self::assertSame([], $this->entries());
    }

    public function test_brute_force_is_limited_per_ip_and_across_ips_for_the_account(): void
    {
        config(['log-explorer.dashboard.auth.login_attempts' => 1]);
        $token = $this->csrf();
        $values = ['username' => 'wrong', 'password' => 'wrong', '_token' => $token];
        $this->post('/log-explorer/login', $values)->assertRedirect();
        $this->post('/log-explorer/login', $values)->assertStatus(429)->assertHeader('Retry-After');
        foreach (['192.0.2.2', '192.0.2.3', '192.0.2.4'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/log-explorer/login', $values)->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])->post('/log-explorer/login', $values)->assertStatus(429);
    }

    public function test_idle_absolute_timeout_and_credential_rotation_invalidate_access(): void
    {
        config(['log-explorer.dashboard.auth.idle_minutes' => 1, 'log-explorer.dashboard.auth.session_minutes' => 2]);
        $this->signIn();
        $this->travel(61)->seconds();
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->signIn();
        $this->travel(50)->seconds();
        $this->getJson('/log-explorer/api/entries')->assertOk();
        $this->travel(50)->seconds();
        $this->getJson('/log-explorer/api/entries')->assertOk();
        $this->travel(21)->seconds();
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->signIn();
        config(['log-explorer.dashboard.auth.username' => 'new-operator']);
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
    }

    public function test_bad_config_and_unknown_mode_fail_closed_without_disclosing_values(): void
    {
        foreach (['', 'plain-password-SECRET_CANARY'] as $hash) {
            config(['log-explorer.dashboard.auth.password_hash' => $hash]);
            $response = $this->get('/log-explorer/login')->assertStatus(503);
            self::assertStringNotContainsString('SECRET_CANARY', $response->getContent());
            $this->getJson('/log-explorer/api/entries')->assertStatus(503);
        }
        config(['log-explorer.dashboard.auth.mode' => 'typo']);
        $this->getJson('/log-explorer/api/entries')->assertStatus(503);
        config(['log-explorer.enabled' => false]);
        $this->get('/log-explorer/login')->assertNotFound();
    }

    public function test_header_protection_applies_to_login_and_login_stylesheet(): void
    {
        foreach (['/log-explorer/login', '/log-explorer/login/assets/login-v1.css'] as $path) {
            $response = $this->get($path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            self::assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_credentials_do_not_accept_bcrypt_suffixes_and_doctor_checks_credentials_without_gate(): void
    {
        $login = app(DashboardLogin::class);
        self::assertTrue($login->verify('log-operator', self::PASSWORD));
        self::assertFalse($login->verify('log-operator', str_repeat('x', 1025)));
        self::assertFalse($login->verify('log-operator', self::PASSWORD."\0suffix"));
        $password = str_repeat('a', 72);
        config(['log-explorer.dashboard.auth.password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4])]);
        self::assertFalse($login->verify('log-operator', $password.'suffix'));
        Log::channel('observability')->info('create private storage');
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->logRoot.'/cache']);
        $this->artisan('log-explorer:doctor')->assertSuccessful();
    }

    public function test_logging_out_revokes_a_replayed_session_and_cache_eviction_fails_closed(): void
    {
        $this->signIn();
        $oldState = session()->get('_log_explorer_credentials');
        $this->post('/log-explorer/logout', ['_token' => session()->token()])->assertRedirect('/log-explorer/login');
        $this->rememberCookie();
        $this->withSession(['_log_explorer_credentials' => $oldState]);
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->signIn();
        Cache::flush();
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
    }

    public function test_independent_login_has_no_user_database_query_and_is_protected_in_production(): void
    {
        $queries = 0;
        app('events')->listen(QueryExecuted::class, function () use (&$queries): void {
            $queries++;
        });
        $this->app->instance('env', 'production');
        $this->getJson('/log-explorer/api/entries')->assertUnauthorized();
        $this->signIn();
        $this->getJson('/log-explorer/api/entries')->assertOk();
        self::assertSame(0, $queries);
    }

    public function test_failed_cache_registration_does_not_create_an_authenticated_session(): void
    {
        $this->csrf();
        $request = Request::create('/log-explorer/login', 'POST');
        $request->setLaravelSession(session()->driver());
        Cache::partialMock()->shouldReceive('put')->once()->andReturn(false);
        try {
            app(DashboardLogin::class)->signIn($request);
            self::fail('Cache failure must reject login.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
            self::assertFalse($request->session()->has('_log_explorer_credentials'));
        }
    }

    public function test_transient_session_or_cache_configuration_fails_before_accepting_credentials(): void
    {
        config(['cache.default' => 'array']);
        $this->get('/log-explorer/login')->assertStatus(503);
        config(['cache.default' => 'file', 'session.driver' => 'array']);
        $this->get('/log-explorer/login')->assertStatus(503);
    }
}
