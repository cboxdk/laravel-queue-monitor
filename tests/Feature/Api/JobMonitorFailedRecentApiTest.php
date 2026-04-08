<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

use function Pest\Laravel\getJson;

test('failed endpoint returns only failed jobs', function () {
    JobMonitor::factory()->count(3)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(2)->failed()->create();

    $response = getJson('/api/queue-monitor/jobs/failed');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('recent endpoint returns jobs', function () {
    JobMonitor::factory()->count(5)->create();

    $response = getJson('/api/queue-monitor/jobs/recent');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(5);
});

test('failed endpoint respects limit parameter', function () {
    JobMonitor::factory()->count(10)->failed()->create();

    $response = getJson('/api/queue-monitor/jobs/failed?limit=3');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('recent endpoint respects limit parameter', function () {
    JobMonitor::factory()->count(10)->create();

    $response = getJson('/api/queue-monitor/jobs/recent?limit=4');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(4);
});

test('failed endpoint clamps limit to maximum of 1000', function () {
    JobMonitor::factory()->count(3)->failed()->create();

    // Absurdly high limit should not cause issues — clamped to 1000
    $response = getJson('/api/queue-monitor/jobs/failed?limit=999999');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('recent endpoint clamps limit to minimum of 1', function () {
    JobMonitor::factory()->count(3)->create();

    $response = getJson('/api/queue-monitor/jobs/recent?limit=0');

    $response->assertOk();
    // limit=0 → clamped to 1, so at least 1 result
    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

test('failed endpoint clamps negative limit to 1', function () {
    JobMonitor::factory()->count(3)->failed()->create();

    $response = getJson('/api/queue-monitor/jobs/failed?limit=-5');

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

test('failed endpoint falls back to default for non-numeric limit', function () {
    JobMonitor::factory()->count(3)->failed()->create();

    $response = getJson('/api/queue-monitor/jobs/failed?limit=abc');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('retry chain endpoint returns chain for a job', function () {
    $job = JobMonitor::factory()->create(['job_id' => 'job-123']);

    $response = getJson("/api/queue-monitor/jobs/{$job->uuid}/retry-chain");

    $response->assertOk();
});
