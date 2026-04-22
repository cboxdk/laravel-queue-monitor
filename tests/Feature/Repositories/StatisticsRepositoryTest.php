<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Illuminate\Support\Str;

beforeEach(function () {
    // Disable cache for deterministic test results
    config()->set('queue-monitor.cache.enabled', false);
});

test('global statistics respects metrics_window_hours', function () {
    config()->set('queue-monitor.metrics_window_hours', 6);

    // Old job — outside window
    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subHours(12),
    ]);

    // Recent job — inside window
    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getGlobalStatistics();

    expect($stats['total'])->toBe(1);
    expect($stats['completed'])->toBe(1);
});

test('global statistics includes all data when metrics_window_hours is null', function () {
    config()->set('queue-monitor.metrics_window_hours', null);

    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(30),
    ]);

    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getGlobalStatistics();

    expect($stats['total'])->toBe(2);
});

test('job class statistics respects metrics_window_hours', function () {
    config()->set('queue-monitor.metrics_window_hours', 6);

    // Old job — outside window
    JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\OldJob',
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subHours(12),
    ]);

    // Recent job — inside window
    JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\RecentJob',
        'status' => JobStatus::COMPLETED,
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getJobClassStatistics();

    expect($stats)->toHaveCount(1);
    expect($stats[0]['job_class'])->toBe('App\\Jobs\\RecentJob');
});

test('server statistics respects metrics_window_hours', function () {
    config()->set('queue-monitor.metrics_window_hours', 6);

    JobMonitor::factory()->create([
        'server_name' => 'old-server',
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subHours(12),
    ]);

    JobMonitor::factory()->create([
        'server_name' => 'current-server',
        'status' => JobStatus::COMPLETED,
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getServerStatistics();

    expect($stats)->toHaveCount(1);
    expect($stats[0]['server_name'])->toBe('current-server');
});

test('queue statistics respects metrics_window_hours', function () {
    config()->set('queue-monitor.metrics_window_hours', 6);

    JobMonitor::factory()->create([
        'queue' => 'old-queue',
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subHours(12),
    ]);

    JobMonitor::factory()->create([
        'queue' => 'current-queue',
        'status' => JobStatus::COMPLETED,
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getQueueStatistics();

    expect($stats)->toHaveCount(1);
    expect($stats[0]['queue'])->toBe('current-queue');
});

test('failure patterns respects metrics_window_hours', function () {
    config()->set('queue-monitor.metrics_window_hours', 6);

    JobMonitor::factory()->failed()->create([
        'exception_class' => 'OldException',
        'created_at' => now()->subHours(12),
    ]);

    JobMonitor::factory()->failed()->create([
        'exception_class' => 'RecentException',
    ]);

    $repo = app(StatisticsRepositoryContract::class);
    $patterns = $repo->getFailurePatterns();

    expect($patterns['top_exceptions'])->toHaveCount(1);
    expect($patterns['top_exceptions'][0]['exception_class'])->toBe('RecentException');
});

test('global statistics returns empty result when no data exists', function () {
    $repo = app(StatisticsRepositoryContract::class);
    $stats = $repo->getGlobalStatistics();

    expect($stats['total'])->toBe(0);
    expect($stats['completed'])->toBe(0);
    expect($stats['success_rate'])->toBe(0);
    expect($stats['avg_duration_ms'])->toBeNull();
});

test('global statistics cache is invalidated after repository create', function () {
    config()->set('queue-monitor.cache.enabled', true);

    $statsRepository = app(StatisticsRepositoryContract::class);
    $jobRepository = app(JobMonitorRepositoryContract::class);

    expect($statsRepository->getGlobalStatistics()['total'])->toBe(0);

    $now = now();

    $jobRepository->create(new JobMonitorData(
        id: null,
        uuid: (string) Str::uuid(),
        jobId: 'job-1',
        jobClass: 'App\\Jobs\\FreshJob',
        displayName: 'FreshJob',
        connection: 'sync',
        queue: 'default',
        payload: null,
        status: JobStatus::QUEUED,
        attempt: 1,
        maxAttempts: 1,
        retriedFromId: null,
        serverName: 'test-server',
        workerId: 'worker-1',
        workerType: 'queue_work',
        cpuTimeMs: null,
        memoryPeakMb: null,
        fileDescriptors: null,
        durationMs: null,
        exception: null,
        tags: null,
        queuedAt: $now,
        availableAt: null,
        startedAt: null,
        completedAt: null,
        createdAt: $now,
        updatedAt: $now,
    ));

    $stats = $statsRepository->getGlobalStatistics();

    expect($stats['total'])->toBe(1);
    expect($stats['queue_backlog'])->toBe(1);
});

test('global statistics cache is invalidated after repository update', function () {
    config()->set('queue-monitor.cache.enabled', true);

    $job = JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $statsRepository = app(StatisticsRepositoryContract::class);
    $jobRepository = app(JobMonitorRepositoryContract::class);

    $initial = $statsRepository->getGlobalStatistics();
    expect($initial['failed'])->toBe(0);

    $jobRepository->update($job->uuid, [
        'status' => JobStatus::FAILED,
    ]);

    $updated = $statsRepository->getGlobalStatistics();

    expect($updated['failed'])->toBe(1);
    expect($updated['completed'])->toBe(0);
});
