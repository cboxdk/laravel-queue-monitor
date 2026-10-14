<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

test('retry creates new record linked to previous attempt', function () {
    // A genuine retry is a redelivery whose prior attempt actually failed; the
    // exception listener records exception_class on that attempt, which is how
    // the started action tells a real retry from a plain release.
    $original = JobMonitor::factory()->processing()->create([
        'job_id' => 'retry-job-1',
        'attempt' => 1,
        'started_at' => now()->subSeconds(5),
        'exception_class' => 'RuntimeException',
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('retry-job-1');
    $mockJob->shouldReceive('attempts')->andReturn(2);
    $mockJob->shouldReceive('payload')->andReturn([]);

    $action = app(RecordJobStartedAction::class);
    $action->execute(new JobProcessing('redis', $mockJob));

    // Original should be marked as failed
    $original->refresh();
    expect($original->status)->toBe(JobStatus::FAILED);
    expect($original->completed_at)->not->toBeNull();

    // New record created for retry
    expect(JobMonitor::count())->toBe(2);
    $retry = JobMonitor::where('attempt', 2)->first();
    expect($retry->status)->toBe(JobStatus::PROCESSING);
    expect($retry->retried_from_id)->toBe($original->id);
    expect($retry->job_id)->toBe('retry-job-1');
});

test('released job redelivery reuses the row without a failed duplicate', function () {
    // A released attempt (rate limit / middleware / manual) is parked as
    // `queued` by the completed action — it is not a failure.
    $original = JobMonitor::factory()->create([
        'job_id' => 'released-job-1',
        'status' => JobStatus::QUEUED,
        'attempt' => 1,
        'started_at' => now()->subSeconds(5),
        'completed_at' => null,
        'duration_ms' => null,
        'exception_class' => null,
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('released-job-1');
    $mockJob->shouldReceive('attempts')->andReturn(2);
    $mockJob->shouldReceive('payload')->andReturn([]);

    app(RecordJobStartedAction::class)->execute(new JobProcessing('redis', $mockJob));

    // No failed flip and no duplicate row — the same attempt row is reused.
    expect(JobMonitor::count())->toBe(1);

    $original->refresh();
    expect($original->status)->toBe(JobStatus::PROCESSING);
    expect($original->attempt)->toBe(2);
    expect($original->completed_at)->toBeNull();
});

test('createFromProcessing when no existing record for job', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('new-job-1');
    $mockJob->shouldReceive('attempts')->andReturn(1);
    $mockJob->shouldReceive('payload')->andReturn([]);
    $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\TestJob');
    $mockJob->shouldReceive('getQueue')->andReturn('default');
    $mockJob->shouldReceive('maxTries')->andReturn(3);

    $action = app(RecordJobStartedAction::class);
    $action->execute(new JobProcessing('redis', $mockJob));

    // Should create a record via createFromProcessing → RecordJobQueuedAction
    expect(JobMonitor::count())->toBe(1);
    $job = JobMonitor::first();
    expect($job->status)->toBe(JobStatus::PROCESSING);
    expect($job->started_at)->not->toBeNull();
});

test('returns early when no job in event', function () {
    $event = new stdClass;

    $action = app(RecordJobStartedAction::class);
    $action->execute($event);

    expect(JobMonitor::count())->toBe(0);
});

dataset('redelivering drivers', [
    'redis (retry_after)' => ['redis', 'redis-job-killed-1'],
    'sqs (visibility timeout)' => ['sqs', 'e3c8f1a2-7b4d-4c1e-9f0a-5d6b7c8e9f01'],
]);

/**
 * A redelivery of the same message: same job id, attempts() as the broker
 * reports it (Redis increments on reserve, SQS from ApproximateReceiveCount).
 */
function redeliveredJob(string $jobId, int $attempts, bool $released = false): Job
{
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn($jobId);
    $mockJob->shouldReceive('attempts')->andReturn($attempts);
    $mockJob->shouldReceive('payload')->andReturn([]);
    $mockJob->shouldReceive('isReleased')->andReturn($released);

    return $mockJob;
}

test('a hard-killed attempt is closed as timeout and the redelivery gets its own row', function (string $connection, string $jobId) {
    // Attempt 1 was running when its worker was SIGKILLed / OOM-killed: no
    // JobProcessed, JobFailed or JobTimedOut ever fired, so the row is still
    // `processing` with no exception.
    $killed = JobMonitor::factory()->processing()->create([
        'job_id' => $jobId,
        'connection' => $connection,
        'attempt' => 1,
        'started_at' => now()->subMinutes(2),
        'exception_class' => null,
    ]);

    app(RecordJobStartedAction::class)->execute(new JobProcessing($connection, redeliveredJob($jobId, 2)));

    expect(JobMonitor::count())->toBe(2);

    $killed->refresh();
    expect($killed->status)->toBe(JobStatus::TIMEOUT)
        ->and($killed->attempt)->toBe(1)
        ->and($killed->completed_at)->not->toBeNull()
        ->and($killed->duration_ms)->toBeNull();

    $retry = JobMonitor::where('job_id', $jobId)->where('attempt', 2)->first();
    expect($retry)->not->toBeNull()
        ->and($retry->status)->toBe(JobStatus::PROCESSING)
        ->and($retry->retried_from_id)->toBe($killed->id)
        ->and($retry->connection)->toBe($connection);
})->with('redelivering drivers');

test('repeated hard kills keep one terminal row per attempt', function (string $connection, string $jobId) {
    JobMonitor::factory()->processing()->create([
        'job_id' => $jobId,
        'connection' => $connection,
        'attempt' => 1,
        'started_at' => now()->subMinutes(3),
        'exception_class' => null,
    ]);

    $action = app(RecordJobStartedAction::class);
    $action->execute(new JobProcessing($connection, redeliveredJob($jobId, 2)));
    $action->execute(new JobProcessing($connection, redeliveredJob($jobId, 3)));

    $rows = JobMonitor::where('job_id', $jobId)->orderBy('attempt')->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('attempt')->all())->toBe([1, 2, 3])
        ->and($rows[0]->status)->toBe(JobStatus::TIMEOUT)
        ->and($rows[1]->status)->toBe(JobStatus::TIMEOUT)
        ->and($rows[2]->status)->toBe(JobStatus::PROCESSING)
        ->and($rows[2]->retried_from_id)->toBe($rows[1]->id);
})->with('redelivering drivers');

test('a release followed by a redelivery still reuses one row end to end', function (string $connection, string $jobId) {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => $jobId,
        'connection' => $connection,
        'attempt' => 1,
        'started_at' => now()->subSeconds(5),
        'exception_class' => null,
    ]);

    app(RecordJobCompletedAction::class)->execute(new JobProcessed($connection, redeliveredJob($jobId, 1, released: true)));
    app(RecordJobStartedAction::class)->execute(new JobProcessing($connection, redeliveredJob($jobId, 2)));

    expect(JobMonitor::count())->toBe(1);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING)
        ->and($monitor->attempt)->toBe(2)
        ->and($monitor->completed_at)->toBeNull();
})->with('redelivering drivers');
