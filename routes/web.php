<?php

use Fiachehr\LaravelLogger\Http\AuthorizeDashboard;
use Fiachehr\LaravelLogger\Http\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('log-explorer.dashboard.path', 'log-explorer'))
    ->middleware(array_merge(config('log-explorer.dashboard.middleware', ['web']), [AuthorizeDashboard::class]))
    ->name('log-explorer.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/api/entries', [DashboardController::class, 'entries'])->name('entries');
        Route::get('/assets/{asset}', [DashboardController::class, 'asset'])->where('asset', 'explorer-v1\.(css|js)')->name('asset');
    });
