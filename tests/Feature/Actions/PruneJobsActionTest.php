<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('prune action removes jobs older than specified days', function () {
    JobMonitor::factory()->create(['created_at' => now()->subDays(40)]);
    JobMonitor::factory()->create(['created_at' => now()->subDays(20)]);
    JobMonitor::factory()->create(['created_at' => now()->subDays(10)]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute(days: 30);

    expect($deleted)->toBe(1);
    expect(JobMonitor::count())->toBe(2);
});

test('prune action only removes specified statuses', function () {
    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(40),
    ]);

    JobMonitor::factory()->failed()->create([
        'created_at' => now()->subDays(40),
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute(days: 30, statuses: [JobStatus::COMPLETED]);

    expect($deleted)->toBe(1);
    expect(JobMonitor::count())->toBe(1);
    expect(JobMonitor::first()->status)->toBe(JobStatus::FAILED);
});

test('prune action uses config defaults when no parameters provided', function () {
    config()->set('queue-monitor.retention.days', 15);
    config()->set('queue-monitor.retention.prune_statuses', ['completed']);

    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(20),
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute();

    expect($deleted)->toBe(1);
});
