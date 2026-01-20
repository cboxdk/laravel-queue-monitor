<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Cbox\LaravelQueueMonitor\Actions\Batch\BatchDeleteAction;
use Cbox\LaravelQueueMonitor\Actions\Batch\BatchReplayAction;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

test('batch replay processes multiple jobs', function () {
    Queue::fake();

    $jobs = JobMonitor::factory()->count(5)->failed()->create();
    $uuids = $jobs->pluck('uuid')->toArray();

    $action = app(BatchReplayAction::class);
    $result = $action->executeByUuids($uuids);

    expect($result['success'])->toBe(5);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();
});

test('batch replay handles failures gracefully', function () {
    Queue::fake();

    $validJob = JobMonitor::factory()->failed()->create();
    $invalidJob = JobMonitor::factory()->processing()->create(); // Can't replay processing

    $action = app(BatchReplayAction::class);
    $result = $action->executeByUuids([$validJob->uuid, $invalidJob->uuid]);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(1);
    expect($result['errors'])->toHaveKey($invalidJob->uuid);
});

test('batch replay with filters replays matching jobs', function () {
    Queue::fake();

    JobMonitor::factory()->count(3)->failed()->create(['queue' => 'emails']);
    JobMonitor::factory()->count(2)->failed()->create(['queue' => 'sms']);

    $filters = new JobFilterData(
        statuses: [JobStatus::FAILED],
        queues: ['emails']
    );

    $action = app(BatchReplayAction::class);
    $result = $action->execute($filters, 100);

    expect($result['success'])->toBe(3);
});

test('batch delete removes multiple jobs', function () {
    $jobs = JobMonitor::factory()->count(5)->create();
    $uuids = $jobs->pluck('uuid')->toArray();

    $action = app(BatchDeleteAction::class);
    $result = $action->executeByUuids($uuids);

    expect($result['deleted'])->toBe(5);
    expect($result['failed'])->toBe(0);
    expect(JobMonitor::count())->toBe(0);
});

test('batch delete with filters deletes matching jobs', function () {
    JobMonitor::factory()->count(10)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(3)->failed()->create();

    $filters = new JobFilterData(
        statuses: [JobStatus::COMPLETED]
    );

    $action = app(BatchDeleteAction::class);
    $result = $action->execute($filters, 100);

    expect($result['deleted'])->toBe(10);
    expect(JobMonitor::count())->toBe(3);
});

test('batch operations respect max jobs limit', function () {
    Queue::fake();

    JobMonitor::factory()->count(50)->failed()->create();

    $filters = new JobFilterData(statuses: [JobStatus::FAILED]);

    $action = app(BatchReplayAction::class);
    $result = $action->execute($filters, 20);

    expect($result['success'])->toBe(20);
});
