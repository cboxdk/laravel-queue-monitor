<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

use function Pest\Laravel\postJson;

test('prune endpoint deletes old jobs', function () {
    // Create old jobs (older than default retention)
    JobMonitor::factory()->count(5)->create([
        'created_at' => now()->subDays(60),
        'queued_at' => now()->subDays(60),
    ]);
    // Create recent jobs
    JobMonitor::factory()->count(3)->create();

    $response = postJson('/api/queue-monitor/prune', [
        'days' => 30,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'deleted']);

    expect($response->json('deleted'))->toBe(5);
    expect(JobMonitor::count())->toBe(3);
});

test('prune endpoint with status filter only deletes matching statuses', function () {
    JobMonitor::factory()->count(3)->create([
        'status' => JobStatus::COMPLETED,
        'created_at' => now()->subDays(60),
        'queued_at' => now()->subDays(60),
    ]);
    JobMonitor::factory()->count(2)->failed()->create([
        'created_at' => now()->subDays(60),
        'queued_at' => now()->subDays(60),
    ]);

    $response = postJson('/api/queue-monitor/prune', [
        'days' => 30,
        'statuses' => ['completed'],
    ]);

    $response->assertOk();
    expect($response->json('deleted'))->toBe(3);
    expect(JobMonitor::count())->toBe(2);
});

test('prune endpoint uses default retention when no days specified', function () {
    JobMonitor::factory()->count(3)->create([
        'created_at' => now()->subDays(60),
        'queued_at' => now()->subDays(60),
    ]);

    $response = postJson('/api/queue-monitor/prune');

    $response->assertOk();
    $response->assertJsonStructure(['message', 'deleted']);
});
