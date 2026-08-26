<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\DashboardCacheService;
use Cbox\LaravelQueueMonitor\Services\InfrastructureService;

test('SLA compliance is computed per queue with distinct targets in SQL', function () {
    config()->set('queue-autoscale.queues', [
        'fast' => ['max_pickup_time_seconds' => 5],
        'slow' => ['max_pickup_time_seconds' => 60],
    ]);

    // fast queue: one within 5s, one over → 50%
    JobMonitor::factory()->create(['queue' => 'fast', 'queued_at' => now()->subSeconds(10), 'started_at' => now()->subSeconds(8)]); // 2s pickup, within
    JobMonitor::factory()->create(['queue' => 'fast', 'queued_at' => now()->subSeconds(30), 'started_at' => now()->subSeconds(10)]); // 20s pickup, breached
    // slow queue: both within 60s → 100%
    JobMonitor::factory()->create(['queue' => 'slow', 'queued_at' => now()->subSeconds(40), 'started_at' => now()->subSeconds(10)]); // 30s pickup, within

    $sla = app(InfrastructureService::class)->getSlaData();
    $byQueue = collect($sla['per_queue'])->keyBy('queue');

    expect($byQueue['fast']['total'])->toBe(2);
    expect($byQueue['fast']['within'])->toBe(1);
    expect($byQueue['fast']['compliance'])->toBe(50.0);
    expect($byQueue['slow']['within'])->toBe(1);
    expect($byQueue['slow']['compliance'])->toBe(100.0);
});

test('SLA data works with only a default target and no per-queue config', function () {
    config()->set('queue-autoscale.queues', []);

    JobMonitor::factory()->create(['queue' => 'default', 'queued_at' => now()->subSeconds(10), 'started_at' => now()->subSeconds(8)]);

    $sla = app(InfrastructureService::class)->getSlaData();

    expect($sla['available'])->toBeTrue();
    expect($sla['per_queue'])->toHaveCount(1);
    expect($sla['per_queue'][0]['queue'])->toBe('default');
});

test('cache bust throttles repeated version bumps within the window', function () {
    config()->set('queue-monitor.cache.enabled', true);
    config()->set('queue-monitor.cache.bust_throttle_seconds', 60);

    $service = app(DashboardCacheService::class);

    $service->remember('probe', fn () => 'warm');
    $keyBefore = $service->scopedKey('probe');

    // A burst of busts within the window collapses to one version bump.
    $service->bust();
    $service->bust();
    $service->bust();

    $keyAfter = $service->scopedKey('probe');

    // Exactly one increment, so the warm entry survives repeated writes.
    expect($keyAfter)->not->toBe($keyBefore);
    $keyAfterAgain = (function () use ($service) {
        $service->bust();

        return $service->scopedKey('probe');
    })();
    expect($keyAfterAgain)->toBe($keyAfter);
});
