<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;

use function Pest\Laravel\get;

beforeEach(function () {
    config()->set('queue-monitor.ui.enabled', true);
});

test('dashboard index renders view', function () {
    $response = get(route('queue-monitor.dashboard'));

    $response->assertOk();
    $response->assertViewIs('queue-monitor::web.dashboard');
});

test('dashboard renders package-local assets without external CDN dependencies', function () {
    View::addNamespace('queue-monitor-source', dirname(__DIR__, 3).'/resources/views');

    $html = view('queue-monitor-source::web.dashboard')->render();

    expect($html)
        ->not->toContain('https://cdn.tailwindcss.com')
        ->not->toContain('https://cdn.jsdelivr.net')
        ->not->toContain('https://fonts.googleapis.com')
        ->toContain('Alpine')
        ->toContain('echarts');
});

test('dashboard source does not include ai tool attribution text', function () {
    $source = file_get_contents(dirname(__DIR__, 3).'/resources/views/web/dashboard.blade.php');
    $blockedTerms = [
        '['.'co'.'dex]',
        'Co'.'dex',
        'Clau'.'de',
        'Open'.'AI',
        'Generated'.' with',
        'Co-authored'.'-by',
    ];

    foreach ($blockedTerms as $term) {
        expect($source)->not->toContain($term);
    }
});

test('dashboard can render published public asset tags', function () {
    config()->set('queue-monitor.ui.assets.mode', 'public');
    config()->set('queue-monitor.ui.assets.url', '/assets/queue-monitor');
    View::addNamespace('queue-monitor-source', dirname(__DIR__, 3).'/resources/views');

    $html = view('queue-monitor-source::web.dashboard')->render();

    expect($html)
        ->toContain('<link rel="stylesheet" href="/assets/queue-monitor/queue-monitor.css">')
        ->toContain('<script src="/assets/queue-monitor/echarts.min.js"></script>')
        ->toContain('<script src="/assets/queue-monitor/alpine.min.js"></script>')
        ->not->toContain('https://cdn.tailwindcss.com')
        ->not->toContain('https://cdn.jsdelivr.net')
        ->not->toContain('https://fonts.googleapis.com');
});

test('dashboard can disable package asset rendering for custom builds', function () {
    config()->set('queue-monitor.ui.assets.mode', 'none');
    View::addNamespace('queue-monitor-source', dirname(__DIR__, 3).'/resources/views');

    $html = view('queue-monitor-source::web.dashboard')->render();

    expect($html)
        ->not->toContain('queue-monitor.css')
        ->not->toContain('echarts.min.js')
        ->not->toContain('alpine.min.js');
});

test('dashboard assets can be published', function () {
    $exitCode = Artisan::call('vendor:publish', [
        '--tag' => 'queue-monitor-assets',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(public_path('vendor/queue-monitor/queue-monitor.css'))->toBeFile();
    expect(public_path('vendor/queue-monitor/alpine.min.js'))->toBeFile();
    expect(public_path('vendor/queue-monitor/echarts.min.js'))->toBeFile();
});

test('dashboard asset sources can be published for app-level builds', function () {
    $exitCode = Artisan::call('vendor:publish', [
        '--tag' => 'queue-monitor-asset-sources',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(resource_path('vendor/queue-monitor/queue-monitor.css'))->toBeFile();
    expect(resource_path('vendor/queue-monitor/tailwind.config.js'))->toBeFile();
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
