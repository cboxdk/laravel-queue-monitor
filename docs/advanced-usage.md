---
title: "Advanced Usage"
description: "Custom monitoring, dashboards, multi-tenancy, and performance optimization"
weight: 40
---

# Advanced Usage

## Custom Job Monitoring

### Using the MonitorsJobs Trait

Add custom monitoring behavior to your jobs:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Cbox\LaravelQueueMonitor\Traits\MonitorsJobs;

class ProcessOrderJob implements ShouldQueue
{
    use MonitorsJobs;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        // Process order
    }

    /**
     * Custom display name for monitoring
     */
    public function displayName(): string
    {
        return "Processing Order #{$this->orderId}";
    }

    /**
     * Tag this job for categorization
     */
    public function tags(): array
    {
        return ['orders', 'high-priority'];
    }
}
```

### Conditional Payload Storage

Control payload storage per job:

```php
class SensitiveDataJob implements ShouldQueue
{
    use MonitorsJobs;

    public function shouldStorePayload(): bool
    {
        // Don't store payload for sensitive data
        return false;
    }
}
```

## Building Custom Dashboards

### Real-Time Job Monitoring

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;
use Illuminate\Support\Facades\Event;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    // Broadcast to websocket for real-time dashboard
    broadcast(new JobStatusUpdated($job));
});
```

### Custom Alerting

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->status->isFailed()) {
        // Send to Slack, email, etc.
        Alert::send(new JobFailedAlert($job));
    }

    if ($job->duration_ms > 10000) {
        // Alert on slow jobs
        Alert::send(new SlowJobAlert($job));
    }
});
```

## Custom Repository Implementations

### Redis-Based Repository

Create a custom repository for Redis storage:

```php
namespace App\Repositories;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Support\Facades\Redis;

class RedisJobMonitorRepository implements JobMonitorRepositoryContract
{
    public function create(JobMonitorData $data): JobMonitor
    {
        // Store in Redis with TTL
        Redis::setex(
            "job:{$data->uuid}",
            3600, // 1 hour TTL
            json_encode($data->toArray())
        );

        // Also store in database for long-term
        return JobMonitor::create($data->toArray());
    }

    // Implement other methods...
}
```

Register in config:

```php
// config/queue-monitor.php
'repositories' => [
    JobMonitorRepositoryContract::class => \App\Repositories\RedisJobMonitorRepository::class,
],
```

## Performance Optimization

### Query Optimization

Use eager loading for relationships:

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$jobs = JobMonitor::with(['retriedFrom', 'retries', 'tagRecords'])
    ->failed()
    ->whereDate('completed_at', today())
    ->get();
```

### Pagination

For large datasets:

```php
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;

$filters = new JobFilterData(
    limit: 50,
    offset: 0,
    sortBy: 'queued_at',
    sortDirection: 'desc'
);

$jobs = QueueMonitor::getJobs($filters);
$total = $this->repository->count($filters);

// Paginate through results
for ($offset = 0; $offset < $total; $offset += 50) {
    $filters = new JobFilterData(limit: 50, offset: $offset);
    $batch = QueueMonitor::getJobs($filters);

    // Process batch
}
```

### Caching Statistics

Cache expensive statistics queries:

```php
use Illuminate\Support\Facades\Cache;

$stats = Cache::remember('queue-monitor:stats', 60, function() {
    return QueueMonitor::statistics();
});
```

## Multi-Tenant Applications

### Tenant-Specific Filtering

Filter jobs by tenant:

```php
class TenantAwareJob implements ShouldQueue
{
    use MonitorsJobs;

    public function tags(): array
    {
        return [
            'tenant:' . tenant()->id,
            'tenant-name:' . tenant()->name,
        ];
    }
}

// Query jobs for specific tenant
$filters = new JobFilterData(
    tags: ['tenant:123']
);

$tenantJobs = QueueMonitor::getJobs($filters);
```

### Separate Database Per Tenant

```php
// config/queue-monitor.php
'database' => [
    'connection' => tenant()->getDatabaseConnection(),
],
```

## Custom Analytics

### Performance Baselines

Track performance degradation:

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$baseline = JobMonitor::forJobClass('App\\Jobs\\ProcessOrder')
    ->successful()
    ->whereBetween('completed_at', [
        now()->subDays(30),
        now()->subDays(7)
    ])
    ->avg('duration_ms');

$recent = JobMonitor::forJobClass('App\\Jobs\\ProcessOrder')
    ->successful()
    ->whereDate('completed_at', today())
    ->avg('duration_ms');

if ($recent > $baseline * 1.5) {
    Alert::send("ProcessOrder performance degraded by 50%");
}
```

### Failure Rate Trending

```php
$dailyFailureRates = collect();

for ($i = 0; $i < 30; $i++) {
    $date = today()->subDays($i);

    $total = JobMonitor::whereDate('completed_at', $date)->count();
    $failed = JobMonitor::failed()->whereDate('completed_at', $date)->count();

    $failureRate = $total > 0 ? ($failed / $total) * 100 : 0;

    $dailyFailureRates->push([
        'date' => $date->toDateString(),
        'failure_rate' => $failureRate,
    ]);
}
```

## Error Handling

### Custom Exception Handling

```php
use Cbox\LaravelQueueMonitor\Exceptions\JobNotFoundException;
use Cbox\LaravelQueueMonitor\Exceptions\JobReplayException;

try {
    QueueMonitor::replay($uuid);
} catch (JobNotFoundException $e) {
    Log::error("Job not found for replay: {$uuid}");
    return response()->json(['error' => 'Job not found'], 404);
} catch (JobReplayException $e) {
    Log::error("Replay failed: {$e->getMessage()}");
    return response()->json(['error' => $e->getMessage()], 422);
}
```

## Scheduled Tasks

### Automatic Pruning

```php
// app/Console/Kernel.php (Laravel 10) or routes/console.php (Laravel 11+)

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue-monitor:prune', ['--days' => 30])
    ->daily()
    ->at('02:00');
```

### Daily Reports

```php
Schedule::call(function() {
    $stats = QueueMonitor::statistics();

    Mail::to('admin@example.com')->send(
        new DailyQueueReport($stats)
    );
})->dailyAt('08:00');
```

## Integration with Monitoring Tools

### Prometheus Metrics

Export metrics to Prometheus:

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->isFinished()) {
        Prometheus::histogram('queue_job_duration_ms', $job->duration_ms, [
            'queue' => $job->queue,
            'status' => $job->status->value,
        ]);

        Prometheus::gauge('queue_job_memory_mb', $job->memory_peak_mb ?? 0, [
            'job_class' => $job->job_class,
        ]);
    }
});
```

### DataDog Integration

```php
use DataDog\DogStatsd;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->isFinished()) {
        $statsd = app(DogStatsd::class);

        $statsd->timing('queue.job.duration', $job->duration_ms, [
            'queue' => $job->queue,
            'status' => $job->status->value,
        ]);

        $statsd->increment('queue.job.completed', 1, [
            'status' => $job->status->value,
        ]);
    }
});
```

## Testing Helpers

### Factory States

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

// Create specific job scenarios
$slowJob = JobMonitor::factory()->slow(10000)->create();
$failedJob = JobMonitor::factory()->failed()->create();
$horizonJob = JobMonitor::factory()->horizon()->create();
$taggedJob = JobMonitor::factory()->withTags(['priority', 'email'])->create();

// Chain multiple states
$job = JobMonitor::factory()
    ->failed()
    ->horizon()
    ->withTags(['critical'])
    ->create();
```

### Testing Job Replay

```php
use Illuminate\Support\Facades\Queue;

test('job replay dispatches to correct queue', function() {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create([
        'queue' => 'emails',
        'connection' => 'redis',
    ]);

    QueueMonitor::replay($job->uuid);

    Queue::assertPushedOn('emails');
});
```

## Advanced Filtering

### Complex Query Building

```php
use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED, JobStatus::TIMEOUT],
    queues: ['emails', 'notifications'],
    jobClasses: [
        'App\\Jobs\\SendEmail',
        'App\\Jobs\\SendNotification',
    ],
    serverNames: ['web-1', 'web-2'],
    tags: ['priority'],
    queuedAfter: Carbon::now()->subHours(24),
    minDurationMs: 1000,
    maxDurationMs: 10000,
    search: 'timeout',
    limit: 100,
    sortBy: 'duration_ms',
    sortDirection: 'desc'
);

$jobs = QueueMonitor::getJobs($filters);
```

### Dynamic Filtering

```php
$filters = new JobFilterData(
    statuses: request('statuses') ?
        array_map(fn($s) => JobStatus::from($s), request('statuses')) :
        null,
    queues: request('queues'),
    search: request('search'),
    limit: min(request('limit', 50), 1000)
);

$jobs = QueueMonitor::getJobs($filters);
```

## Bulk Operations

### Bulk Replay

```php
$failedJobs = QueueMonitor::getFailedJobs(100);

foreach ($failedJobs as $job) {
    try {
        QueueMonitor::replay($job->uuid);
        Log::info("Replayed: {$job->uuid}");
    } catch (\Exception $e) {
        Log::error("Replay failed for {$job->uuid}: {$e->getMessage()}");
    }

    // Rate limit to avoid overwhelming queue
    usleep(100000); // 100ms delay
}
```

### Bulk Deletion

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

// Delete all completed jobs older than 7 days
JobMonitor::successful()
    ->where('completed_at', '<', now()->subDays(7))
    ->chunk(100, function($jobs) {
        foreach ($jobs as $job) {
            QueueMonitor::delete($job->uuid);
        }
    });
```

## Security Considerations

### API Authentication

```php
// config/queue-monitor.php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

### Payload Redaction

While the package stores full payloads by default, you can implement custom redaction:

```php
// In your job class
use Cbox\LaravelQueueMonitor\Traits\MonitorsJobs;

class ProcessPaymentJob implements ShouldQueue
{
    use MonitorsJobs;

    public function __construct(
        public array $paymentData
    ) {}

    // Override serialization to redact sensitive data
    public function __sleep(): array
    {
        // Redact credit card before serialization
        $this->paymentData['card_number'] = '****';

        return ['paymentData'];
    }
}
```

### IP Whitelisting

```php
// Custom middleware
class WhitelistQueueMonitorApi
{
    public function handle($request, Closure $next)
    {
        $allowedIps = config('queue-monitor.allowed_ips', []);

        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            abort(403, 'Unauthorized IP');
        }

        return $next($request);
    }
}

// config/queue-monitor.php
'api' => [
    'middleware' => ['api', WhitelistQueueMonitorApi::class],
],
```

## Performance at Scale

### Database Partitioning

For high-volume applications, consider partitioning:

```sql
-- MySQL partition by month
ALTER TABLE queue_monitor_jobs
PARTITION BY RANGE (TO_DAYS(created_at)) (
    PARTITION p202501 VALUES LESS THAN (TO_DAYS('2025-02-01')),
    PARTITION p202502 VALUES LESS THAN (TO_DAYS('2025-03-01')),
    -- Add partitions monthly
);
```

### Read Replicas

Use read replicas for analytics:

```php
// config/queue-monitor.php
'database' => [
    'connection' => app()->environment('production')
        ? 'mysql_analytics'
        : 'mysql',
],
```

### Async Pruning

```php
use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;

class PruneQueueMonitorJob implements ShouldQueue
{
    public function handle(PruneJobsAction $action): void
    {
        $deleted = $action->execute(days: 30);

        Log::info("Pruned {$deleted} queue monitor jobs");
    }
}

// Dispatch daily
Schedule::job(new PruneQueueMonitorJob)->daily();
```

## Custom Statistics

### Building Custom Reports

```php
use Illuminate\Support\Facades\DB;

class CustomQueueAnalytics
{
    public function getHourlyJobCount(): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return DB::table($prefix.'jobs')
            ->select([
                DB::raw('DATE_FORMAT(queued_at, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(duration_ms) as avg_duration'),
            ])
            ->whereBetween('queued_at', [now()->subDay(), now()])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    public function getTopFailingJobs(int $limit = 10): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return DB::table($prefix.'jobs')
            ->select([
                'job_class',
                DB::raw('COUNT(*) as failure_count'),
                DB::raw('MAX(completed_at) as last_failure'),
            ])
            ->where('status', 'failed')
            ->whereDate('completed_at', '>=', now()->subDays(7))
            ->groupBy('job_class')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
```

## Extending the Package

### Custom Actions

Create custom actions following the pattern:

```php
namespace App\Actions;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class ArchiveOldJobsAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository
    ) {}

    public function execute(int $days): int
    {
        $jobs = JobMonitor::where('completed_at', '<', now()->subDays($days))
            ->get();

        foreach ($jobs as $job) {
            // Archive to S3 or external storage
            Storage::put("archives/{$job->uuid}.json", $job->toJson());

            // Delete from database
            $this->repository->delete($job->uuid);
        }

        return $jobs->count();
    }
}
```

### Custom Events

Dispatch additional events:

```php
namespace App\Events;

class CriticalJobFailed
{
    public function __construct(
        public JobMonitor $job
    ) {}
}

// In event listener
if ($job->job_class === 'App\\Jobs\\CriticalJob' && $job->status->isFailed()) {
    event(new CriticalJobFailed($job));
}
```

## Best Practices

### 1. Tag Everything

Use tags liberally for better analytics:

```php
public function tags(): array
{
    return [
        'environment:' . app()->environment(),
        'tenant:' . tenant()->id,
        'priority:' . $this->priority,
        'category:email',
    ];
}
```

### 2. Meaningful Display Names

```php
public function displayName(): string
{
    return "Send Welcome Email to User #{$this->userId}";
}
```

### 3. Monitor Resource Usage

```php
// Alert on high memory usage
if ($job->memory_peak_mb > 500) {
    Alert::send("High memory job: {$job->job_class}");
}
```

### 4. Regular Pruning

```php
// Keep only 7 days of successful jobs
Schedule::command('queue-monitor:prune', [
    '--days' => 7,
    '--statuses' => 'completed'
])->daily();

// Keep failures for 30 days
Schedule::command('queue-monitor:prune', [
    '--days' => 30,
    '--statuses' => 'failed,timeout'
])->weekly();
```

### 5. Use Facade for Queries

```php
// Good: Type-safe facade
$job = QueueMonitor::getJob($uuid);

// Avoid: Direct model queries for monitoring logic
$job = JobMonitor::where('uuid', $uuid)->first();
```
