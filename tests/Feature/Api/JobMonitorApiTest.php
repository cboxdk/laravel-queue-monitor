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
        'queued_at' => now(),
    ]);
});

test('can list jobs via api', function () {
    $response = $this->getJson('/api/queue-monitor/jobs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'uuid', 'job_class', 'status', 'metrics', 'timestamps'],
            ],
            'meta' => ['total', 'limit', 'offset'],
        ]);
});

test('can get job details via api', function () {
    $response = $this->getJson("/api/queue-monitor/jobs/{$this->job->uuid}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'uuid' => $this->job->uuid,
                'job_class' => 'App\\Jobs\\TestJob',
                'status' => [
                    'value' => 'completed',
                    'label' => 'Completed',
                ],
            ],
        ]);
});

test('can filter jobs by status', function () {
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

    $response = $this->getJson('/api/queue-monitor/jobs?statuses[]=failed');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1);
    expect($data[0]['status']['value'])->toBe('failed');
});

test('can delete job via api', function () {
    $response = $this->deleteJson("/api/queue-monitor/jobs/{$this->job->uuid}");

    $response->assertOk()
        ->assertJson(['message' => 'Job deleted successfully']);

    expect(JobMonitor::find($this->job->id))->toBeNull();
});

test('returns 404 for non-existent job', function () {
    $uuid = Str::uuid()->toString();
    $response = $this->getJson("/api/queue-monitor/jobs/{$uuid}");

    $response->assertNotFound();
});
