<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

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
        ->expectsOutputToContain('"checks"')
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
