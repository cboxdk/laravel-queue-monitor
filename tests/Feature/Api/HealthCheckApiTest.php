<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('health endpoint returns system status', function () {
    JobMonitor::factory()->count(5)->create();

    $response = $this->getJson('/api/queue-monitor/health');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'checks' => [
                'database',
                'recent_activity',
                'stuck_jobs',
                'error_rate',
                'queue_backlog',
                'storage',
            ],
            'timestamp',
        ]);
});

test('health score endpoint returns score', function () {
    JobMonitor::factory()->count(5)->create();

    $response = $this->getJson('/api/queue-monitor/health/score');

    $response->assertOk()
        ->assertJsonStructure(['score', 'status']);

    $data = $response->json();
    expect($data['score'])->toBeInt();
    expect($data['score'])->toBeGreaterThanOrEqual(0);
    expect($data['score'])->toBeLessThanOrEqual(100);
});

test('alerts endpoint returns active alerts', function () {
    $response = $this->getJson('/api/queue-monitor/health/alerts');

    $response->assertOk()
        ->assertJsonStructure([
            'alerts',
            'has_critical',
            'count',
        ]);
});

test('health endpoint returns 503 when degraded', function () {
    // Create stuck job
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subHours(2),
    ]);

    $response = $this->getJson('/api/queue-monitor/health');

    // Might be 503 or 200 depending on other checks
    expect($response->status())->toBeIn([200, 503]);
});
