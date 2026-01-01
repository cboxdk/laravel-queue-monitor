<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Listeners\JobProcessingListener;
use PHPeek\LaravelQueueMonitor\Listeners\JobQueuedListener;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Tests\Support\ExampleJob;

test('job queued event creates monitor record', function () {
    $job = new ExampleJob;

    // Directly invoke the listener to avoid event dispatch timing issues in CI
    $event = new JobQueued('redis', 'default', '12345', $job, '{}', null);
    $listener = app(JobQueuedListener::class);
    $listener->handle($event);

    expect(JobMonitor::count())->toBe(1);

    $monitor = JobMonitor::first();
    expect($monitor->status)->toBe(JobStatus::QUEUED);
    expect($monitor->job_class)->toContain('ExampleJob');
});

test('job processing event updates status', function () {
    $monitor = JobMonitor::factory()->queued()->create([
        'job_id' => '12345',
    ]);

    $mockJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('12345');
    $mockJob->shouldReceive('attempts')->andReturn(1);
    $mockJob->shouldReceive('payload')->andReturn([]);

    // Directly invoke the listener to avoid event dispatch timing issues in CI
    $event = new JobProcessing('redis', $mockJob);
    $listener = app(JobProcessingListener::class);
    $listener->handle($event);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING);
    expect($monitor->started_at)->not->toBeNull();
});

test('job processed event marks completion', function () {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => '12345',
        'started_at' => now()->subSeconds(5),
    ]);

    $mockJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('12345');

    // Directly invoke the listener to avoid event dispatch timing issues in CI
    $event = new JobProcessed('redis', $mockJob);
    $listener = app(\PHPeek\LaravelQueueMonitor\Listeners\JobProcessedListener::class);
    $listener->handle($event);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::COMPLETED);
    expect($monitor->completed_at)->not->toBeNull();
    expect($monitor->duration_ms)->toBeGreaterThan(0);
});

test('job failed event captures exception', function () {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => '12345',
    ]);

    $mockJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('12345');

    $exception = new \RuntimeException('Test failure');

    // Directly invoke the listener to avoid event dispatch timing issues in CI
    $event = new JobFailed('redis', $mockJob, $exception);
    $listener = app(\PHPeek\LaravelQueueMonitor\Listeners\JobFailedListener::class);
    $listener->handle($event);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::FAILED);
    expect($monitor->exception_class)->toBe(RuntimeException::class);
    expect($monitor->exception_message)->toBe('Test failure');
    expect($monitor->exception_trace)->not->toBeNull();
});

test('complete job lifecycle is tracked', function () {
    // Use factory to create a properly linked job record
    $monitor = JobMonitor::factory()->queued()->create([
        'job_id' => '12345',
    ]);

    expect($monitor->status)->toBe(JobStatus::QUEUED);

    // 2. Processing
    $mockJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('12345');
    $mockJob->shouldReceive('attempts')->andReturn(1);
    $mockJob->shouldReceive('payload')->andReturn([]);

    // Directly invoke listeners to avoid event dispatch timing issues in CI
    $processingEvent = new JobProcessing('redis', $mockJob);
    app(JobProcessingListener::class)->handle($processingEvent);
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING);

    // 3. Completed
    $processedEvent = new JobProcessed('redis', $mockJob);
    app(\PHPeek\LaravelQueueMonitor\Listeners\JobProcessedListener::class)->handle($processedEvent);
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::COMPLETED);
    expect($monitor->isFinished())->toBeTrue();
});
