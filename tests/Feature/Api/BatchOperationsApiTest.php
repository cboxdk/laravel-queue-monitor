<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

test('batch replay by uuids replays failed jobs', function () {
    Queue::fake();

    $jobs = JobMonitor::factory()->count(3)->failed()->create();
    $uuids = $jobs->pluck('uuid')->toArray();

    $response = postJson('/api/queue-monitor/batch/replay', [
        'uuids' => $uuids,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Batch replay completed',
            'success' => 3,
            'failed' => 0,
        ]);
});

test('batch replay with filters replays matching jobs', function () {
    Queue::fake();

    JobMonitor::factory()->count(2)->failed()->create(['queue' => 'emails']);
    JobMonitor::factory()->count(3)->create(['status' => JobStatus::COMPLETED]);

    $response = postJson('/api/queue-monitor/batch/replay', [
        'filters' => [
            'statuses' => ['failed'],
            'queues' => ['emails'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBe(2);
});

test('batch delete by uuids deletes jobs', function () {
    $jobs = JobMonitor::factory()->count(3)->create();
    $uuids = $jobs->pluck('uuid')->toArray();

    $response = postJson('/api/queue-monitor/batch/delete', [
        'uuids' => $uuids,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Batch delete completed',
            'deleted' => 3,
            'failed' => 0,
        ]);

    expect(JobMonitor::count())->toBe(0);
});

test('batch delete with filters deletes matching jobs', function () {
    JobMonitor::factory()->count(5)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(2)->failed()->create();

    $response = postJson('/api/queue-monitor/batch/delete', [
        'filters' => [
            'statuses' => ['completed'],
        ],
    ]);

    $response->assertOk();
    expect($response->json('deleted'))->toBe(5);
    expect(JobMonitor::count())->toBe(2);
});

test('batch replay validates uuid format', function () {
    $response = postJson('/api/queue-monitor/batch/replay', [
        'uuids' => ['not-a-uuid'],
    ]);

    $response->assertStatus(422);
});

test('batch delete validates uuid format', function () {
    $response = postJson('/api/queue-monitor/batch/delete', [
        'uuids' => ['not-a-uuid'],
    ]);

    $response->assertStatus(422);
});
