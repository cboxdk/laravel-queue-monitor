<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Contracts\Queue\Job;
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
    // Prior attempt still processing with no recorded exception = a release
    // (rate limit / middleware / manual), not a failure.
    $original = JobMonitor::factory()->processing()->create([
        'job_id' => 'released-job-1',
        'attempt' => 1,
        'started_at' => now()->subSeconds(5),
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
