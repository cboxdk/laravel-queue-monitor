<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Http\Transformers\JobMonitorTransformer;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

test('toListArray returns expected structure', function () {
    $job = JobMonitor::factory()->create([
        'duration_ms' => 1500,
        'cpu_time_ms' => 200,
        'memory_peak_mb' => 32.5,
    ]);

    $result = JobMonitorTransformer::toListArray($job);

    expect($result)->toHaveKeys([
        'uuid', 'job_class', 'full_job_class', 'queue', 'connection',
        'status', 'worker_type', 'attempt', 'max_attempts', 'server',
        'duration_ms', 'duration', 'cpu_time_ms', 'memory_peak_mb',
        'worker_memory_limit_mb', 'queued_at', 'started_at', 'completed_at',
        'error', 'is_failed',
    ]);

    expect($result['uuid'])->toBe($job->uuid);
    expect($result['status'])->toBeArray()->toHaveKeys(['value', 'label', 'color']);
    expect($result['worker_type'])->toBeArray()->toHaveKeys(['value', 'label', 'icon']);
    expect($result['duration'])->toBe('1,500ms');
    expect($result['duration_ms'])->toBe(1500);
});

test('toListArray formats duration as dash when null', function () {
    $job = JobMonitor::factory()->create(['duration_ms' => null]);

    $result = JobMonitorTransformer::toListArray($job);

    expect($result['duration'])->toBe('-');
});

test('toDetailArray returns nested metrics and timestamps', function () {
    $job = JobMonitor::factory()->create([
        'duration_ms' => 500,
        'started_at' => now()->subSeconds(2),
        'completed_at' => now(),
        'available_at' => now()->subSeconds(3),
    ]);

    $result = JobMonitorTransformer::toDetailArray($job);

    expect($result)->toHaveKeys([
        'uuid', 'job_class', 'short_job_class', 'display_name',
        'queue', 'connection', 'status', 'worker_type',
        'attempt', 'max_attempts', 'server', 'worker_id',
        'metrics', 'timestamps', 'tags', 'is_failed', 'is_retryable',
    ]);

    expect($result['metrics'])->toHaveKeys([
        'duration_ms', 'cpu_time_ms', 'memory_peak_mb',
        'worker_memory_limit_mb', 'file_descriptors',
        'wait_time_ms', 'total_time_ms', 'delay_ms', 'pickup_latency_ms',
    ]);

    expect($result['timestamps'])->toHaveKeys([
        'queued_at', 'available_at', 'started_at', 'completed_at',
    ]);
});

test('toDetailArray handles null timestamps gracefully', function () {
    $job = JobMonitor::factory()->create([
        'started_at' => null,
        'completed_at' => null,
        'available_at' => null,
    ]);

    $result = JobMonitorTransformer::toDetailArray($job);

    expect($result['metrics']['wait_time_ms'])->toBeNull();
    expect($result['metrics']['total_time_ms'])->toBeNull();
    expect($result['metrics']['delay_ms'])->toBeNull();
    expect($result['metrics']['pickup_latency_ms'])->toBeNull();
    expect($result['timestamps']['started_at'])->toBeNull();
    expect($result['timestamps']['completed_at'])->toBeNull();
    expect($result['timestamps']['available_at'])->toBeNull();
});

test('toRetryChainArray marks current job correctly', function () {
    $job = JobMonitor::factory()->create();
    $otherUuid = 'other-uuid';

    $resultCurrent = JobMonitorTransformer::toRetryChainArray($job, $job->uuid);
    $resultNotCurrent = JobMonitorTransformer::toRetryChainArray($job, $otherUuid);

    expect($resultCurrent['is_current'])->toBeTrue();
    expect($resultNotCurrent['is_current'])->toBeFalse();
});

test('toRetryChainArray includes exception data', function () {
    $job = JobMonitor::factory()->failed()->create([
        'exception_class' => 'RuntimeException',
        'exception_message' => 'Something went wrong',
        'exception_trace' => '#0 /app/test.php(10): doStuff()',
    ]);

    $result = JobMonitorTransformer::toRetryChainArray($job, $job->uuid);

    expect($result)->toHaveKeys([
        'uuid', 'attempt', 'status', 'duration_ms', 'memory_peak_mb',
        'server_name', 'worker_id', 'started_at', 'completed_at',
        'exception_class', 'exception_message', 'exception_trace',
        'wait_time_ms', 'is_current',
    ]);
    expect($result['exception_class'])->toBe('RuntimeException');
    expect($result['exception_message'])->toBe('Something went wrong');
});

test('toListArray reflects failed status correctly', function () {
    $failed = JobMonitor::factory()->failed()->create();
    $completed = JobMonitor::factory()->create(['status' => JobStatus::COMPLETED]);

    expect(JobMonitorTransformer::toListArray($failed)['is_failed'])->toBeTrue();
    expect(JobMonitorTransformer::toListArray($completed)['is_failed'])->toBeFalse();
});
