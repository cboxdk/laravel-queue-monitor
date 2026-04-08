<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

test('fromArray creates DTO with all fields', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'test-uuid',
        'job_id' => 'job-1',
        'job_class' => 'App\\Jobs\\TestJob',
        'display_name' => 'TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => ['key' => 'value'],
        'status' => 'completed',
        'attempt' => 2,
        'max_attempts' => 5,
        'retried_from_id' => 1,
        'server_name' => 'prod-01',
        'worker_id' => 'w-1',
        'worker_type' => 'horizon',
        'cpu_time_ms' => 150.5,
        'memory_peak_mb' => 64.2,
        'file_descriptors' => 42,
        'duration_ms' => 1500,
        'exception' => ['class' => 'RuntimeException', 'message' => 'fail', 'trace' => '#0'],
        'tags' => ['email', 'urgent'],
        'queued_at' => '2025-01-01 12:00:00',
        'available_at' => '2025-01-01 12:00:05',
        'started_at' => '2025-01-01 12:00:10',
        'completed_at' => '2025-01-01 12:01:00',
        'created_at' => '2025-01-01 12:00:00',
        'updated_at' => '2025-01-01 12:01:00',
    ]);

    expect($data->uuid)->toBe('test-uuid');
    expect($data->jobClass)->toBe('App\\Jobs\\TestJob');
    expect($data->status)->toBe(JobStatus::COMPLETED);
    expect($data->attempt)->toBe(2);
    expect($data->tags)->toBe(['email', 'urgent']);
    expect($data->cpuTimeMs)->toBe(150.5);
    expect($data->exception)->not->toBeNull();
    expect($data->exception->class)->toBe('RuntimeException');
});

test('fromArray handles minimal data with defaults', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'min-uuid',
        'job_class' => 'App\\Jobs\\Simple',
        'connection' => 'sync',
        'queue' => 'default',
    ]);

    expect($data->uuid)->toBe('min-uuid');
    expect($data->status)->toBe(JobStatus::QUEUED);
    expect($data->attempt)->toBe(1);
    expect($data->maxAttempts)->toBe(1);
    expect($data->payload)->toBeNull();
    expect($data->tags)->toBeNull();
    expect($data->exception)->toBeNull();
    expect($data->cpuTimeMs)->toBeNull();
    expect($data->serverName)->toBe('unknown');
});

test('toArray returns all fields', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'arr-uuid',
        'job_class' => 'App\\Jobs\\TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => 'failed',
        'duration_ms' => 2000,
        'queued_at' => '2025-01-01 12:00:00',
    ]);

    $array = $data->toArray();

    expect($array)->toHaveKeys([
        'id', 'uuid', 'job_id', 'job_class', 'display_name',
        'connection', 'queue', 'payload', 'status', 'attempt',
        'max_attempts', 'retried_from_id', 'server_name', 'worker_id',
        'worker_type', 'cpu_time_ms', 'memory_peak_mb', 'file_descriptors',
        'duration_ms', 'exception', 'tags', 'queued_at',
        'available_at', 'started_at', 'completed_at', 'created_at', 'updated_at',
    ]);
    expect($array['uuid'])->toBe('arr-uuid');
    expect($array['status'])->toBe('failed');
});

test('isFinished returns true for completed status', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'status' => 'completed',
    ]);

    expect($data->isFinished())->toBeTrue();
    expect($data->isSuccessful())->toBeTrue();
    expect($data->isFailed())->toBeFalse();
});

test('isFailed returns true for failed status', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'status' => 'failed',
    ]);

    expect($data->isFailed())->toBeTrue();
    expect($data->isSuccessful())->toBeFalse();
});

test('isRetryable when failed with remaining attempts', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'status' => 'failed', 'attempt' => 1, 'max_attempts' => 3,
    ]);

    expect($data->isRetryable())->toBeTrue();
});

test('isRetry when has retried_from_id', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'retried_from_id' => 5,
    ]);

    expect($data->isRetry())->toBeTrue();
});

test('durationInSeconds converts from milliseconds', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'duration_ms' => 2500,
    ]);

    expect($data->durationInSeconds())->toBe(2.5);
});

test('durationInSeconds returns null when no duration', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
    ]);

    expect($data->durationInSeconds())->toBeNull();
});

test('throughput calculates items per second', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'duration_ms' => 500,
    ]);

    expect($data->throughput())->toBe(2.0);
});

test('throughput returns null when duration is zero', function () {
    $data = JobMonitorData::fromArray([
        'uuid' => 'u', 'job_class' => 'J', 'connection' => 'c', 'queue' => 'q',
        'duration_ms' => 0,
    ]);

    expect($data->throughput())->toBeNull();
});
