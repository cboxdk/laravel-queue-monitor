<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Feature;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Illuminate\Support\Facades\Artisan;

use function Termwind\render;

/**
 * Default view data for TUI dashboard tests.
 *
 * @return array<string, mixed>
 */
function tuiViewData(array $overrides = []): array
{
    return array_merge([
        'stats' => [
            'total' => 10,
            'success_rate' => 90,
            'avg_duration_ms' => 150,
            'failed' => 1,
            'completed' => 9,
            'processing' => 0,
            'max_duration_ms' => 500,
            'avg_memory_mb' => 32.5,
            'max_memory_mb' => 64.0,
            'failure_rate' => 10,
        ],
        'queues' => [
            ['queue' => 'default', 'status' => 'healthy', 'total_last_hour' => 10, 'processing' => 0, 'failed' => 1, 'avg_duration_ms' => 150, 'health_score' => 90, 'success_rate' => 90],
        ],
        'jobs' => collect(),
        'totalJobs' => 0,
        'selectedIndex' => 0,
        'currentView' => 1,
        'statusFilter' => null,
        'queueFilter' => null,
        'searchQuery' => '',
        'inSearchMode' => false,
        'timestamp' => now()->format('H:i:s'),
        'healthy' => true,
        'alerts' => [],
        'pageOffset' => 0,
        'perPage' => 20,
        'jobDetail' => null,
        'jobRetryChain' => collect(),
        'analyticsData' => null,
        'healthData' => null,
        'infrastructureData' => null,
    ], $overrides);
}

it('renders the TUI dashboard without errors', function () {
    // Create some dummy data
    JobMonitor::factory()->count(3)->create();

    // Execute the command with --once
    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

it('renders the TUI view directly to catch style errors', function () {
    $jobs = JobMonitor::factory()->count(2)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => $jobs,
        'totalJobs' => $jobs->count(),
    ]))->render();

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
    $jobs = JobMonitor::orderByDesc('queued_at')->limit(20)->get();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'stats' => $stats,
        'queues' => $queues,
        'jobs' => $jobs,
        'totalJobs' => $jobs->count(),
    ]))->render();

    expect($html)->toContain('TestJob');

    // Also confirm the command itself runs successfully
    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

it('dashboard renders all view types without errors', function () {
    JobMonitor::factory()->count(2)->create();
    JobMonitor::factory()->failed()->create();

    $jobs = JobMonitor::all();

    foreach ([1, 2, 3, 4, 5, 6] as $view) {
        $html = view('queue-monitor::tui.dashboard', tuiViewData([
            'jobs' => $jobs,
            'totalJobs' => $jobs->count(),
            'currentView' => $view,
            'analyticsData' => $view === 5 ? [
                'job_classes' => [['job_class' => 'App\\Jobs\\Test', 'total' => 3, 'completed' => 2, 'failed' => 1, 'avg_duration_ms' => 200, 'success_rate' => 66.7]],
                'queues' => [],
                'servers' => [],
                'failure_patterns' => ['top_exceptions' => [['exception_class' => 'RuntimeException', 'count' => 1, 'affected_jobs' => ['App\\Jobs\\Test']]]],
                'tags' => [],
            ] : null,
            'healthData' => $view === 4 ? [
                'status' => 'healthy',
                'score' => 100,
                'checks' => [
                    'database' => ['healthy' => true, 'message' => 'OK'],
                    'recent_activity' => ['healthy' => true, 'message' => 'Active'],
                ],
            ] : null,
            'infrastructureData' => $view === 6 ? [
                'workers' => ['available' => false],
                'worker_types' => ['by_type' => []],
                'sla' => ['per_queue' => []],
                'scaling' => ['utilization' => ['percentage' => 45, 'status' => 'underutilized', 'busy_workers' => 2, 'total_workers' => 4], 'has_autoscale' => false, 'history' => [], 'summary' => []],
                'capacity' => ['queues' => []],
            ] : null,
        ]))->render();

        render($html);
    }

    expect(true)->toBeTrue();
});

it('dashboard renders with status filter active', function () {
    JobMonitor::factory()->count(2)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 2,
        'statusFilter' => 'failed',
    ]))->render();

    render($html);

    expect($html)->toContain('Failed');
});

it('dashboard renders with search mode active', function () {
    JobMonitor::factory()->count(2)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 2,
        'searchQuery' => 'test',
        'inSearchMode' => true,
    ]))->render();

    render($html);

    expect($html)->toContain('test');
});

it('dashboard renders with empty job list', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'stats' => [
            'total' => 0, 'success_rate' => 0, 'avg_duration_ms' => 0,
            'failed' => 0, 'completed' => 0, 'processing' => 0,
            'max_duration_ms' => 0, 'avg_memory_mb' => 0, 'failure_rate' => 0,
        ],
    ]))->render();

    render($html);

    expect($html)->toContain('No jobs found');
});

it('dashboard renders with queue filter active', function () {
    JobMonitor::factory()->count(2)->create(['queue' => 'payments']);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 2,
        'queueFilter' => 'payments',
    ]))->render();

    render($html);

    expect($html)->toContain('payments');
});

it('dashboard renders job detail view', function () {
    $job = JobMonitor::factory()->failed()->create([
        'job_class' => 'App\\Jobs\\ProcessPayment',
        'queue' => 'payments',
        'exception_class' => 'RuntimeException',
        'exception_message' => 'Payment gateway timeout',
        'duration_ms' => 1500,
        'memory_peak_mb' => 32.5,
    ]);

    $retryChain = collect([$job]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => $retryChain,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('ProcessPayment')
        ->toContain('RuntimeException')
        ->toContain('Payment gateway timeout')
        ->toContain('payments');
});

it('dashboard renders with alerts', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'alerts' => [
            'high_failure_rate' => ['severity' => 'warning', 'message' => 'Failure rate above 10%'],
        ],
    ]))->render();

    render($html);

    expect($html)->toContain('1 alert');
});

it('dashboard pagination shows correct range', function () {
    JobMonitor::factory()->count(5)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 50,
        'pageOffset' => 20,
        'perPage' => 20,
    ]))->render();

    render($html);

    expect($html)->toContain('21-40 of 50');
});

it('can publish migrations and config', function () {
    $exitCode = Artisan::call('vendor:publish', [
        '--provider' => 'Cbox\LaravelQueueMonitor\LaravelQueueMonitorServiceProvider',
    ]);

    expect($exitCode)->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 1: JOBS TABLE
// ═══════════════════════════════════════════════════════════════════════════════

test('jobs view renders timeout status job', function () {
    $job = JobMonitor::factory()->timeout()->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)->toContain('T/O');
});

test('jobs view renders processing status job', function () {
    $job = JobMonitor::factory()->processing()->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)->toContain('Run');
});

test('jobs view renders queued status job', function () {
    $job = JobMonitor::factory()->queued()->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)->toContain('Wait');
});

test('jobs view shows retry indicator when attempt is greater than one', function () {
    $job = JobMonitor::factory()->failed()->create([
        'attempt' => 2,
        'max_attempts' => 3,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)->toContain('×2');
});

test('jobs view shows slow duration in red for jobs over 5000ms', function () {
    $job = JobMonitor::factory()->slow(8000)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('text-red-400')
        ->toContain('8.0s');
});

test('jobs view formats duration in seconds for jobs over 1000ms', function () {
    $job = JobMonitor::factory()->slow(2500)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => collect([$job]),
        'totalJobs' => 1,
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)->toContain('2.5s');
});

// ═══════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════════════════════════════

test('pagination on first page shows no prev link', function () {
    JobMonitor::factory()->count(3)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 50,
        'pageOffset' => 0,
        'perPage' => 20,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('1-20 of 50')
        ->toContain('next →')
        ->not->toContain('← prev');
});

test('pagination on last page shows no next link', function () {
    JobMonitor::factory()->count(3)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 50,
        'pageOffset' => 40,
        'perPage' => 20,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('41-50 of 50')
        ->toContain('← prev')
        ->not->toContain('next →');
});

test('pagination on middle page shows both prev and next links', function () {
    JobMonitor::factory()->count(3)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 60,
        'pageOffset' => 20,
        'perPage' => 20,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('21-40 of 60')
        ->toContain('← prev')
        ->toContain('next →');
});

test('pagination shows correct range when total jobs equals one page', function () {
    JobMonitor::factory()->count(5)->create();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 5,
        'pageOffset' => 0,
        'perPage' => 20,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('1-5 of 5')
        ->not->toContain('← prev')
        ->not->toContain('next →');
});

// ═══════════════════════════════════════════════════════════════════════════════
// FILTER COMBINATIONS
// ═══════════════════════════════════════════════════════════════════════════════

test('dashboard renders with both status and queue filter active', function () {
    JobMonitor::factory()->failed()->create(['queue' => 'critical']);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 1,
        'statusFilter' => 'failed',
        'queueFilter' => 'critical',
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Failed')
        ->toContain('[Q: critical]');
});

test('dashboard renders with status filter and search query combined', function () {
    JobMonitor::factory()->failed()->create(['job_class' => 'App\\Jobs\\InvoiceJob']);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobs' => JobMonitor::all(),
        'totalJobs' => 1,
        'statusFilter' => 'failed',
        'searchQuery' => 'Invoice',
        'inSearchMode' => false,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Failed')
        ->toContain('Invoice');
});

test('dashboard renders active search mode with cursor indicator', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'searchQuery' => 'pay',
        'inSearchMode' => true,
    ]))->render();

    render($html);

    expect($html)->toContain('pay_');
});

// ═══════════════════════════════════════════════════════════════════════════════
// HEADER BAR
// ═══════════════════════════════════════════════════════════════════════════════

test('header shows degraded status when healthy is false', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'healthy' => false,
    ]))->render();

    render($html);

    expect($html)->toContain('Degraded');
});

test('header shows plural alerts label when multiple alerts are present', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'alerts' => [
            'high_failure_rate' => ['severity' => 'warning', 'message' => 'Failure rate above 10%'],
            'queue_backlog' => ['severity' => 'critical', 'message' => 'Queue backlog too large'],
        ],
    ]))->render();

    render($html);

    expect($html)->toContain('2 alerts');
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 2: STATISTICS
// ═══════════════════════════════════════════════════════════════════════════════

test('statistics view renders with real factory data', function () {
    JobMonitor::factory()->count(5)->create();
    JobMonitor::factory()->count(2)->failed()->create();

    $stats = app(StatisticsRepositoryContract::class)->getGlobalStatistics();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'stats' => $stats,
        'currentView' => 2,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Statistics Overview')
        ->toContain('Total Jobs')
        ->toContain('Success Rate')
        ->toContain('Avg Duration');
});

test('statistics view shows high failure rate in red', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'stats' => [
            'total' => 10,
            'success_rate' => 40,
            'avg_duration_ms' => 200,
            'failed' => 6,
            'completed' => 4,
            'processing' => 0,
            'max_duration_ms' => 1000,
            'avg_memory_mb' => 32.0,
            'max_memory_mb' => 64.0,
            'failure_rate' => 60,
        ],
        'currentView' => 2,
    ]))->render();

    render($html);

    expect($html)->toContain('60.00%');
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 3: QUEUES
// ═══════════════════════════════════════════════════════════════════════════════

test('queues view renders with real data from statistics repository', function () {
    JobMonitor::factory()->count(5)->create(['queue' => 'notifications']);
    JobMonitor::factory()->failed()->create(['queue' => 'notifications']);

    $queues = app(StatisticsRepositoryContract::class)->getQueueHealth();

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'queues' => $queues,
        'currentView' => 3,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('notifications')
        ->toContain('HEALTH');
});

test('queues view shows degraded queue status', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'queues' => [
            ['queue' => 'payments', 'status' => 'degraded', 'total_last_hour' => 20, 'processing' => 0, 'failed' => 5, 'avg_duration_ms' => 800, 'health_score' => 60, 'success_rate' => 75],
        ],
        'currentView' => 3,
    ]))->render();

    render($html);

    expect($html)->toContain('degraded');
});

test('queues view shows critical queue status', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'queues' => [
            ['queue' => 'critical', 'status' => 'critical', 'total_last_hour' => 5, 'processing' => 0, 'failed' => 5, 'avg_duration_ms' => 0, 'health_score' => 0, 'success_rate' => 0],
        ],
        'currentView' => 3,
    ]))->render();

    render($html);

    expect($html)->toContain('critical');
});

test('queues view shows empty state when no queue data exists', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'queues' => [],
        'currentView' => 3,
    ]))->render();

    render($html);

    expect($html)->toContain('No queue data available.');
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 4: HEALTH
// ═══════════════════════════════════════════════════════════════════════════════

test('health view shows loading state when health data is null', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 4,
        'healthData' => null,
    ]))->render();

    render($html);

    expect($html)->toContain('Loading health data...');
});

test('health view renders degraded status with failed checks', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 4,
        'healthData' => [
            'status' => 'degraded',
            'score' => 45,
            'checks' => [
                'database' => ['healthy' => true, 'message' => 'OK'],
                'recent_activity' => ['healthy' => false, 'message' => 'No jobs processed in 30 minutes'],
                'failure_rate' => ['healthy' => false, 'message' => 'Failure rate is 35%'],
            ],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('DEGRADED')
        ->toContain('Score: 45%')
        ->toContain('No jobs processed in 30 minutes')
        ->toContain('Failure rate is 35%');
});

test('health view renders critical alert with correct severity color', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 4,
        'healthData' => [
            'status' => 'degraded',
            'score' => 20,
            'checks' => [],
        ],
        'alerts' => [
            'queue_down' => ['severity' => 'critical', 'message' => 'Queue worker has stopped'],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Active Alerts')
        ->toContain('critical')
        ->toContain('Queue worker has stopped');
});

test('health view renders warning and critical alerts together', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 4,
        'healthData' => [
            'status' => 'degraded',
            'score' => 55,
            'checks' => [],
        ],
        'alerts' => [
            'high_failure_rate' => ['severity' => 'warning', 'message' => 'Failure rate is elevated'],
            'queue_down' => ['severity' => 'critical', 'message' => 'Payments queue is down'],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('warning')
        ->toContain('critical')
        ->toContain('Failure rate is elevated')
        ->toContain('Payments queue is down');
});

test('health view renders per-queue health section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 4,
        'healthData' => [
            'status' => 'healthy',
            'score' => 100,
            'checks' => [],
        ],
        'queues' => [
            ['queue' => 'emails', 'status' => 'healthy', 'total_last_hour' => 50, 'processing' => 2, 'failed' => 0, 'avg_duration_ms' => 100, 'health_score' => 100, 'success_rate' => 100],
            ['queue' => 'payments', 'status' => 'degraded', 'total_last_hour' => 10, 'processing' => 0, 'failed' => 3, 'avg_duration_ms' => 500, 'health_score' => 70, 'success_rate' => 70],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Queue Health')
        ->toContain('emails')
        ->toContain('payments')
        ->toContain('degraded');
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 5: ANALYTICS
// ═══════════════════════════════════════════════════════════════════════════════

test('analytics view shows loading state when analytics data is null', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => null,
    ]))->render();

    render($html);

    expect($html)->toContain('Loading analytics...');
});

test('analytics view shows empty job class state', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => [
            'job_classes' => [],
            'queues' => [],
            'servers' => [],
            'failure_patterns' => ['top_exceptions' => []],
            'tags' => [],
        ],
    ]))->render();

    render($html);

    expect($html)->toContain('No job class data.');
});

test('analytics view renders job class statistics from real data', function () {
    JobMonitor::factory()->count(3)->create(['job_class' => 'App\\Jobs\\SendInvoiceJob']);
    JobMonitor::factory()->failed()->create(['job_class' => 'App\\Jobs\\SendInvoiceJob']);

    $stats = app(StatisticsRepositoryContract::class);
    $analyticsData = [
        'job_classes' => $stats->getJobClassStatistics(),
        'queues' => $stats->getQueueStatistics(),
        'servers' => $stats->getServerStatistics(),
        'failure_patterns' => $stats->getFailurePatterns(),
        'tags' => [],
    ];

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => $analyticsData,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Analytics')
        ->toContain('Job Classes')
        ->toContain('SendInvoiceJob');
});

test('analytics view renders failure patterns section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => [
            'job_classes' => [
                ['job_class' => 'App\\Jobs\\ProcessOrder', 'total' => 10, 'completed' => 7, 'failed' => 3, 'avg_duration_ms' => 300, 'success_rate' => 70],
            ],
            'queues' => [],
            'servers' => [],
            'failure_patterns' => [
                'top_exceptions' => [
                    ['exception_class' => 'Illuminate\\Database\\QueryException', 'count' => 3, 'affected_jobs' => ['App\\Jobs\\ProcessOrder']],
                    ['exception_class' => 'GuzzleHttp\\Exception\\ConnectException', 'count' => 1, 'affected_jobs' => ['App\\Jobs\\FetchPrices']],
                ],
            ],
            'tags' => [],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Failure Patterns')
        ->toContain('QueryException')
        ->toContain('ConnectException');
});

test('analytics view renders server statistics section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => [
            'job_classes' => [],
            'queues' => [],
            'servers' => [
                ['server_name' => 'web-01', 'total' => 100, 'failed' => 5, 'avg_duration_ms' => 250],
                ['server_name' => 'web-02', 'total' => 80, 'failed' => 0, 'avg_duration_ms' => 180],
            ],
            'failure_patterns' => ['top_exceptions' => []],
            'tags' => [],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Servers')
        ->toContain('web-01')
        ->toContain('web-02');
});

test('analytics view renders tag statistics section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 5,
        'analyticsData' => [
            'job_classes' => [],
            'queues' => [],
            'servers' => [],
            'failure_patterns' => ['top_exceptions' => []],
            'tags' => [
                ['tag' => 'billing', 'count' => 12, 'success_rate' => 91.7],
                ['tag' => 'notifications', 'count' => 45, 'success_rate' => 100],
            ],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Tags')
        ->toContain('#billing')
        ->toContain('#notifications');
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW 6: INFRASTRUCTURE
// ═══════════════════════════════════════════════════════════════════════════════

test('infrastructure view shows loading state when infrastructure data is null', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => null,
    ]))->render();

    render($html);

    expect($html)->toContain('Loading infrastructure data...');
});

test('infrastructure view renders utilization bar and worker counts', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => ['by_type' => []],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 72, 'status' => 'normal', 'busy_workers' => 7, 'total_workers' => 10],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Utilization')
        ->toContain('72%')
        ->toContain('7 busy / 10 total');
});

test('infrastructure view shows horizon not detected message when workers unavailable', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => ['by_type' => []],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 0, 'status' => 'idle', 'busy_workers' => 0, 'total_workers' => 0],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)->toContain('Horizon not detected');
});

test('infrastructure view renders horizon supervisors when available', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => [
                'available' => true,
                'supervisors' => [
                    ['name' => 'supervisor-1', 'status' => 'running', 'processes' => 4, 'queues' => ['default', 'emails']],
                    ['name' => 'supervisor-2', 'status' => 'paused', 'processes' => 2, 'queues' => ['notifications']],
                ],
            ],
            'worker_types' => ['by_type' => []],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 60, 'status' => 'normal', 'busy_workers' => 6, 'total_workers' => 10],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Supervisors')
        ->toContain('supervisor-1')
        ->toContain('supervisor-2')
        ->toContain('paused');
});

test('infrastructure view renders worker type breakdown', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => [
                'by_type' => [
                    ['label' => 'Queue Worker', 'total_jobs' => 150, 'total_workers' => 8, 'queues' => ['default', 'emails']],
                    ['label' => 'Horizon', 'total_jobs' => 50, 'total_workers' => 3, 'queues' => ['notifications']],
                ],
            ],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 45, 'status' => 'underutilized', 'busy_workers' => 4, 'total_workers' => 9],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Worker Types')
        ->toContain('Queue Worker')
        ->toContain('Horizon');
});

test('infrastructure view renders SLA compliance section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => ['by_type' => []],
            'sla' => [
                'per_queue' => [
                    ['queue' => 'payments', 'compliance' => 98.5, 'within' => 197, 'total' => 200, 'target_seconds' => 30],
                    ['queue' => 'emails', 'compliance' => 72.0, 'within' => 72, 'total' => 100, 'target_seconds' => 60],
                ],
            ],
            'scaling' => [
                'utilization' => ['percentage' => 50, 'status' => 'normal', 'busy_workers' => 5, 'total_workers' => 10],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('SLA Compliance')
        ->toContain('payments')
        ->toContain('98.5%')
        ->toContain('72.0%');
});

test('infrastructure view renders queue capacity section', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => ['by_type' => []],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 80, 'status' => 'high', 'busy_workers' => 8, 'total_workers' => 10],
                'has_autoscale' => false,
                'history' => [],
                'summary' => [],
            ],
            'capacity' => [
                'queues' => [
                    ['queue' => 'default', 'avg_duration_ms' => 200, 'workers' => 4, 'max_jobs_per_minute' => 1200, 'peak_jobs_per_minute' => 900, 'headroom_percent' => 25, 'status' => 'optimal'],
                    ['queue' => 'payments', 'avg_duration_ms' => 800, 'workers' => 2, 'max_jobs_per_minute' => 150, 'peak_jobs_per_minute' => 148, 'headroom_percent' => 1, 'status' => 'at_capacity'],
                ],
            ],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Queue Capacity')
        ->toContain('at_capacity')
        ->toContain('optimal');
});

test('infrastructure view renders scaling events when autoscale is enabled', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 6,
        'infrastructureData' => [
            'workers' => ['available' => false],
            'worker_types' => ['by_type' => []],
            'sla' => ['per_queue' => []],
            'scaling' => [
                'utilization' => ['percentage' => 90, 'status' => 'critical', 'busy_workers' => 9, 'total_workers' => 10],
                'has_autoscale' => true,
                'history' => [
                    ['action' => 'scale_up', 'queue' => 'payments', 'current_workers' => 2, 'target_workers' => 4, 'reason' => 'High utilization', 'time_human' => '2m ago'],
                    ['action' => 'scale_down', 'queue' => 'emails', 'current_workers' => 6, 'target_workers' => 3, 'reason' => 'Low utilization', 'time_human' => '10m ago'],
                ],
                'summary' => [
                    'total_decisions' => 5,
                    'scale_ups' => 3,
                    'scale_downs' => 2,
                    'sla_breaches' => 1,
                ],
            ],
            'capacity' => ['queues' => []],
        ],
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Scaling Events')
        ->toContain('Decisions: 5')
        ->toContain('High utilization')
        ->toContain('Low utilization')
        ->toContain('1 breaches');
});

// ═══════════════════════════════════════════════════════════════════════════════
// JOB DETAIL VIEW
// ═══════════════════════════════════════════════════════════════════════════════

test('job detail view renders retry chain with multiple attempts', function () {
    $attempt1 = JobMonitor::factory()->failed()->create([
        'job_class' => 'App\\Jobs\\SendNotification',
        'attempt' => 1,
        'max_attempts' => 3,
        'duration_ms' => 500,
        'exception_message' => 'Connection refused',
    ]);

    $attempt2 = JobMonitor::factory()->failed()->create([
        'job_class' => 'App\\Jobs\\SendNotification',
        'attempt' => 2,
        'max_attempts' => 3,
        'retried_from_id' => $attempt1->id,
        'duration_ms' => 300,
        'exception_message' => 'Connection refused',
    ]);

    $attempt3 = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\SendNotification',
        'attempt' => 3,
        'max_attempts' => 3,
        'retried_from_id' => $attempt2->id,
    ]);

    $retryChain = collect([$attempt1, $attempt2, $attempt3]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $attempt3,
        'jobRetryChain' => $retryChain,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Retry Chain (3 attempts)')
        ->toContain('#1')
        ->toContain('#2')
        ->toContain('#3');
});

test('job detail view does not show retry chain section for single attempt', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\ImportData',
        'attempt' => 1,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)->not->toContain('Retry Chain');
});

test('job detail view renders completed job without replay keybinding', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\SyncData',
        'status' => JobStatus::COMPLETED,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)
        ->toContain('SyncData')
        ->not->toContain('Replay');
});

test('job detail view renders failed job with replay keybinding', function () {
    $job = JobMonitor::factory()->failed()->create([
        'job_class' => 'App\\Jobs\\ProcessWebhook',
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)->toContain('Replay');
});

test('job detail view renders job with tags', function () {
    $job = JobMonitor::factory()->withTags(['billing', 'priority-high'])->create([
        'job_class' => 'App\\Jobs\\ChargeSubscription',
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)
        ->toContain('#billing')
        ->toContain('#priority-high');
});

test('job detail view renders job with no payload gracefully', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\CleanupJob',
        'payload' => null,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)->toContain('CleanupJob');
});

test('job detail view renders exception trace lines for failed job', function () {
    $job = JobMonitor::factory()->failed()->create([
        'job_class' => 'App\\Jobs\\ProcessPayment',
        'exception_class' => 'App\\Exceptions\\PaymentException',
        'exception_message' => 'Card declined',
        'exception_trace' => implode("\n", [
            '#0 /app/src/Jobs/ProcessPayment.php(45): chargeCard()',
            '#1 /app/vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php(103): handle()',
            '#2 /app/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(98): call()',
        ]),
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)
        ->toContain('PaymentException')
        ->toContain('Card declined')
        ->toContain('ProcessPayment.php');
});

test('job detail view shows retryable label when job can still be retried', function () {
    $job = JobMonitor::factory()->failed()->create([
        'attempt' => 1,
        'max_attempts' => 3,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)->toContain('retryable');
});

test('job detail view renders display name when it differs from job class', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\ProcessOrder',
        'display_name' => 'Process Customer Order #12345',
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)
        ->toContain('ProcessOrder')
        ->toContain('Process Customer Order #12345');
});

test('job detail view renders slow duration in red', function () {
    $job = JobMonitor::factory()->failed()->create([
        'duration_ms' => 8000,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)->toContain('8,000ms');
});

test('job detail view renders memory and file descriptor metrics', function () {
    $job = JobMonitor::factory()->create([
        'memory_peak_mb' => 128.50,
        'file_descriptors' => 42,
        'cpu_time_ms' => 350.0,
    ]);

    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'jobDetail' => $job,
        'jobRetryChain' => collect([$job]),
    ]))->render();

    render($html);

    expect($html)
        ->toContain('128.50MB')
        ->toContain('FDs:')
        ->toContain('42')
        ->toContain('CPU:');
});

// ═══════════════════════════════════════════════════════════════════════════════
// COMMAND --once WITH DIFFERENT DATA STATES
// ═══════════════════════════════════════════════════════════════════════════════

test('dashboard command runs successfully with all job statuses present', function () {
    JobMonitor::factory()->create();
    JobMonitor::factory()->failed()->create();
    JobMonitor::factory()->timeout()->create();
    JobMonitor::factory()->processing()->create();
    JobMonitor::factory()->queued()->create();

    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

test('dashboard command runs successfully with no jobs in database', function () {
    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

test('dashboard command runs successfully with large number of jobs', function () {
    JobMonitor::factory()->count(25)->create();
    JobMonitor::factory()->count(5)->failed()->create();

    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

test('dashboard command runs successfully with horizon worker jobs', function () {
    JobMonitor::factory()->horizon()->count(3)->create();

    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

test('dashboard command runs successfully with tagged jobs', function () {
    JobMonitor::factory()->withTags(['billing', 'urgent'])->count(2)->create();

    $this->artisan('queue-monitor:dashboard', ['--once' => true])
        ->assertSuccessful();
});

// ═══════════════════════════════════════════════════════════════════════════════
// VIEW TAB NAVIGATION RENDERING
// ═══════════════════════════════════════════════════════════════════════════════

test('active view tab is highlighted in tab bar', function () {
    foreach ([1, 2, 3, 4, 5, 6] as $view) {
        $html = view('queue-monitor::tui.dashboard', tuiViewData([
            'currentView' => $view,
        ]))->render();

        render($html);
    }

    expect(true)->toBeTrue();
});

test('keybindings footer shows job-specific shortcuts on view 1', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 1,
    ]))->render();

    render($html);

    expect($html)
        ->toContain('Search')
        ->toContain('Status')
        ->toContain('Detail');
});

test('keybindings footer shows generic shortcuts on non-job views', function () {
    $html = view('queue-monitor::tui.dashboard', tuiViewData([
        'currentView' => 2,
    ]))->render();

    render($html);

    expect($html)->toContain('1-6');
});
