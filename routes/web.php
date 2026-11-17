<?php

use Fiachehr\LaravelLogger\Http\AuthorizeDashboard;
use Fiachehr\LaravelLogger\Http\DashboardController;
use Fiachehr\LaravelLogger\Http\DashboardEnabled;
use Fiachehr\LaravelLogger\Http\DashboardHeaders;
use Fiachehr\LaravelLogger\Http\LoginController;
use Fiachehr\LaravelLogger\Http\VerifyDashboardCsrf;
use Illuminate\Support\Facades\Route;

Route::prefix(config('log-explorer.dashboard.path', 'log-explorer'))
    ->middleware(array_merge(config('log-explorer.dashboard.middleware', ['web']), [DashboardHeaders::class, DashboardEnabled::class]))
    ->name('log-explorer.')
    ->group(function (): void {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware(VerifyDashboardCsrf::class)->name('login.submit');
        Route::get('/login/assets/login-v1.css', [LoginController::class, 'stylesheet'])->name('login.style');
        Route::middleware(AuthorizeDashboard::class)->group(function (): void {
            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::get('/api/entries', [DashboardController::class, 'entries'])->name('entries');
            Route::post('/api/entries', [DashboardController::class, 'entries'])->middleware(VerifyDashboardCsrf::class);
            Route::get('/assets/{asset}', [DashboardController::class, 'asset'])->where('asset', 'explorer-v1\.(css|js)')->name('asset');
            Route::post('/logout', [LoginController::class, 'destroy'])->middleware(VerifyDashboardCsrf::class)->name('logout');
        });
    });
