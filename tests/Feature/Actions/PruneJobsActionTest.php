<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

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
    config()->set('queue-monitor.retention.max_rows', null);
    config()->set('queue-monitor.retention.prune_statuses', ['completed']);

    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(20),
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute();

    expect($deleted)->toBe(1);
});

test('prune action enforces max_rows limit', function () {
    config()->set('queue-monitor.retention.max_rows', null);

    // Create 10 completed jobs
    JobMonitor::factory()->count(10)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute(days: 0, maxRows: 5);

    expect($deleted)->toBe(5);
    expect(JobMonitor::count())->toBe(5);
});

test('prune action enforces max_rows from config', function () {
    config()->set('queue-monitor.retention.days', 365);
    config()->set('queue-monitor.retention.max_rows', 3);
    config()->set('queue-monitor.retention.prune_statuses', ['completed']);

    JobMonitor::factory()->count(8)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute();

    expect($deleted)->toBe(5);
    expect(JobMonitor::count())->toBe(3);
});

test('prune action does nothing when row count is under max_rows', function () {
    config()->set('queue-monitor.retention.days', 365);
    config()->set('queue-monitor.retention.max_rows', 100);

    JobMonitor::factory()->count(5)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute();

    expect($deleted)->toBe(0);
    expect(JobMonitor::count())->toBe(5);
});

test('prune action combines time-based and max_rows pruning', function () {
    config()->set('queue-monitor.retention.max_rows', null);

    // 3 old jobs (will be pruned by time)
    JobMonitor::factory()->count(3)->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(10),
    ]);

    // 7 recent jobs (some pruned by max_rows)
    JobMonitor::factory()->count(7)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $action = app(PruneJobsAction::class);
    // days=5 removes the 3 old ones, maxRows=5 removes 2 more of the remaining 7
    $deleted = $action->execute(days: 5, maxRows: 5);

    expect($deleted)->toBe(5);
    expect(JobMonitor::count())->toBe(5);
});

test('prune action skips max_rows when set to null', function () {
    config()->set('queue-monitor.retention.days', 365);
    config()->set('queue-monitor.retention.max_rows', null);

    JobMonitor::factory()->count(10)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute();

    expect($deleted)->toBe(0);
    expect(JobMonitor::count())->toBe(10);
});

test('prune action max_rows only prunes matching statuses', function () {
    config()->set('queue-monitor.retention.max_rows', null);

    // 5 completed (prunable)
    JobMonitor::factory()->count(5)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    // 5 processing (not prunable by default statuses)
    JobMonitor::factory()->count(5)->create([
        'status' => JobStatus::PROCESSING,
    ]);

    $action = app(PruneJobsAction::class);
    $deleted = $action->execute(days: 0, statuses: [JobStatus::COMPLETED], maxRows: 3);

    // Time-based: 0 (all recent). Max-rows: 10 total, max 3, but only 5 are prunable
    // It deletes 5 completed via max_rows since 10 > 3 and only completed match
    expect(JobMonitor::where('status', JobStatus::PROCESSING)->count())->toBe(5);
});
