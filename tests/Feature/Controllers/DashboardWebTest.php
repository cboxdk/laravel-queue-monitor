<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;

use function Pest\Laravel\get;

beforeEach(function () {
    config()->set('queue-monitor.ui.enabled', true);
});

test('dashboard index renders view', function () {
    $response = get(route('queue-monitor.dashboard'));

    $response->assertOk();
    $response->assertViewIs('queue-monitor::web.dashboard');
});

test('dashboard show renders with job uuid', function () {
    $job = JobMonitor::factory()->create();

    $response = get(route('queue-monitor.job.view', $job->uuid));

    $response->assertOk();
    $response->assertViewIs('queue-monitor::web.dashboard');
    $response->assertViewHas('jobUuid', $job->uuid);
});

test('dashboard queue drill-down renders view', function () {
    $response = get(route('queue-monitor.queue.view', 'payments'));

    $response->assertOk();
    $response->assertViewHas('drillDownType', 'queue');
    $response->assertViewHas('drillDownValue', 'payments');
});

test('dashboard server drill-down renders view', function () {
    $response = get(route('queue-monitor.server.view', 'prod-01'));

    $response->assertOk();
    $response->assertViewHas('drillDownType', 'server');
    $response->assertViewHas('drillDownValue', 'prod-01');
});

test('dashboard class drill-down renders view', function () {
    $response = get(route('queue-monitor.class.view', 'App\Jobs\TestJob'));

    $response->assertOk();
    $response->assertViewHas('drillDownType', 'job_class');
});
