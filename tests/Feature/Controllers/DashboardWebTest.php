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

test('dashboard serves bundled assets from the package route by default', function () {
    View::addNamespace('queue-monitor-source', dirname(__DIR__, 3).'/resources/views');

    $html = view('queue-monitor-source::web.dashboard')->render();

    // Default 'served' mode: external tags at the package asset route with a
    // content-hash version, not the ~1 MB chart library inlined per response.
    expect($html)
        ->toContain('/queue-monitor/assets/echarts.min.js?v=')
        ->toContain('/queue-monitor/assets/alpine.min.js?v=')
        ->toContain('/queue-monitor/assets/queue-monitor.css?v=')
        ->not->toContain('<style>@charset');
});

test('the asset route streams a bundled file with an immutable cache and 404s unknown files', function () {
    $response = get('/queue-monitor/assets/alpine.min.js');

    $response->assertOk();
    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('max-age=31536000')
        ->and($cacheControl)->toContain('public')
        ->and($cacheControl)->toContain('immutable');
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->headers->get('ETag'))->not->toBeNull();

    get('/queue-monitor/assets/secrets.env')->assertNotFound();
    get('/queue-monitor/assets/queue-monitor.css')->assertOk();
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

test('the autoscale tab carries the fuse and leadership panels', function () {
    // These render from data the listener writes, so a template dropped in a
    // refactor would silently take the only on-screen explanation for a held
    // queue with it.
    View::addNamespace('queue-monitor-source', dirname(__DIR__, 3).'/resources/views');

    $html = view('queue-monitor-source::web.dashboard')->render();

    expect($html)
        ->toContain('Failure Fuse Holding')
        ->toContain('autoscale.scaling?.open_fuses')
        ->toContain('autoscale.cluster?.leadership?.unstable')
        ->toContain('leadership changes in')
        ->toContain('summary?.fuse_trips');
});
