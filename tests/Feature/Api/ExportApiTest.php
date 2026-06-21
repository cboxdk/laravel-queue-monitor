<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;

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

test('export endpoints clamp requested rows to configured maximum', function () {
    config()->set('queue-monitor.export.max_rows', 3);
    config()->set('queue-monitor.export.default_limit', 3);

    JobMonitor::factory()->count(5)->create();

    $csvResponse = $this->get('/api/queue-monitor/export/csv?limit=100');
    $jsonResponse = $this->getJson('/api/queue-monitor/export/json?limit=100');

    $csvResponse->assertOk();
    $jsonResponse->assertOk();

    expect(substr_count($csvResponse->getContent(), "\n"))->toBe(4);
    expect($jsonResponse->json('data'))->toHaveCount(3);
    expect($jsonResponse->json('meta.count'))->toBe(3);
});
