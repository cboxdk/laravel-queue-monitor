<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Tests\Support\ExampleJob;

test('job queued event creates monitor record', function () {
    $job = new ExampleJob;

    // Directly invoke the action to avoid silent failures in listener try/catch
    $event = new JobQueued('redis', 'default', '12345', $job, '{}', null);
    $action = app(RecordJobQueuedAction::class);
    $action->execute($event);

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

    // Directly invoke the action to avoid silent failures in listener try/catch
    $event = new JobProcessing('redis', $mockJob);
    $action = app(RecordJobStartedAction::class);
    $action->execute($event);

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

    // Directly invoke the action to avoid silent failures in listener try/catch
    $event = new JobProcessed('redis', $mockJob);
    $action = app(\PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction::class);
    $action->execute($event);

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

    // Directly invoke the action to avoid silent failures in listener try/catch
    $event = new JobFailed('redis', $mockJob, $exception);
    $action = app(\PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction::class);
    $action->execute($event);

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

    // Directly invoke actions to avoid silent failures in listener try/catch
    $processingEvent = new JobProcessing('redis', $mockJob);
    app(RecordJobStartedAction::class)->execute($processingEvent);
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING);

    // 3. Completed
    $processedEvent = new JobProcessed('redis', $mockJob);
    app(\PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction::class)->execute($processedEvent);
    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::COMPLETED);
    expect($monitor->isFinished())->toBeTrue();
});
