<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Listeners\JobFailedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobProcessedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobProcessingListener;
use Cbox\LaravelQueueMonitor\Listeners\JobQueuedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobTimedOutListener;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobTimedOut;

test('JobQueuedListener creates monitor record when enabled', function () {
    $event = new JobQueued('redis', 'default', 'queued-1', new ExampleJob, '{}', null);

    $listener = new JobQueuedListener;
    $listener->handle($event);

    expect(JobMonitor::count())->toBe(1);
    expect(JobMonitor::first()->status)->toBe(JobStatus::QUEUED);
});

test('JobProcessingListener updates status to processing', function () {
    JobMonitor::factory()->queued()->create(['job_id' => 'proc-1']);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('proc-1');
    $mockJob->shouldReceive('attempts')->andReturn(1);
    $mockJob->shouldReceive('payload')->andReturn([]);

    $listener = new JobProcessingListener;
    $listener->handle(new JobProcessing('redis', $mockJob));

    expect(JobMonitor::first()->status)->toBe(JobStatus::PROCESSING);
});

test('JobProcessedListener marks job completed', function () {
    JobMonitor::factory()->processing()->create([
        'job_id' => 'done-1',
        'started_at' => now()->subSeconds(2),
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('done-1');

    $listener = new JobProcessedListener;
    $listener->handle(new JobProcessed('redis', $mockJob));

    $job = JobMonitor::first();
    expect($job->status)->toBe(JobStatus::COMPLETED);
    expect($job->completed_at)->not->toBeNull();
});

test('JobFailedListener marks job failed with exception', function () {
    JobMonitor::factory()->processing()->create(['job_id' => 'fail-1']);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('fail-1');

    $listener = new JobFailedListener;
    $listener->handle(new JobFailed('redis', $mockJob, new RuntimeException('boom')));

    $job = JobMonitor::first();
    expect($job->status)->toBe(JobStatus::FAILED);
    expect($job->exception_message)->toBe('boom');
});

test('JobTimedOutListener marks job as timeout', function () {
    JobMonitor::factory()->processing()->create([
        'job_id' => 'timeout-1',
        'started_at' => now()->subSeconds(30),
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('timeout-1');

    $listener = new JobTimedOutListener;
    $listener->handle(new JobTimedOut('redis', $mockJob));

    $job = JobMonitor::first();
    expect($job->status)->toBe(JobStatus::TIMEOUT);
    expect($job->exception_class)->toBe('JobTimeout');
});

test('listeners silently handle exceptions without crashing', function () {
    // Listener with an action that will throw (no job instance in event)
    $brokenEvent = new JobQueued('redis', 'default', null, null, '{}', null);

    $listener = new JobQueuedListener;

    // Should not throw — listener catches all exceptions
    $listener->handle($brokenEvent);

    expect(true)->toBeTrue();
});
