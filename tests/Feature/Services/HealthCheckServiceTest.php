<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
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

test('readiness returns production readiness checks', function () {
    $service = app(HealthCheckService::class);
    $result = $service->readiness();

    expect($result)->toHaveKeys(['status', 'checks', 'timestamp']);
    expect($result['checks'])->toHaveKeys([
        'access_control',
        'api_middleware',
        'payload_storage',
        'retention',
        'horizon_timeouts',
    ]);
});

test('readiness passes for protected production configuration', function () {
    app()->detectEnvironment(fn (): string => 'production');
    LaravelQueueMonitor::auth(fn (): bool => true);

    config()->set('queue-monitor.api.enabled', true);
    config()->set('queue-monitor.api.middleware', ['api', 'auth:sanctum']);
    config()->set('queue-monitor.storage.store_payload', false);

    $service = app(HealthCheckService::class);
    $result = $service->readiness();

    expect($result['status'])->toBe('ready');
    expect($result['checks']['access_control']['healthy'])->toBeTrue();
    expect($result['checks']['api_middleware']['healthy'])->toBeTrue();
    expect($result['checks']['payload_storage']['healthy'])->toBeTrue();
});

test('readiness flags missing production authorization callback', function () {
    app()->detectEnvironment(fn (): string => 'production');
    LaravelQueueMonitor::$authUsing = null;

    config()->set('queue-monitor.api.enabled', false);
    config()->set('queue-monitor.storage.store_payload', false);

    $service = app(HealthCheckService::class);
    $result = $service->readiness();

    expect($result['status'])->toBe('attention');
    expect($result['checks']['access_control']['healthy'])->toBeFalse();
});

test('readiness flags production payload storage', function () {
    app()->detectEnvironment(fn (): string => 'production');
    LaravelQueueMonitor::auth(fn (): bool => true);

    config()->set('queue-monitor.api.enabled', false);
    config()->set('queue-monitor.storage.store_payload', true);

    $service = app(HealthCheckService::class);
    $result = $service->readiness();

    expect($result['status'])->toBe('attention');
    expect($result['checks']['payload_storage']['healthy'])->toBeFalse();
});
