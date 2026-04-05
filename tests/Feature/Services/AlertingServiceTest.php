<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\AlertingService;

test('returns no alerts when everything is healthy', function () {
    JobMonitor::factory()->count(5)->create();

    $service = app(AlertingService::class);
    $alerts = $service->checkAlertConditions();

    expect($alerts)->not->toHaveKey('stuck_jobs');
    expect($alerts)->not->toHaveKey('high_error_rate');
    expect($alerts)->not->toHaveKey('high_backlog');
});

test('detects stuck jobs', function () {
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(45),
    ]);

    $service = app(AlertingService::class);
    $alerts = $service->checkAlertConditions();

    expect($alerts)->toHaveKey('stuck_jobs');
    expect($alerts['stuck_jobs']['severity'])->toBe('warning');
    expect($alerts['stuck_jobs']['count'])->toBe(1);
});

test('detects high error rate above 20 percent', function () {
    // 3 failed out of 10 = 30% error rate
    JobMonitor::factory()->count(7)->create([
        'queued_at' => now()->subMinutes(30),
    ]);
    JobMonitor::factory()->count(3)->failed()->create([
        'queued_at' => now()->subMinutes(30),
    ]);

    $service = app(AlertingService::class);
    $alerts = $service->checkAlertConditions();

    expect($alerts)->toHaveKey('high_error_rate');
    expect($alerts['high_error_rate']['severity'])->toBe('critical');
});

test('detects elevated error rate between 10 and 20 percent', function () {
    // 2 failed out of 15 = ~13% error rate
    JobMonitor::factory()->count(13)->create([
        'queued_at' => now()->subMinutes(30),
    ]);
    JobMonitor::factory()->count(2)->failed()->create([
        'queued_at' => now()->subMinutes(30),
    ]);

    $service = app(AlertingService::class);
    $alerts = $service->checkAlertConditions();

    expect($alerts)->toHaveKey('elevated_error_rate');
    expect($alerts['elevated_error_rate']['severity'])->toBe('warning');
});

test('detects high backlog', function () {
    JobMonitor::factory()->count(1001)->create([
        'status' => JobStatus::QUEUED,
    ]);

    $service = app(AlertingService::class);
    $alerts = $service->checkAlertConditions();

    expect($alerts)->toHaveKey('high_backlog');
    expect($alerts['high_backlog']['count'])->toBe(1001);
});

test('getCriticalAlerts filters to critical severity only', function () {
    // Create conditions for both warning and critical alerts
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(45),
    ]);
    // 5 failed out of 10 = 50% error rate (critical)
    JobMonitor::factory()->count(5)->create([
        'queued_at' => now()->subMinutes(30),
    ]);
    JobMonitor::factory()->count(5)->failed()->create([
        'queued_at' => now()->subMinutes(30),
    ]);

    $service = app(AlertingService::class);
    $critical = $service->getCriticalAlerts();

    foreach ($critical as $alert) {
        expect($alert['severity'])->toBe('critical');
    }
    expect($critical)->toHaveKey('high_error_rate');
    expect($critical)->not->toHaveKey('stuck_jobs');
});

test('requiresAttention returns true when critical alerts exist', function () {
    // 8 failed out of 10 = 80% error rate (critical)
    JobMonitor::factory()->count(2)->create([
        'queued_at' => now()->subMinutes(30),
    ]);
    JobMonitor::factory()->count(8)->failed()->create([
        'queued_at' => now()->subMinutes(30),
    ]);

    $service = app(AlertingService::class);

    expect($service->requiresAttention())->toBeTrue();
});

test('requiresAttention returns false when no critical alerts', function () {
    JobMonitor::factory()->count(5)->create();

    $service = app(AlertingService::class);

    expect($service->requiresAttention())->toBeFalse();
});
