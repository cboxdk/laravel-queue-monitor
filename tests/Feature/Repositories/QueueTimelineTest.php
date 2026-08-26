<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

it('buckets one queue per minute with duration and memory aggregates', function () {
    $minute = now()->subMinutes(5)->startOfMinute();

    JobMonitor::factory()->create([
        'queue' => 'payments',
        'queued_at' => $minute,
        'duration_ms' => 100,
        'memory_peak_mb' => 40.0,
        'worker_memory_limit_mb' => 256.0,
    ]);
    JobMonitor::factory()->failed()->create([
        'queue' => 'payments',
        'queued_at' => $minute->copy()->addSeconds(30),
        'duration_ms' => 300,
        'memory_peak_mb' => 80.0,
    ]);
    // Jobs on other queues must not leak into the timeline.
    JobMonitor::factory()->create(['queue' => 'emails', 'queued_at' => $minute]);

    $timeline = app(StatisticsRepositoryContract::class)->getQueueTimeline('payments', 15);

    expect($timeline['buckets'])->toHaveCount(16);
    expect($timeline['memory_limit_mb'])->toBe(256.0);

    $bucket = collect($timeline['buckets'])->firstWhere('minute', $minute->format('Y-m-d H:i'));
    expect($bucket)->not->toBeNull();
    expect($bucket['total'])->toBe(2);
    expect($bucket['completed'])->toBe(1);
    expect($bucket['failed'])->toBe(1);
    expect($bucket['avg_duration_ms'])->toBe(200.0);
    expect($bucket['max_duration_ms'])->toBe(300);
    expect($bucket['max_memory_mb'])->toBe(80.0);
    expect($bucket['ts'])->not->toBeEmpty();
});

it('reports live processing, waiting, and delayed counts for the queue', function () {
    JobMonitor::factory()->processing()->create(['queue' => 'payments']);
    JobMonitor::factory()->queued()->count(2)->create(['queue' => 'payments', 'available_at' => null]);
    JobMonitor::factory()->queued()->create(['queue' => 'payments', 'available_at' => now()->addMinutes(10)]);
    JobMonitor::factory()->queued()->create(['queue' => 'emails', 'available_at' => null]);

    $timeline = app(StatisticsRepositoryContract::class)->getQueueTimeline('payments', 15);

    expect($timeline['live'])->toBe(['processing' => 1, 'waiting' => 2, 'delayed' => 1]);
});
