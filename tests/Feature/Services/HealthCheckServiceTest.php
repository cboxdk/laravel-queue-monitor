<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\HealthCheckService;

test('health check returns comprehensive status', function () {
    JobMonitor::factory()->count(10)->create();

    $service = app(HealthCheckService::class);
    $result = $service->check();

    expect($result)->toHaveKeys(['status', 'checks', 'timestamp']);
    expect($result['checks'])->toHaveKeys([
        'database',
        'recent_activity',
        'stuck_jobs',
        'error_rate',
        'queue_backlog',
        'storage',
    ]);
});

test('database check passes when connection works', function () {
    $service = app(HealthCheckService::class);
    $result = $service->check();

    expect($result['checks']['database']['healthy'])->toBeTrue();
});

test('recent activity check detects jobs', function () {
    JobMonitor::factory()->create(['queued_at' => now()->subMinutes(30)]);

    $service = app(HealthCheckService::class);
    $result = $service->check();

    expect($result['checks']['recent_activity']['healthy'])->toBeTrue();
    expect($result['checks']['recent_activity']['details']['jobs_last_hour'])->toBe(1);
});

test('stuck jobs check detects stuck processing jobs', function () {
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subHours(2),
    ]);

    $service = app(HealthCheckService::class);
    $result = $service->check();

    expect($result['checks']['stuck_jobs']['healthy'])->toBeFalse();
    expect($result['checks']['stuck_jobs']['details']['stuck_count'])->toBe(1);
});

test('health score calculation works', function () {
    JobMonitor::factory()->count(5)->create();

    $service = app(HealthCheckService::class);
    $score = $service->getHealthScore();

    expect($score)->toBeInt();
    expect($score)->toBeGreaterThanOrEqual(0);
    expect($score)->toBeLessThanOrEqual(100);
});

test('isHealthy returns correct boolean', function () {
    JobMonitor::factory()->count(5)->create();

    $service = app(HealthCheckService::class);

    expect($service->isHealthy())->toBeTrue();
});
