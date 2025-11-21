<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Events\JobMonitorRecorded;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('job queued event creates monitor record', function () {
    Event::fake([JobMonitorRecorded::class]);

    $job = new \Tests\Support\ExampleJob;

    event(new JobQueued('redis', $job));

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

    event(new JobProcessing('redis', $mockJob));

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

    event(new JobProcessed('redis', $mockJob));

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

    event(new JobFailed('redis', $mockJob, $exception));

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::FAILED);
    expect($monitor->exception_class)->toBe(RuntimeException::class);
    expect($monitor->exception_message)->toBe('Test failure');
    expect($monitor->exception_trace)->not->toBeNull();
});

test('complete job lifecycle is tracked', function () {
    $job = new \Tests\Support\ExampleJob;

    // 1. Queued
    event(new JobQueued('redis', $job));
    $monitor = JobMonitor::first();
    expect($monitor->status)->toBe(JobStatus::QUEUED);

    // 2. Processing
    $mockJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn($monitor->job_id ?? '999');
    $mockJob->shouldReceive('attempts')->andReturn(1);

    event(new JobProcessing('redis', $mockJob));
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING);

    // 3. Completed
    event(new JobProcessed('redis', $mockJob));
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::COMPLETED);
    expect($monitor->isFinished())->toBeTrue();
});
