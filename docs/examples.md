---
title: "Usage Examples"
description: "Real-world examples and common patterns for monitoring, replay, and analytics"
weight: 15
---

# Usage Examples

## Basic Monitoring

### Monitor All Jobs

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

// Get last 100 jobs
$jobs = QueueMonitor::getRecentJobs(100);

foreach ($jobs as $job) {
    echo "{$job->job_class}: {$job->status->label()} ({$job->duration_ms}ms)\n";
}
```

### Check Job Status

```php
$job = QueueMonitor::getJob($uuid);

if ($job->isSuccessful()) {
    echo "Job completed successfully\n";
} elseif ($job->isFailed()) {
    echo "Job failed: {$job->exception_message}\n";
} elseif ($job->isProcessing()) {
    echo "Job is still running\n";
}
```

## Filtering and Searching

### Failed Jobs from Specific Queue

```php
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED, JobStatus::TIMEOUT],
    queues: ['emails'],
    limit: 50
);

$failedEmails = QueueMonitor::getJobs($filters);
```

### Search by Exception Type

```php
$filters = new JobFilterData(
    search: 'TimeoutException',
    statuses: [JobStatus::FAILED]
);

$timeoutJobs = QueueMonitor::getJobs($filters);
```

### Jobs from Last 24 Hours

```php
use Carbon\Carbon;

$filters = new JobFilterData(
    queuedAfter: Carbon::now()->subHours(24),
    sortBy: 'queued_at',
    sortDirection: 'desc'
);

$recentJobs = QueueMonitor::getJobs($filters);
```

### Slow Jobs

```php
$filters = new JobFilterData(
    minDurationMs: 5000, // > 5 seconds
    statuses: [JobStatus::COMPLETED],
    sortBy: 'duration_ms',
    sortDirection: 'desc'
);

$slowJobs = QueueMonitor::getJobs($filters);
```

## Job Replay Scenarios

### Replay Single Failed Job

```php
try {
    $replayData = QueueMonitor::replay($uuid);

    echo "Original: {$replayData->originalUuid}\n";
    echo "New Job: {$replayData->newJobId}\n";
    echo "Queue: {$replayData->queue}\n";
} catch (\Exception $e) {
    echo "Replay failed: {$e->getMessage()}\n";
}
```

### Replay All Failed Jobs from Last Hour

```php
use Carbon\Carbon;

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queuedAfter: Carbon::now()->subHour()
);

$failed = QueueMonitor::getJobs($filters);

foreach ($failed as $job) {
    try {
        QueueMonitor::replay($job->uuid);
        echo "Replayed: {$job->job_class}\n";
    } catch (\Exception $e) {
        echo "Failed to replay {$job->uuid}: {$e->getMessage()}\n";
    }

    usleep(100000); // 100ms delay to avoid overwhelming queue
}
```

### Conditional Replay Based on Exception

```php
$failed = QueueMonitor::getFailedJobs(100);

foreach ($failed as $job) {
    // Only replay transient failures
    if (str_contains($job->exception_message, 'Connection timeout') ||
        str_contains($job->exception_message, 'SQLSTATE')) {

        QueueMonitor::replay($job->uuid);
    }
}
```

## Analytics and Reporting

### Daily Statistics Report

```php
$stats = QueueMonitor::statistics();

echo "=== Daily Queue Report ===\n";
echo "Total Jobs: {$stats['total']}\n";
echo "Completed: {$stats['completed']}\n";
echo "Failed: {$stats['failed']}\n";
echo "Success Rate: {$stats['success_rate']}%\n";
echo "Avg Duration: {$stats['avg_duration_ms']}ms\n";
echo "Peak Memory: {$stats['max_memory_mb']}MB\n";
```

### Per-Queue Health Check

```php
$health = QueueMonitor::queueHealth();

foreach ($health as $queue) {
    $status = $queue['status']; // healthy, degraded, unhealthy
    $score = $queue['health_score'];

    if ($status !== 'healthy') {
        echo "⚠️ Queue '{$queue['queue']}' is {$status} (score: {$score})\n";
        echo "   Processing: {$queue['processing']}\n";
        echo "   Failed (last hour): {$queue['failed']}\n";
    }
}
```

### Top Failing Jobs

```php
use Illuminate\Support\Facades\DB;

$topFailures = DB::table('queue_monitor_jobs')
    ->select('job_class', DB::raw('COUNT(*) as failure_count'))
    ->where('status', 'failed')
    ->whereDate('completed_at', '>=', now()->subDays(7))
    ->groupBy('job_class')
    ->orderByDesc('failure_count')
    ->limit(10)
    ->get();

foreach ($topFailures as $failure) {
    echo "{$failure->job_class}: {$failure->failure_count} failures\n";
}
```

### Server Performance Comparison

```php
$serverStats = QueueMonitor::serverStatistics();

foreach ($serverStats as $server) {
    echo "Server: {$server['server_name']}\n";
    echo "  Total: {$server['total']}\n";
    echo "  Success Rate: {$server['success_rate']}%\n";
    echo "  Avg Duration: {$server['avg_duration_ms']}ms\n\n";
}
```

## Advanced Queries

### Jobs with Retry Chains

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$jobsWithRetries = JobMonitor::has('retries')->get();

foreach ($jobsWithRetries as $job) {
    $chain = QueueMonitor::getRetryChain($job->uuid);

    echo "Job {$job->uuid} had {$chain->count()} attempts:\n";
    foreach ($chain as $attempt) {
        echo "  Attempt {$attempt->attempt}: {$attempt->status->label()}\n";
    }
}
```

### Jobs by Tag

```php
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

$priorityJobs = QueryBuilderHelper::withTag('high-priority')
    ->whereDate('queued_at', today())
    ->get();

$emailJobs = QueryBuilderHelper::withAllTags(['email', 'priority'])
    ->get();
```

### Slow Jobs Analysis

```php
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

$slowJobs = QueryBuilderHelper::slow(10000) // > 10 seconds
    ->whereDate('completed_at', today())
    ->get();

foreach ($slowJobs as $job) {
    echo "{$job->job_class}: {$job->getDurationInSeconds()}s\n";
}
```

### Stuck Jobs Detection

```php
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

$stuck = QueryBuilderHelper::stuck(30) // Processing > 30 min
    ->get();

foreach ($stuck as $job) {
    echo "Stuck job: {$job->uuid} (started {$job->started_at->diffForHumans()})\n";

    // Mark as timeout
    $job->update([
        'status' => JobStatus::TIMEOUT,
        'completed_at' => now(),
    ]);
}
```

## Performance Analysis

### Percentile Statistics

```php
use Cbox\LaravelQueueMonitor\Utilities\PerformanceAnalyzer;

$percentiles = PerformanceAnalyzer::getDurationPercentiles('App\\Jobs\\ProcessOrder');

echo "P50 (median): {$percentiles['p50']}ms\n";
echo "P95: {$percentiles['p95']}ms\n";
echo "P99: {$percentiles['p99']}ms\n";
```

### Performance Regression Detection

```php
$regression = PerformanceAnalyzer::detectRegression(
    'App\\Jobs\\ProcessOrder',
    baselineDays: 30,
    comparisonDays: 7
);

if ($regression['regression']) {
    echo "⚠️ Performance regression detected!\n";
    echo "Baseline: {$regression['baseline']['avg_duration_ms']}ms\n";
    echo "Current: {$regression['current']['avg_duration_ms']}ms\n";
    echo "Change: {$regression['change_percent']}%\n";
}
```

### Duration Distribution

```php
$distribution = PerformanceAnalyzer::getDurationDistribution('App\\Jobs\\SendEmail');

foreach ($distribution as $bucket => $count) {
    echo "{$bucket}: {$count} jobs\n";
}
```

## Alerting Examples

### Slack Notification on Critical Failures

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

Event::listen(JobMonitorRecorded::class, function ($event) {
    $job = $event->jobMonitor;

    if ($job->isFailed() && in_array('critical', $job->tags ?? [])) {
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new CriticalJobFailedNotification($job));
    }
});
```

### Email on High Failure Rate

```php
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $stats = QueueMonitor::statistics();

    if ($stats['failure_rate'] > 10) { // > 10% failures
        Mail::to('admin@example.com')
            ->send(new HighFailureRateAlert($stats));
    }
})->hourly();
```

## Bulk Operations

### Replay All Failed Jobs for Specific Job Class

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$failed = JobMonitor::failed()
    ->where('job_class', 'App\\Jobs\\SendEmail')
    ->where('created_at', '>=', now()->subHours(24))
    ->get();

$success = 0;
$errors = [];

foreach ($failed as $job) {
    try {
        QueueMonitor::replay($job->uuid);
        $success++;
    } catch (\Exception $e) {
        $errors[$job->uuid] = $e->getMessage();
    }
}

echo "Replayed {$success} jobs\n";
echo "Errors: ".count($errors)."\n";
```

### Clean Up Old Successful Jobs

```php
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

$deleted = QueueMonitor::prune(
    days: 7,
    statuses: [JobStatus::COMPLETED->value]
);

echo "Deleted {$deleted} old successful jobs\n";
```

## Custom Dashboard Integration

### Export Data for Charts

```php
use Illuminate\Support\Facades\DB;

$hourlyStats = DB::table('queue_monitor_jobs')
    ->select([
        DB::raw('DATE_FORMAT(queued_at, "%Y-%m-%d %H:00") as hour'),
        DB::raw('COUNT(*) as total'),
        DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
        DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
    ])
    ->whereBetween('queued_at', [now()->subDay(), now()])
    ->groupBy('hour')
    ->get();

// Return as JSON for chart library
return response()->json($hourlyStats);
```

### Real-Time Dashboard Updates

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function ($event) {
    // Broadcast via WebSockets
    broadcast(new JobStatusUpdated(
        $event->jobMonitor->toArray()
    ));
});
```

## Testing in Your Application

### Create Test Jobs

```php
namespace Tests\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Cbox\LaravelQueueMonitor\Traits\MonitorsJobs;

class TestMonitoringJob implements ShouldQueue
{
    use MonitorsJobs;

    public function handle(): void
    {
        sleep(2); // Simulate work
    }

    public function tags(): array
    {
        return ['test', 'monitoring'];
    }
}
```

### Test Monitoring in Feature Tests

```php
test('job is monitored when dispatched', function () {
    TestMonitoringJob::dispatch();

    $this->artisan('queue:work --once');

    $job = JobMonitor::latest()->first();

    expect($job)->not->toBeNull();
    expect($job->job_class)->toContain('TestMonitoringJob');
    expect($job->status)->toBe(JobStatus::COMPLETED);
});
```

## Production Scenarios

### Find Jobs Stuck in Processing

```php
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

$stuck = QueryBuilderHelper::stuck(60) // > 60 minutes
    ->get();

if ($stuck->isNotEmpty()) {
    foreach ($stuck as $job) {
        // Log for investigation
        Log::warning('Stuck job detected', [
            'uuid' => $job->uuid,
            'job_class' => $job->job_class,
            'started_at' => $job->started_at,
            'worker_id' => $job->worker_id,
        ]);

        // Mark as timeout
        $job->update([
            'status' => JobStatus::TIMEOUT,
            'completed_at' => now(),
            'exception_message' => 'Job marked as timeout (stuck for > 60 minutes)',
        ]);
    }
}
```

### Daily Health Check Report

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $stats = QueueMonitor::statistics();
    $health = QueueMonitor::queueHealth();
    $serverStats = QueueMonitor::serverStatistics();

    Mail::to('team@example.com')->send(
        new DailyQueueHealthReport($stats, $health, $serverStats)
    );
})->dailyAt('08:00');
```

### Automatic Retry for Transient Failures

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function ($event) {
    $job = $event->jobMonitor;

    if (!$job->isFailed()) {
        return;
    }

    $transientErrors = [
        'Connection timeout',
        'SQLSTATE[HY000]',
        'Network unreachable',
    ];

    foreach ($transientErrors as $error) {
        if (str_contains($job->exception_message ?? '', $error)) {
            if ($job->attempt < $job->max_attempts) {
                QueueMonitor::replay($job->uuid);
                Log::info("Auto-replayed transient failure: {$job->uuid}");
                break;
            }
        }
    }
});
```

## Multi-Tenant Applications

### Tenant-Specific Job Tracking

```php
// In your job
class TenantAwareJob implements ShouldQueue
{
    use MonitorsJobs;

    public function __construct(
        public int $tenantId
    ) {}

    public function tags(): array
    {
        return [
            "tenant:{$this->tenantId}",
            tenant($this->tenantId)->name,
        ];
    }
}

// Query tenant jobs
$filters = new JobFilterData(
    tags: ['tenant:123']
);

$tenantJobs = QueueMonitor::getJobs($filters);
```

### Per-Tenant Statistics

```php
$tenantId = 123;

$jobs = JobMonitor::whereJsonContains('tags', "tenant:{$tenantId}")
    ->get();

$stats = [
    'total' => $jobs->count(),
    'completed' => $jobs->where('status', JobStatus::COMPLETED)->count(),
    'failed' => $jobs->where('status', JobStatus::FAILED)->count(),
    'avg_duration' => $jobs->avg('duration_ms'),
];
```

## Integration with Monitoring Tools

### Export to Prometheus

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function ($event) {
    $job = $event->jobMonitor;

    if ($job->isFinished()) {
        app('prometheus')->histogram(
            'queue_job_duration_seconds',
            $job->getDurationInSeconds() ?? 0,
            [$job->queue, $job->status->value]
        );
    }
});
```

### Send to DataDog

```php
use DataDog\DogStatsd;

$statsd = new DogStatsd();

$stats = QueueMonitor::statistics();

$statsd->gauge('queue.jobs.total', $stats['total']);
$statsd->gauge('queue.success_rate', $stats['success_rate']);
$statsd->timing('queue.avg_duration', $stats['avg_duration_ms'] ?? 0);
```

## Scheduled Maintenance

### Daily Pruning

```php
// routes/console.php
Schedule::command('queue-monitor:prune', [
    '--days' => 7,
    '--statuses' => 'completed'
])->daily()->at('02:00');

// Keep failures longer
Schedule::command('queue-monitor:prune', [
    '--days' => 30,
    '--statuses' => 'failed,timeout'
])->weekly();
```

### Weekly Performance Report

```php
Schedule::call(function () {
    $lastWeek = [
        'total' => JobMonitor::whereBetween('queued_at', [
            now()->subWeek(),
            now()
        ])->count(),
        'success_rate' => JobMonitor::successful()
            ->whereBetween('completed_at', [now()->subWeek(), now()])
            ->count(),
    ];

    // Email to team
    Mail::to('team@example.com')->send(
        new WeeklyQueueReport($lastWeek)
    );
})->weeklyOn(1, '09:00'); // Monday 9 AM
```
