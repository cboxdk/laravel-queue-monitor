<?php

use Cbox\LaravelQueueMonitor\Http\Controllers\DashboardController;
use Cbox\LaravelQueueMonitor\Http\Controllers\DashboardDrillDownController;
use Cbox\LaravelQueueMonitor\Http\Controllers\DashboardHealthController;
use Cbox\LaravelQueueMonitor\Http\Controllers\DashboardMetricsController;
use Cbox\LaravelQueueMonitor\Http\Middleware\EnsureQueueMonitorEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix(config('queue-monitor.ui.route_prefix'))
    ->middleware(array_merge(
        config('queue-monitor.ui.middleware', ['web']),
        [EnsureQueueMonitorEnabled::class.':ui']
    ))
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('queue-monitor.dashboard');
        Route::get('/metrics', [DashboardMetricsController::class, 'overview'])->name('queue-monitor.dashboard.metrics');
        Route::get('/jobs', [DashboardMetricsController::class, 'jobs'])->name('queue-monitor.dashboard.jobs');
        Route::get('/jobs/{uuid}', [DashboardMetricsController::class, 'jobDetail'])->name('queue-monitor.dashboard.job.detail');
        Route::get('/analytics', [DashboardMetricsController::class, 'analytics'])->name('queue-monitor.dashboard.analytics');
        Route::get('/health', [DashboardHealthController::class, 'health'])->name('queue-monitor.dashboard.health');
        Route::get('/infrastructure', [DashboardHealthController::class, 'infrastructure'])->name('queue-monitor.dashboard.infrastructure');
        Route::get('/drill-down', [DashboardDrillDownController::class, 'drillDown'])->name('queue-monitor.dashboard.drill-down');
        Route::get('/jobs/{uuid}/payload', [DashboardMetricsController::class, 'payload'])->name('queue-monitor.job.payload');
    });
