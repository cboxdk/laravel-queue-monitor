<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

test('can replay a failed job', function () {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create();

    $response = postJson("/api/queue-monitor/jobs/{$job->uuid}/replay");

    $response->assertOk()
        ->assertJson(['message' => 'Job replayed successfully'])
        ->assertJsonStructure(['data']);
});

test('replay returns 422 for non-replayable job', function () {
    $job = JobMonitor::factory()->processing()->create();

    $response = postJson("/api/queue-monitor/jobs/{$job->uuid}/replay");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Failed to replay job']);
});

test('replay returns 422 for non-existent job', function () {
    $response = postJson('/api/queue-monitor/jobs/nonexistent-uuid/replay');

    $response->assertStatus(422);
});
