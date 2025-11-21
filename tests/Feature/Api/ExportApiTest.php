<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('export csv endpoint returns CSV content', function () {
    JobMonitor::factory()->count(3)->create();

    $response = $this->get('/api/queue-monitor/export/csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('export json endpoint returns JSON data', function () {
    JobMonitor::factory()->count(2)->create();

    $response = $this->getJson('/api/queue-monitor/export/json');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['uuid', 'job_class', 'status', 'metrics'],
            ],
            'meta' => ['count', 'exported_at'],
        ]);

    expect($response->json('data'))->toHaveCount(2);
});

test('export statistics endpoint returns report', function () {
    JobMonitor::factory()->count(5)->create();

    $response = $this->getJson('/api/queue-monitor/export/statistics');

    $response->assertOk()
        ->assertJsonStructure([
            'generated_at',
            'global',
            'servers',
            'queue_health',
        ]);
});

test('export failed jobs endpoint returns failure report', function () {
    JobMonitor::factory()->count(2)->failed()->create();

    $response = $this->getJson('/api/queue-monitor/export/failed-jobs');

    $response->assertOk()
        ->assertJsonStructure([
            'generated_at',
            'total_failed',
            'by_exception',
            'by_queue',
            'recent_failures',
        ]);

    expect($response->json('total_failed'))->toBe(2);
});

test('export csv respects filters', function () {
    JobMonitor::factory()->count(5)->create(['queue' => 'emails']);
    JobMonitor::factory()->count(3)->create(['queue' => 'sms']);

    $response = $this->get('/api/queue-monitor/export/csv?queues[]=emails');

    $response->assertOk();
    $content = $response->getContent();

    expect(substr_count($content, "\n"))->toBe(6); // Header + 5 jobs
});
