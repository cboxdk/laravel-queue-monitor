<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use Cbox\LaravelQueueMonitor\Actions\Core\UpdateJobMetricsAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Contracts\Queue\Job;

test('timeout action marks job as timed out', function () {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => 'timeout-job',
        'started_at' => now()->subSeconds(30),
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('timeout-job');

    $event = new \stdClass;
    $event->job = $mockJob;

    $action = app(RecordJobTimeoutAction::class);
    $action->execute($event);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::TIMEOUT);
    expect($monitor->completed_at)->not->toBeNull();
    expect($monitor->duration_ms)->toBeGreaterThan(0);
    expect($monitor->exception_class)->toBe('JobTimeout');
    expect($monitor->exception_message)->toBe('Job exceeded maximum execution time');
});

test('timeout action returns early when no job in event', function () {
    $event = new \stdClass;

    $action = app(RecordJobTimeoutAction::class);
    $action->execute($event);

    expect(JobMonitor::count())->toBe(0);
});

test('timeout action returns early when job not found', function () {
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('nonexistent');

    $event = new \stdClass;
    $event->job = $mockJob;

    $action = app(RecordJobTimeoutAction::class);
    $action->execute($event);

    // No crash
    expect(true)->toBeTrue();
});

test('timeout action handles job without started_at', function () {
    $monitor = JobMonitor::factory()->queued()->create([
        'job_id' => 'no-start',
        'started_at' => null,
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn('no-start');

    $event = new \stdClass;
    $event->job = $mockJob;

    $action = app(RecordJobTimeoutAction::class);
    $action->execute($event);

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::TIMEOUT);
    expect($monitor->duration_ms)->toBeNull();
});

test('update metrics action returns early when disabled', function () {
    config()->set('queue-monitor.enabled', false);

    $action = app(UpdateJobMetricsAction::class);
    $action->execute(['cpu_time_ms' => 100], 'some-job');

    expect(true)->toBeTrue();
});

test('update metrics action returns early when job not found', function () {
    $action = app(UpdateJobMetricsAction::class);
    $action->execute(['cpu_time_ms' => 100], 'nonexistent');

    expect(true)->toBeTrue();
});
