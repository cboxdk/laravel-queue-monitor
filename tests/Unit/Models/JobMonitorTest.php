<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

beforeEach(function () {
    $this->job = JobMonitor::create([
        'uuid' => Str::uuid()->toString(),
        'job_class' => 'App\\Jobs\\TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobStatus::COMPLETED,
        'attempt' => 1,
        'max_attempts' => 3,
        'server_name' => 'web-1',
        'worker_id' => 'worker-123',
        'worker_type' => WorkerType::QUEUE_WORK,
        'duration_ms' => 1500,
        'queued_at' => now(),
    ]);
});

test('job monitor model can be created', function () {
    expect($this->job)->toBeInstanceOf(JobMonitor::class);
    expect($this->job->status)->toBe(JobStatus::COMPLETED);
    expect($this->job->worker_type)->toBe(WorkerType::QUEUE_WORK);
});

test('isFinished returns correct value', function () {
    expect($this->job->isFinished())->toBeTrue();

    $this->job->status = JobStatus::PROCESSING;
    expect($this->job->isFinished())->toBeFalse();
});

test('isSuccessful returns true for completed jobs', function () {
    $this->job->status = JobStatus::COMPLETED;
    expect($this->job->isSuccessful())->toBeTrue();

    $this->job->status = JobStatus::FAILED;
    expect($this->job->isSuccessful())->toBeFalse();
});

test('isFailed returns true for failed and timeout', function () {
    $this->job->status = JobStatus::FAILED;
    expect($this->job->isFailed())->toBeTrue();

    $this->job->status = JobStatus::TIMEOUT;
    expect($this->job->isFailed())->toBeTrue();

    $this->job->status = JobStatus::COMPLETED;
    expect($this->job->isFailed())->toBeFalse();
});

test('getDurationInSeconds converts milliseconds correctly', function () {
    $this->job->duration_ms = 3000;

    expect($this->job->getDurationInSeconds())->toBe(3.0);
});

test('scopeWithStatus filters by status', function () {
    JobMonitor::create([
        'uuid' => Str::uuid()->toString(),
        'job_class' => 'App\\Jobs\\TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobStatus::FAILED,
        'attempt' => 1,
        'max_attempts' => 3,
        'server_name' => 'web-1',
        'worker_id' => 'worker-123',
        'worker_type' => WorkerType::QUEUE_WORK,
        'queued_at' => now(),
    ]);

    $failed = JobMonitor::withStatus(JobStatus::FAILED)->get();
    $completed = JobMonitor::withStatus(JobStatus::COMPLETED)->get();

    expect($failed)->toHaveCount(1);
    expect($completed)->toHaveCount(1);
});

test('scopeFailed returns only failed jobs', function () {
    JobMonitor::create([
        'uuid' => Str::uuid()->toString(),
        'job_class' => 'App\\Jobs\\TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobStatus::FAILED,
        'attempt' => 1,
        'max_attempts' => 3,
        'server_name' => 'web-1',
        'worker_id' => 'worker-123',
        'worker_type' => WorkerType::QUEUE_WORK,
        'queued_at' => now(),
    ]);

    $failed = JobMonitor::failed()->get();

    expect($failed)->toHaveCount(1);
    expect($failed->first()->status)->toBe(JobStatus::FAILED);
});

test('getShortExceptionClass returns class name without namespace', function () {
    $this->job->exception_class = 'App\\Exceptions\\CustomException';

    expect($this->job->getShortExceptionClass())->toBe('CustomException');
});
