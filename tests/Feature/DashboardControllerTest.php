<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;

use function Pest\Laravel\getJson;

test('overview metrics endpoint returns stats, queues, recent jobs', function () {
    JobMonitor::factory()->count(5)->create();
    JobMonitor::factory()->failed()->count(2)->create();

    $response = getJson(route('queue-monitor.dashboard.metrics'));

    $response->assertOk();
    $response->assertJsonStructure([
        'stats' => ['total', 'success_rate', 'failed', 'avg_duration_ms'],
        'queues',
        'alerts',
        'recent_jobs',
        'charts',
    ]);
});

test('jobs endpoint returns paginated filtered jobs', function () {
    JobMonitor::factory()->count(10)->create();
    JobMonitor::factory()->failed()->count(3)->create();

    $response = getJson(route('queue-monitor.dashboard.jobs', [
        'statuses' => ['failed', 'timeout'],
        'limit' => 5,
    ]));

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'meta' => ['total', 'limit', 'offset'],
    ]);
    expect($response->json('meta.total'))->toBe(3);
});

test('jobs endpoint supports search', function () {
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\SendEmail']);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\ProcessPayment']);

    $response = getJson(route('queue-monitor.dashboard.jobs', ['search' => 'SendEmail']));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('job detail endpoint returns full job with payload and retry chain', function () {
    config(['queue-monitor.api.sensitive_keys' => ['password']]);

    $job = JobMonitor::factory()->failed()->create([
        'payload' => ['user_id' => 1, 'password' => 'secret'],
    ]);

    $response = getJson(route('queue-monitor.dashboard.job.detail', $job->uuid));

    $response->assertOk();
    $response->assertJsonStructure([
        'job' => ['uuid', 'job_class', 'status', 'metrics', 'timestamps'],
        'payload',
        'exception',
        'retry_chain',
    ]);
    // Verify payload is redacted
    expect($response->json('payload.password'))->toBe('*****');
});

test('job detail returns 404 for unknown uuid', function () {
    $response = getJson(route('queue-monitor.dashboard.job.detail', 'nonexistent'));

    $response->assertNotFound();
});

test('analytics endpoint returns distribution and breakdowns', function () {
    JobMonitor::factory()->count(5)->create();

    $response = getJson(route('queue-monitor.dashboard.analytics'));

    $response->assertOk();
    $response->assertJsonStructure([
        'job_classes',
        'queues',
        'servers',
        'failure_patterns',
        'tags',
    ]);
});

test('health endpoint returns checks and score', function () {
    JobMonitor::factory()->count(3)->create();

    $response = getJson(route('queue-monitor.dashboard.health'));

    $response->assertOk();
    $response->assertJsonStructure([
        'score',
        'status',
        'checks',
        'alerts',
    ]);
});

test('payload endpoint returns redacted payload', function () {
    config(['queue-monitor.api.sensitive_keys' => ['password']]);

    $job = JobMonitor::factory()->create([
        'payload' => ['user_id' => 1, 'password' => 'secret123'],
    ]);

    $response = getJson(route('queue-monitor.job.payload', $job->uuid));

    $response->assertOk();
    expect($response->json('payload.user_id'))->toBe(1);
    expect($response->json('payload.password'))->toBe('*****');
});

test('payload endpoint returns empty for job without payload', function () {
    $job = JobMonitor::factory()->create(['payload' => null]);

    $response = getJson(route('queue-monitor.job.payload', $job->uuid));

    $response->assertOk();
    expect($response->json('payload'))->toBe([]);
});

test('metrics endpoint includes throughput data', function () {
    JobMonitor::factory()->count(5)->create(['queued_at' => now()->subMinutes(5)]);

    $response = getJson(route('queue-monitor.dashboard.metrics'));
    $response->assertOk();
    $response->assertJsonStructure(['charts' => ['throughput', 'distribution']]);
});

test('drill-down endpoint returns queue detail', function () {
    JobMonitor::factory()->count(5)->create(['queue' => 'payments']);
    JobMonitor::factory()->failed()->count(2)->create(['queue' => 'payments']);

    $response = getJson(route('queue-monitor.dashboard.drill-down', ['type' => 'queue', 'value' => 'payments']));
    $response->assertOk();
    $response->assertJsonStructure([
        'entity' => ['type', 'value'],
        'stats' => ['total', 'completed', 'failed', 'success_rate', 'avg_duration_ms'],
        'throughput',
        'recent_jobs',
        'failure_patterns',
    ]);
    expect($response->json('stats.total'))->toBe(7);
});

test('drill-down endpoint returns server detail', function () {
    JobMonitor::factory()->count(3)->create(['server_name' => 'prod-01']);

    $response = getJson(route('queue-monitor.dashboard.drill-down', ['type' => 'server', 'value' => 'prod-01']));
    $response->assertOk();
    expect($response->json('entity.type'))->toBe('server');
    expect($response->json('stats.total'))->toBe(3);
});

test('drill-down endpoint returns job_class detail', function () {
    JobMonitor::factory()->count(4)->create(['job_class' => 'App\\Jobs\\TestJob']);

    $response = getJson(route('queue-monitor.dashboard.drill-down', ['type' => 'job_class', 'value' => 'App\\Jobs\\TestJob']));
    $response->assertOk();
    expect($response->json('stats.total'))->toBe(4);
});

test('drill-down endpoint validates type parameter', function () {
    $response = getJson(route('queue-monitor.dashboard.drill-down', ['type' => 'invalid', 'value' => 'x']));
    $response->assertStatus(422);
});

test('infrastructure endpoint returns worker utilization, capacity, and SLA data', function () {
    JobMonitor::factory()->count(5)->create([
        'queue' => 'default',
        'started_at' => now(),
        'queued_at' => now(),
        'duration_ms' => 500,
    ]);
    JobMonitor::factory()->count(3)->create([
        'queue' => 'emails',
        'started_at' => now(),
        'queued_at' => now(),
        'duration_ms' => 1200,
    ]);

    $response = getJson(route('queue-monitor.dashboard.infrastructure'));

    $response->assertOk();
    $response->assertJsonStructure([
        'workers' => ['available'],
        'queues' => ['available'],
        'sla' => ['available', 'per_queue', 'source'],
        'scaling' => ['utilization' => ['percentage', 'total_processing_ms', 'busy_workers', 'total_workers', 'window_seconds', 'status']],
        'capacity' => ['queues'],
    ]);
    // Workers and queues should be unavailable since Horizon/queue-metrics are not installed
    expect($response->json('workers.available'))->toBeFalse();
    expect($response->json('queues.available'))->toBeFalse();
    // SLA should be available since it uses our own data
    expect($response->json('sla.available'))->toBeTrue();
    // Capacity queues should have data
    expect($response->json('capacity.queues'))->toBeArray();
});

test('infrastructure SLA compliance calculates correctly', function () {
    // Create jobs on same queue with different pickup times
    JobMonitor::factory()->count(3)->create([
        'queue' => 'payments',
        'queued_at' => now()->subSeconds(10),
        'available_at' => now()->subSeconds(10),
        'started_at' => now()->subSeconds(8), // 2s pickup time — within 30s default
        'duration_ms' => 100,
    ]);
    JobMonitor::factory()->count(2)->create([
        'queue' => 'payments',
        'queued_at' => now()->subMinutes(2),
        'available_at' => now()->subMinutes(2),
        'started_at' => now()->subMinutes(2)->addSeconds(45), // 45s pickup — exceeds 30s default
        'duration_ms' => 100,
    ]);

    $response = getJson(route('queue-monitor.dashboard.infrastructure'));

    $response->assertOk();

    $perQueue = collect($response->json('sla.per_queue'));
    $payments = $perQueue->firstWhere('queue', 'payments');
    expect($payments)->not->toBeNull();
    expect($payments['total'])->toBe(5);
    // Default SLA target is 30s — 3 within, 2 breached
    expect($payments['within'])->toBe(3);
    expect($payments['breached'])->toBe(2);
});

test('infrastructure endpoint works with no job data', function () {
    $response = getJson(route('queue-monitor.dashboard.infrastructure'));

    $response->assertOk();
    expect($response->json('sla.available'))->toBeTrue();
    expect($response->json('scaling.utilization.percentage'))->toEqual(0);
    expect($response->json('capacity.queues'))->toBe([]);
});

test('infrastructure endpoint includes scaling history when table exists', function () {
    // The scaling_events table is already created by the package migration

    // Insert a scaling event
    ScalingEvent::create([
        'connection' => 'redis',
        'queue' => 'default',
        'action' => 'scale_up',
        'current_workers' => 2,
        'target_workers' => 5,
        'reason' => 'High queue depth',
        'sla_target' => 30,
        'sla_breach_risk' => true,
    ]);

    $response = getJson(route('queue-monitor.dashboard.infrastructure'));
    $response->assertOk();

    expect($response->json('scaling.has_autoscale'))->toBeTrue();
    expect($response->json('scaling.history'))->toHaveCount(1);
    expect($response->json('scaling.history.0.action'))->toBe('scale_up');
    expect($response->json('scaling.summary.scale_ups'))->toBe(1);
});
