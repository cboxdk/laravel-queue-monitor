<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Feature;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

use function Termwind\render;

it('renders the TUI dashboard without errors', function () {
    // Create some dummy data
    JobMonitor::factory()->count(3)->create();

    // Execute the command with --once
    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

it('renders the TUI view directly to catch style errors', function () {
    $stats = [
        'total_jobs' => 10,
        'success_rate' => 90,
        'avg_duration_ms' => 150,
        'failed_jobs' => 1,
        'completed_jobs' => 9,
        'processing' => 0,
        'max_duration_ms' => 500,
        'avg_memory_mb' => 32.5,
        'failure_rate' => 10,
    ];

    $queues = [
        ['queue' => 'default', 'status' => 'healthy', 'total_last_hour' => 10, 'processing' => 0, 'failed' => 1, 'avg_duration_ms' => 150, 'health_score' => 90],
    ];

    $jobs = JobMonitor::factory()->count(2)->create();

    // This will throw Termwind\Exceptions\StyleNotFound if any class is invalid
    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => $queues,
        'jobs' => $jobs,
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => null,
        'searchQuery' => '',
        'inSearchMode' => false,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
    ])->render();

    // Verify it can be rendered by Termwind
    render($html);

    expect(true)->toBeTrue();
});

it('dashboard command runs with --once flag', function () {
    JobMonitor::factory()->count(3)->create();
    JobMonitor::factory()->failed()->create();

    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

it('dashboard command shows job data in once mode', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\TestJob',
        'queue' => 'default',
    ]);

    // Termwind renders directly to STDOUT, so we verify through view rendering
    // that the job class appears in the rendered HTML
    $stats = app(StatisticsRepositoryContract::class)->getGlobalStatistics();
    $queues = app(StatisticsRepositoryContract::class)->getQueueHealth();

    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => $queues,
        'jobs' => JobMonitor::orderByDesc('queued_at')->limit(20)->get(),
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => null,
        'searchQuery' => '',
        'inSearchMode' => false,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
    ])->render();

    expect($html)->toContain('TestJob');

    // Also confirm the command itself runs successfully
    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

it('dashboard renders all view types without errors', function () {
    JobMonitor::factory()->count(2)->create();
    JobMonitor::factory()->failed()->create();

    $stats = [
        'total_jobs' => 3,
        'success_rate' => 66.7,
        'avg_duration_ms' => 200,
        'failed_jobs' => 1,
        'completed_jobs' => 2,
        'processing' => 0,
        'max_duration_ms' => 400,
        'avg_memory_mb' => 25.0,
        'failure_rate' => 33.3,
    ];

    $queues = [
        ['queue' => 'default', 'status' => 'healthy', 'total_last_hour' => 3, 'processing' => 0, 'failed' => 1, 'avg_duration_ms' => 200, 'health_score' => 66.7],
    ];

    $jobs = JobMonitor::all();

    foreach ([1, 2, 3, 4] as $view) {
        $html = view('queue-monitor::tui.dashboard', [
            'stats' => $stats,
            'queues' => $queues,
            'jobs' => $jobs,
            'selectedIndex' => 0,
            'currentView' => $view,
            'statusFilter' => null,
            'searchQuery' => '',
            'inSearchMode' => false,
            'timestamp' => now()->format('H:i:s'),
            'healthy' => true,
        ])->render();

        render($html);
    }

    expect(true)->toBeTrue();
});

it('dashboard renders with status filter active', function () {
    JobMonitor::factory()->count(2)->create();

    $stats = [
        'total_jobs' => 2,
        'success_rate' => 100,
        'avg_duration_ms' => 150,
        'failed_jobs' => 0,
        'completed_jobs' => 2,
        'processing' => 0,
        'max_duration_ms' => 300,
        'avg_memory_mb' => 20.0,
        'failure_rate' => 0,
    ];

    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => [],
        'jobs' => JobMonitor::all(),
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => 'failed',
        'searchQuery' => '',
        'inSearchMode' => false,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
    ])->render();

    render($html);

    expect($html)->toContain('Filter: Failed');
});

it('dashboard renders with search mode active', function () {
    JobMonitor::factory()->count(2)->create();

    $stats = [
        'total_jobs' => 2,
        'success_rate' => 100,
        'avg_duration_ms' => 150,
        'failed_jobs' => 0,
        'completed_jobs' => 2,
        'processing' => 0,
        'max_duration_ms' => 300,
        'avg_memory_mb' => 20.0,
        'failure_rate' => 0,
    ];

    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => [],
        'jobs' => JobMonitor::all(),
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => null,
        'searchQuery' => 'test',
        'inSearchMode' => true,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
    ])->render();

    render($html);

    expect($html)->toContain('Search:');
});

it('dashboard renders with empty job list', function () {
    $stats = [
        'total_jobs' => 0,
        'success_rate' => 0,
        'avg_duration_ms' => 0,
        'failed_jobs' => 0,
        'completed_jobs' => 0,
        'processing' => 0,
        'max_duration_ms' => 0,
        'avg_memory_mb' => 0,
        'failure_rate' => 0,
    ];

    $html = view('queue-monitor::tui.dashboard', [
        'stats' => $stats,
        'queues' => [],
        'jobs' => collect(),
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => null,
        'searchQuery' => '',
        'inSearchMode' => false,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
    ])->render();

    render($html);

    expect($html)->toContain('No jobs found');
});

it('can publish migrations and config', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
        '--provider' => 'Cbox\LaravelQueueMonitor\LaravelQueueMonitorServiceProvider',
    ]);

    expect($exitCode)->toBe(0);
});
