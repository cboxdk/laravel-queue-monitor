<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PHPeek\LaravelQueueMonitor\Http\Controllers\BatchOperationsController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\ExportController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\HealthCheckController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\JobMonitorController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\JobReplayController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\PruneController;
use PHPeek\LaravelQueueMonitor\Http\Controllers\StatisticsController;
use PHPeek\LaravelQueueMonitor\Http\Middleware\EnsureQueueMonitorEnabled;

if (! config('queue-monitor.api.enabled', true)) {
    return;
}

Route::prefix(config('queue-monitor.api.prefix', 'api/queue-monitor'))
    ->middleware(array_merge(
        config('queue-monitor.api.middleware', ['api']),
        [EnsureQueueMonitorEnabled::class]
    ))
    ->name('queue-monitor.')
    ->group(function (): void {
        // Job listing and details
        Route::get('/jobs', [JobMonitorController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/failed', [JobMonitorController::class, 'failed'])->name('jobs.failed');
        Route::get('/jobs/recent', [JobMonitorController::class, 'recent'])->name('jobs.recent');
        Route::get('/jobs/{uuid}', [JobMonitorController::class, 'show'])->name('jobs.show');
        Route::delete('/jobs/{uuid}', [JobMonitorController::class, 'destroy'])->name('jobs.destroy');
        Route::get('/jobs/{uuid}/retry-chain', [JobMonitorController::class, 'retryChain'])->name('jobs.retry-chain');

        // Job replay
        Route::post('/jobs/{uuid}/replay', JobReplayController::class)->name('jobs.replay');

        // Statistics
        Route::get('/statistics', [StatisticsController::class, 'global'])->name('statistics.global');
        Route::get('/statistics/servers', [StatisticsController::class, 'servers'])->name('statistics.servers');
        Route::get('/statistics/queues', [StatisticsController::class, 'queues'])->name('statistics.queues');
        Route::get('/statistics/job-classes', [StatisticsController::class, 'jobClasses'])->name('statistics.job-classes');
        Route::get('/statistics/queue-health', [StatisticsController::class, 'queueHealth'])->name('statistics.queue-health');
        Route::get('/statistics/failure-patterns', [StatisticsController::class, 'failurePatterns'])->name('statistics.failure-patterns');
        Route::get('/statistics/tags', [StatisticsController::class, 'tags'])->name('statistics.tags');

        // Maintenance
        Route::post('/prune', PruneController::class)->name('prune');

        // Batch Operations
        Route::post('/batch/replay', [BatchOperationsController::class, 'batchReplay'])->name('batch.replay');
        Route::post('/batch/delete', [BatchOperationsController::class, 'batchDelete'])->name('batch.delete');

        // Health & Monitoring
        Route::get('/health', [HealthCheckController::class, 'index'])->name('health');
        Route::get('/health/score', [HealthCheckController::class, 'score'])->name('health.score');
        Route::get('/health/alerts', [HealthCheckController::class, 'alerts'])->name('health.alerts');

        // Export
        Route::get('/export/csv', [ExportController::class, 'csv'])->name('export.csv');
        Route::get('/export/json', [ExportController::class, 'json'])->name('export.json');
        Route::get('/export/statistics', [ExportController::class, 'statistics'])->name('export.statistics');
        Route::get('/export/failed-jobs', [ExportController::class, 'failedJobs'])->name('export.failed-jobs');
    });
