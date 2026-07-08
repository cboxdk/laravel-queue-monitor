<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Regression tests for the `database` queue driver.
 *
 * Unlike the redis/sqs drivers, Laravel's DatabaseJob::getJobId() returns the
 * integer auto-increment id rather than a string, even though the Job contract
 * declares getJobId(): string. Every action that reads the job id must tolerate
 * an int, otherwise a TypeError is thrown on every processed job.
 *
 * @see https://github.com/laravel/framework DatabaseJob::getJobId()
 */
test('database driver integer job id records processing without error', function () {
    $monitor = JobMonitor::factory()->queued()->create([
        'job_id' => '12345',
    ]);

    // Database driver returns an integer job id.
    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn(12345);
    $mockJob->shouldReceive('attempts')->andReturn(1);
    $mockJob->shouldReceive('payload')->andReturn([]);

    app(RecordJobStartedAction::class)->execute(new JobProcessing('database', $mockJob));

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::PROCESSING);
    expect($monitor->job_id)->toBe('12345');
});

test('database driver integer job id records completion without error', function () {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => '12345',
        'started_at' => now()->subSeconds(5),
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn(12345);

    app(RecordJobCompletedAction::class)->execute(new JobProcessed('database', $mockJob));

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::COMPLETED);
    expect($monitor->completed_at)->not->toBeNull();
});

test('database driver integer job id records failure without error', function () {
    $monitor = JobMonitor::factory()->processing()->create([
        'job_id' => '12345',
    ]);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('getJobId')->andReturn(12345);

    app(RecordJobFailedAction::class)->execute(
        new JobFailed('database', $mockJob, new RuntimeException('boom'))
    );

    $monitor->refresh();
    expect($monitor->status)->toBe(JobStatus::FAILED);
    expect($monitor->exception_message)->toBe('boom');
});

test('repository normalizes integer job id to string lookup', function () {
    JobMonitor::factory()->processing()->create(['job_id' => '98765']);

    $repository = app(JobMonitorRepositoryContract::class);

    expect($repository->findByJobId(98765))->not->toBeNull()
        ->and($repository->findLatestAttemptByJobId(98765))->not->toBeNull();
});
