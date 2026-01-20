<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Queue;

test('facade can get job by uuid', function () {
    $job = JobMonitor::factory()->create();

    $found = QueueMonitor::getJob($job->uuid);

    expect($found)->not->toBeNull();
    expect($found->uuid)->toBe($job->uuid);
});

test('facade can query jobs with filters', function () {
    JobMonitor::factory()->count(3)->create();
    JobMonitor::factory()->count(2)->failed()->create();

    $filters = new JobFilterData(
        statuses: [JobStatus::FAILED],
        limit: 10
    );

    $results = QueueMonitor::getJobs($filters);

    expect($results)->toHaveCount(2);
});

test('facade can get statistics', function () {
    JobMonitor::factory()->count(5)->create();

    $stats = QueueMonitor::statistics();

    expect($stats)->toHaveKeys(['total', 'completed', 'success_rate']);
    expect($stats['total'])->toBe(5);
});

test('facade can get failed jobs', function () {
    JobMonitor::factory()->count(3)->create();
    JobMonitor::factory()->count(2)->failed()->create();

    $failed = QueueMonitor::getFailedJobs();

    expect($failed)->toHaveCount(2);
    expect($failed->every(fn ($job) => $job->isFailed()))->toBeTrue();
});

test('facade can cancel job', function () {
    $job = JobMonitor::factory()->processing()->create();

    $result = QueueMonitor::cancel($job->uuid);

    expect($result)->toBeTrue();

    $job->refresh();
    expect($job->status)->toBe(JobStatus::CANCELLED);
});

test('facade replay returns replay data', function () {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create();

    $replayData = QueueMonitor::replay($job->uuid);

    expect($replayData->originalUuid)->toBe($job->uuid);
    expect($replayData->queue)->toBe($job->queue);
});

test('facade can get retry chain', function () {
    $original = JobMonitor::factory()->failed()->create();
    $retry = JobMonitor::factory()->create([
        'uuid' => $original->uuid,
        'attempt' => 2,
        'retried_from_id' => $original->id,
    ]);

    $chain = QueueMonitor::getRetryChain($original->uuid);

    expect($chain)->toHaveCount(2);
});
