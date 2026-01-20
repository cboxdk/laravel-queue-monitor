<?php

use Illuminate\Support\Facades\Route;
use Cbox\LaravelQueueMonitor\Http\Controllers\DashboardController;

Route::prefix(config('queue-monitor.ui.route_prefix'))
    ->middleware(config('queue-monitor.ui.middleware'))
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('queue-monitor.dashboard');
        Route::get('/metrics', [DashboardController::class, 'metrics'])->name('queue-monitor.metrics');
        Route::get('/jobs/{uuid}/payload', [DashboardController::class, 'payload'])->name('queue-monitor.job.payload');
    });
