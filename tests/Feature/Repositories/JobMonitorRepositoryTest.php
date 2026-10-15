<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobAttemptRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Support\Facades\DB;

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

test('update persists changes and returns the refreshed record', function () {
    $job = JobMonitor::factory()->create(['status' => JobStatus::PROCESSING]);

    $updated = $this->repository->update($job->uuid, [
        'status' => JobStatus::COMPLETED,
    ]);

    expect($updated)->toBeInstanceOf(JobMonitor::class);
    expect($updated->uuid)->toBe($job->uuid);
    expect($updated->status)->toBe(JobStatus::COMPLETED);
    expect(JobMonitor::firstWhere('uuid', $job->uuid)->status)->toBe(JobStatus::COMPLETED);
});

test('update returns the in-memory record when the row is deleted mid-update', function () {
    $job = JobMonitor::factory()->create(['status' => JobStatus::PROCESSING]);

    // Simulate a concurrent prune deleting the row in the window between the
    // UPDATE and the fresh() re-read, which makes fresh() return null.
    JobMonitor::updated(function (JobMonitor $updated) use ($job): void {
        if ($updated->getKey() === $job->getKey()) {
            DB::table($job->getTable())->where('id', $job->getKey())->delete();
        }
    });

    $updated = $this->repository->update($job->uuid, [
        'status' => JobStatus::COMPLETED,
    ]);

    expect($updated)->toBeInstanceOf(JobMonitor::class);
    expect($updated->status)->toBe(JobStatus::COMPLETED);
    expect(JobMonitor::firstWhere('uuid', $job->uuid))->toBeNull();
});

test('update throws when the uuid does not exist', function () {
    $this->repository->update('missing-uuid', ['status' => JobStatus::COMPLETED]);
})->throws(RuntimeException::class);

test('it implements the attempt lookup contract', function () {
    expect($this->repository)->toBeInstanceOf(JobAttemptRepositoryContract::class);
});

test('can find one attempt of a job', function () {
    JobMonitor::factory()->create(['job_id' => 'attempted', 'attempt' => 1]);
    $second = JobMonitor::factory()->create(['job_id' => 'attempted', 'attempt' => 2]);
    JobMonitor::factory()->create(['job_id' => 'attempted', 'attempt' => 3]);
    JobMonitor::factory()->create(['job_id' => 'other', 'attempt' => 2]);

    expect($this->repository->findAttemptByJobId('attempted', 2)?->uuid)->toBe($second->uuid)
        ->and($this->repository->findAttemptByJobId('attempted', 4))->toBeNull()
        ->and($this->repository->findAttemptByJobId('missing', 1))->toBeNull();
});

test('finding an attempt accepts the integer id of the database driver', function () {
    $row = JobMonitor::factory()->create(['job_id' => '98765', 'attempt' => 2]);

    expect($this->repository->findAttemptByJobId(98765, 2)?->uuid)->toBe($row->uuid);
});

test('finding an attempt with duplicate rows returns the most recent', function () {
    JobMonitor::factory()->create(['job_id' => 'duplicated', 'attempt' => 2, 'created_at' => now()->subMinute()]);
    $newer = JobMonitor::factory()->create(['job_id' => 'duplicated', 'attempt' => 2, 'created_at' => now()]);

    expect($this->repository->findAttemptByJobId('duplicated', 2)?->uuid)->toBe($newer->uuid);
});

test('finding an attempt with duplicate rows returns the row recorded last, whatever created_at says', function () {
    // created_at holds whole seconds and can tie or go backwards (clock steps
    // between hosts); the id is the order the rows were recorded in.
    JobMonitor::factory()->create(['job_id' => 'skewed', 'attempt' => 1, 'created_at' => now()]);
    $recordedLast = JobMonitor::factory()->create(['job_id' => 'skewed', 'attempt' => 1, 'created_at' => now()->subMinute()]);

    expect($this->repository->findAttemptByJobId('skewed', 1)?->uuid)->toBe($recordedLast->uuid)
        ->and($this->repository->findLatestAttemptByJobId('skewed')?->uuid)->toBe($recordedLast->uuid);
});

test('the latest attempt is the row recorded last, not the highest attempt number', function () {
    JobMonitor::factory()->create(['job_id' => 'rerun', 'attempt' => 3]);
    $retryRun = JobMonitor::factory()->create(['job_id' => 'rerun', 'attempt' => 1]);

    expect($this->repository->findLatestAttemptByJobId('rerun')?->uuid)->toBe($retryRun->uuid);
});
