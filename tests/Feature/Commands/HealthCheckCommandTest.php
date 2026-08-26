<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Artisan;

test('health check command displays status', function () {
    JobMonitor::factory()->count(5)->create();

    $this->artisan('queue-monitor:health')
        ->expectsOutputToContain('System Status')
        ->assertSuccessful();
});

test('health check command shows JSON output', function () {
    JobMonitor::factory()->count(3)->create();

    $this->artisan('queue-monitor:health --json')
        ->expectsOutputToContain('"status"')
        ->assertSuccessful();
});

test('health check command shows alerts only', function () {
    $this->artisan('queue-monitor:health --alerts')
        ->expectsOutputToContain('alerts')
        ->assertSuccessful();
});

test('health check command fails when system degraded', function () {
    // Create stuck job
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subHours(2),
    ]);

    // Command should fail (return 1) when degraded
    $this->artisan('queue-monitor:health')
        ->assertFailed();
});

test('health check command shows readiness output', function () {
    app()->detectEnvironment(fn (): string => 'local');
    LaravelQueueMonitor::auth(fn (): bool => true);

    config()->set('queue-monitor.storage.store_payload', false);

    $this->artisan('queue-monitor:health --readiness')
        ->expectsOutputToContain('Production Readiness')
        ->assertSuccessful();
});

test('health check command readiness fails for unsafe production configuration', function () {
    app()->detectEnvironment(fn (): string => 'production');
    LaravelQueueMonitor::$authUsing = null;

    config()->set('queue-monitor.api.enabled', true);
    config()->set('queue-monitor.api.middleware', ['api']);
    config()->set('queue-monitor.storage.store_payload', true);

    $this->artisan('queue-monitor:health --readiness')
        ->expectsOutputToContain('Production Readiness')
        ->assertFailed();
});

test('health check command readiness supports json output', function () {
    app()->detectEnvironment(fn (): string => 'local');
    LaravelQueueMonitor::auth(fn (): bool => true);

    $exitCode = Artisan::call('queue-monitor:health', ['--readiness' => true, '--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('"status"');
    expect($output)->toContain('"access_control"');
});

test('health check readiness --json exits non-zero when not ready', function () {
    app()->detectEnvironment(fn (): string => 'production');
    LaravelQueueMonitor::$authUsing = null;
    config()->set('queue-monitor.api.enabled', true);
    config()->set('queue-monitor.api.middleware', ['api']);
    config()->set('queue-monitor.storage.store_payload', true);

    // The JSON path is exactly what a CI/deploy gate scripts, so it must carry
    // the failing exit code, not a blanket success.
    $exitCode = Artisan::call('queue-monitor:health', ['--readiness' => true, '--json' => true]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('"status"');
});
