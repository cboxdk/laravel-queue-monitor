<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

beforeEach(function () {
    $this->repository = app(JobMonitorRepositoryContract::class);
});

test('can find job by uuid', function () {
    $job = JobMonitor::factory()->create();

    $found = $this->repository->findByUuid($job->uuid);

    expect($found)->not->toBeNull();
    expect($found->uuid)->toBe($job->uuid);
});

test('can query with filters', function () {
    JobMonitor::factory()->count(5)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(3)->failed()->create();

    $filters = new JobFilterData(
        statuses: [JobStatus::FAILED, JobStatus::TIMEOUT]
    );

    $results = $this->repository->query($filters);

    expect($results)->toHaveCount(3);
    expect($results->first()->status->isFailed())->toBeTrue();
});

test('can count with filters', function () {
    JobMonitor::factory()->count(10)->create();

    $filters = new JobFilterData(limit: 100);

    expect($this->repository->count($filters))->toBe(10);
});

test('can get retry chain', function () {
    $original = JobMonitor::factory()->failed()->create();

    $retry1 = JobMonitor::factory()->failed()->create([
        'uuid' => $original->uuid,
        'attempt' => 2,
        'retried_from_id' => $original->id,
    ]);

    $retry2 = JobMonitor::factory()->create([
        'uuid' => $original->uuid,
        'attempt' => 3,
        'retried_from_id' => $retry1->id,
        'status' => JobStatus::COMPLETED,
    ]);

    $chain = $this->repository->getRetryChain($original->uuid);

    expect($chain)->toHaveCount(3);
    expect($chain->first()->attempt)->toBe(1);
    expect($chain->last()->attempt)->toBe(3);
});

test('prune removes old jobs', function () {
    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(40),
    ]);

    JobMonitor::factory()->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(10),
    ]);

    $deleted = $this->repository->prune(30, [JobStatus::COMPLETED]);

    expect($deleted)->toBe(1);
    expect(JobMonitor::count())->toBe(1);
});
