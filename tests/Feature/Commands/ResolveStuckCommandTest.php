<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

test('resolve-stuck marks old processing jobs as timed out', function () {
    config()->set('queue-monitor.retention.resolve_stuck_after_minutes', 30);

    $stuck = JobMonitor::factory()->create([
        'status' => JobStatus::PROCESSING,
        'started_at' => now()->subMinutes(45),
        'completed_at' => null,
    ]);

    $this->artisan('queue-monitor:resolve-stuck')
        ->expectsOutputToContain('Marked 1 stuck job(s) as timed out.')
        ->assertSuccessful();

    $stuck->refresh();

    expect($stuck->status)->toBe(JobStatus::TIMEOUT)
        ->and($stuck->completed_at)->not->toBeNull();
});

test('resolve-stuck leaves processing jobs within the threshold untouched', function () {
    config()->set('queue-monitor.retention.resolve_stuck_after_minutes', 30);

    $live = JobMonitor::factory()->create([
        'status' => JobStatus::PROCESSING,
        'started_at' => now()->subMinutes(5),
        'completed_at' => null,
    ]);

    $this->artisan('queue-monitor:resolve-stuck')
        ->expectsOutputToContain('No stuck jobs found.')
        ->assertSuccessful();

    expect($live->fresh()->status)->toBe(JobStatus::PROCESSING);
});

test('resolve-stuck skips when no threshold is configured', function () {
    config()->set('queue-monitor.retention.resolve_stuck_after_minutes', null);

    $stuck = JobMonitor::factory()->create([
        'status' => JobStatus::PROCESSING,
        'started_at' => now()->subMinutes(120),
        'completed_at' => null,
    ]);

    $this->artisan('queue-monitor:resolve-stuck')
        ->expectsOutputToContain('No stuck-job threshold configured')
        ->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(JobStatus::PROCESSING);
});

test('resolve-stuck honors the --minutes option over config', function () {
    config()->set('queue-monitor.retention.resolve_stuck_after_minutes', null);

    $stuck = JobMonitor::factory()->create([
        'status' => JobStatus::PROCESSING,
        'started_at' => now()->subMinutes(20),
        'completed_at' => null,
    ]);

    $this->artisan('queue-monitor:resolve-stuck', ['--minutes' => 10])
        ->expectsOutputToContain('Marked 1 stuck job(s) as timed out.')
        ->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(JobStatus::TIMEOUT);
});

test('resolve-stuck --dry-run reports without changing rows', function () {
    config()->set('queue-monitor.retention.resolve_stuck_after_minutes', 30);

    $stuck = JobMonitor::factory()->create([
        'status' => JobStatus::PROCESSING,
        'started_at' => now()->subMinutes(45),
        'completed_at' => null,
    ]);

    $this->artisan('queue-monitor:resolve-stuck', ['--dry-run' => true])
        ->expectsOutputToContain('would be marked as timed out')
        ->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(JobStatus::PROCESSING);
});
