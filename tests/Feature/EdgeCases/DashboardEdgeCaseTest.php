<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

use function Pest\Laravel\getJson;

// Empty data edge cases
test('overview metrics works with zero jobs', function () {
    $response = getJson(route('queue-monitor.dashboard.metrics'));
    $response->assertOk();
    expect($response->json('stats.total'))->toBe(0);
    expect($response->json('stats.success_rate'))->toBe(0);
    expect($response->json('recent_jobs'))->toBeArray()->toBeEmpty();
});

test('jobs endpoint works with zero jobs', function () {
    $response = getJson(route('queue-monitor.dashboard.jobs'));
    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    expect($response->json('meta.total'))->toBe(0);
});

test('analytics works with zero jobs', function () {
    $response = getJson(route('queue-monitor.dashboard.analytics'));
    $response->assertOk();
});

test('health works with zero jobs', function () {
    $response = getJson(route('queue-monitor.dashboard.health'));
    $response->assertOk();
    expect($response->json('score'))->toBeInt();
});

test('infrastructure works with zero jobs', function () {
    $response = getJson(route('queue-monitor.dashboard.infrastructure'));
    $response->assertOk();
});

// Drill-down edge cases
test('drill-down works with nonexistent queue', function () {
    $response = getJson(route('queue-monitor.dashboard.drill-down', [
        'type' => 'queue',
        'value' => 'nonexistent-queue',
    ]));
    $response->assertOk();
    expect($response->json('stats.total'))->toBe(0);
});

test('drill-down works with special characters in value', function () {
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\Special\\MyJob']);

    $response = getJson(route('queue-monitor.dashboard.drill-down', [
        'type' => 'job_class',
        'value' => 'App\\Jobs\\Special\\MyJob',
    ]));
    $response->assertOk();
    expect($response->json('stats.total'))->toBe(1);
});

test('drill-down rejects invalid type', function () {
    $response = getJson(route('queue-monitor.dashboard.drill-down', [
        'type' => 'invalid',
        'value' => 'test',
    ]));
    $response->assertStatus(422);
});

test('drill-down rejects missing parameters', function () {
    $response = getJson(route('queue-monitor.dashboard.drill-down'));
    $response->assertStatus(422);
});

// Job detail edge cases
test('job detail works for job with null payload', function () {
    $job = JobMonitor::factory()->create(['payload' => null]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));
    $response->assertOk();
    expect($response->json('payload'))->toBeNull();
});

test('job detail works for job with null timestamps', function () {
    $job = JobMonitor::factory()->create([
        'started_at' => null,
        'completed_at' => null,
        'available_at' => null,
    ]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));
    $response->assertOk();
    expect($response->json('job.metrics.wait_time_ms'))->toBeNull();
    expect($response->json('job.metrics.total_time_ms'))->toBeNull();
});

test('job detail returns 404 for nonexistent uuid', function () {
    $response = getJson(route('queue-monitor.dashboard.job.detail', 'does-not-exist'));
    $response->assertNotFound();
});

// Jobs filter edge cases
test('jobs endpoint handles empty search string', function () {
    JobMonitor::factory()->count(3)->create();

    $response = getJson(route('queue-monitor.dashboard.jobs', ['search' => '']));
    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
});

test('jobs endpoint handles pagination beyond total', function () {
    JobMonitor::factory()->count(5)->create();

    $response = getJson(route('queue-monitor.dashboard.jobs', [
        'limit' => 10,
        'offset' => 100,
    ]));
    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    expect($response->json('meta.total'))->toBe(5);
});

test('jobs endpoint handles multiple status filters', function () {
    JobMonitor::factory()->count(3)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(2)->create(['status' => JobStatus::FAILED]);
    JobMonitor::factory()->count(1)->create(['status' => JobStatus::PROCESSING]);

    $response = getJson(route('queue-monitor.dashboard.jobs', [
        'statuses' => ['failed', 'processing'],
    ]));
    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
});

// Deleted/renamed job class
test('job detail works when job class no longer exists', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\DeletedJob',
        'display_name' => 'Deleted Job',
    ]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));
    $response->assertOk();
    expect($response->json('job.job_class'))->toBe('App\\Jobs\\DeletedJob');
});

// Large dataset simulation (bounded — verifies queries don't crash)
test('drill-down handles large result set', function () {
    JobMonitor::factory()->count(50)->create([
        'queue' => 'high-volume',
        'duration_ms' => fn () => random_int(100, 5000),
    ]);

    $response = getJson(route('queue-monitor.dashboard.drill-down', [
        'type' => 'queue',
        'value' => 'high-volume',
    ]));
    $response->assertOk();
    expect($response->json('stats.total'))->toBe(50);
    // Verify percentiles are calculated
    expect($response->json('stats.p50_duration_ms'))->not->toBeNull();
    expect($response->json('stats.p95_duration_ms'))->not->toBeNull();
});

// Available_at backward compatibility
test('timing metrics handle null available_at gracefully', function () {
    $job = JobMonitor::factory()->create([
        'available_at' => null,
        'started_at' => now(),
        'completed_at' => now()->addSeconds(2),
    ]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));
    $response->assertOk();
    expect($response->json('job.metrics.delay_ms'))->toBeNull();
    expect($response->json('job.metrics.pickup_latency_ms'))->toBeNull();
    // wait_time_ms should still work (uses queued_at → started_at)
    expect($response->json('job.metrics.wait_time_ms'))->not->toBeNull();
});

// Retry chain
test('retry chain works with single job (no retries)', function () {
    $job = JobMonitor::factory()->create();

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));
    $response->assertOk();
    expect($response->json('retry_chain'))->toHaveCount(1);
});

test('retry chain works with multiple attempts', function () {
    $original = JobMonitor::factory()->failed()->create([
        'job_id' => 'test-job-123',
        'attempt' => 1,
    ]);
    $retry = JobMonitor::factory()->create([
        'job_id' => 'test-job-123',
        'attempt' => 2,
        'retried_from_id' => $original->id,
    ]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $retry->uuid));
    $response->assertOk();
    expect($response->json('retry_chain'))->toHaveCount(2);
});
