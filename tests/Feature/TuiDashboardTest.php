<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Feature;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Artisan;
use function Termwind\render;

it('renders the TUI dashboard without errors', function () {
    // Create some dummy data
    JobMonitor::factory()->count(3)->create();

    // Execute the command with --once
    $exitCode = Artisan::call('queue-monitor:dashboard', ['--once' => true]);

    expect($exitCode)->toBe(0);
});

it('renders the TUI view directly to catch style errors', function () {
    $stats = [
        'total_jobs' => 10,
        'success_rate' => 90,
        'avg_duration_ms' => 150,
    ];
    
    $queues = [
        ['queue' => 'default', 'status' => 'healthy', 'jobs_per_minute' => 5]
    ];
    
    $recentJobs = JobMonitor::factory()->count(2)->make();
    $failedJobs = JobMonitor::factory()->count(1)->make();

    // This will throw Termwind\Exceptions\StyleNotFound if any class is invalid
    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => $queues,
        'recentJobs' => $recentJobs,
        'failedJobs' => $failedJobs,
        'timestamp' => now()->format('H:i:s'),
    ])->render();

    // Verify it can be rendered by Termwind
    render($html);
    
    expect(true)->toBeTrue();
});

it('can publish migrations and config', function () {
    // Clean up any previously published files in the test environment if necessary
    
    $exitCode = Artisan::call('vendor:publish', [
        '--provider' => 'Cbox\LaravelQueueMonitor\LaravelQueueMonitorServiceProvider',
    ]);

    expect($exitCode)->toBe(0);
});

