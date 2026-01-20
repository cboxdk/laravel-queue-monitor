<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Str;

beforeEach(function () {
    JobMonitor::create([
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
        'duration_ms' => 1000,
        'queued_at' => now(),
        'completed_at' => now(),
    ]);

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
        'duration_ms' => 500,
        'queued_at' => now(),
        'completed_at' => now(),
    ]);
});

test('can get global statistics', function () {
    $response = $this->getJson('/api/queue-monitor/statistics');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total',
                'completed',
                'failed',
                'success_rate',
                'failure_rate',
                'avg_duration_ms',
            ],
        ]);

    $data = $response->json('data');
    expect($data['total'])->toBe(2);
    expect($data['completed'])->toBe(1);
    expect($data['failed'])->toBe(1);
});

test('can get queue health', function () {
    $response = $this->getJson('/api/queue-monitor/statistics/queue-health');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'queue',
                    'total_last_hour',
                    'health_score',
                    'status',
                ],
            ],
        ]);
});

test('statistics show correct success rate', function () {
    $response = $this->getJson('/api/queue-monitor/statistics');

    $data = $response->json('data');
    expect((float) $data['success_rate'])->toEqual(50.0);
    expect((float) $data['failure_rate'])->toEqual(50.0);
});
