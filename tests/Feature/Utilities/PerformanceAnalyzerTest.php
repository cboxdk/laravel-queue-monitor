<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Utilities\PerformanceAnalyzer;

test('calculates duration percentiles correctly', function () {
    // Create jobs with known durations
    foreach ([100, 200, 300, 400, 500, 1000, 2000, 5000, 10000] as $duration) {
        JobMonitor::factory()->create([
            'job_class' => 'App\\Jobs\\TestJob',
            'duration_ms' => $duration,
        ]);
    }

    $percentiles = PerformanceAnalyzer::getDurationPercentiles('App\\Jobs\\TestJob');

    expect($percentiles)->toHaveKeys(['p50', 'p75', 'p90', 'p95', 'p99']);
    expect($percentiles['p50'])->toBeGreaterThan(0);
    expect($percentiles['p99'])->toBeGreaterThan($percentiles['p50']);
});

test('detects performance regression', function () {
    // Baseline: average 500ms
    JobMonitor::factory()->count(10)->create([
        'job_class' => 'App\\Jobs\\TestJob',
        'duration_ms' => 500,
        'completed_at' => now()->subDays(20),
    ]);

    // Current: average 800ms (60% slower)
    JobMonitor::factory()->count(5)->create([
        'job_class' => 'App\\Jobs\\TestJob',
        'duration_ms' => 800,
        'completed_at' => now()->subDays(2),
    ]);

    $analysis = PerformanceAnalyzer::detectRegression('App\\Jobs\\TestJob', 30, 7);

    expect($analysis['regression'])->toBeTrue();
    expect($analysis['change_percent'])->toBeGreaterThan(20);
});

test('calculates throughput correctly', function () {
    // Create 100 jobs in last hour
    JobMonitor::factory()->count(100)->create([
        'queue' => 'test',
        'queued_at' => now()->subMinutes(30),
    ]);

    $throughput = PerformanceAnalyzer::calculateThroughput('test', 1);

    expect($throughput)->toBeGreaterThan(0);
    expect($throughput)->toBeLessThan(100); // Less than 100/sec for 100 jobs/hour
});

test('gets slowest jobs', function () {
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 100]);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 5000]);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 3000]);

    $slowest = PerformanceAnalyzer::getSlowestJobs('App\\Jobs\\TestJob', 2);

    expect($slowest)->toHaveCount(2);
    expect($slowest->first()->duration_ms)->toBe(5000);
    expect($slowest->last()->duration_ms)->toBe(3000);
});

test('duration distribution buckets jobs correctly', function () {
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 50]);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 250]);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 750]);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\TestJob', 'duration_ms' => 3000]);

    $distribution = PerformanceAnalyzer::getDurationDistribution('App\\Jobs\\TestJob');

    expect($distribution['0-100ms'])->toBe(1);
    expect($distribution['100-500ms'])->toBe(1);
    expect($distribution['500ms-1s'])->toBe(1);
    expect($distribution['1s-5s'])->toBe(1);
});
