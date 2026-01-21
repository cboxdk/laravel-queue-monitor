---
title: "Facade Usage"
description: "Programmatic access to queue monitoring with type-safe facade methods"
weight: 20
---

# Facade Usage

The `QueueMonitor` facade provides programmatic access to all monitoring functionality.

## Getting Job Information

### Get Single Job

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

$job = QueueMonitor::getJob('9a123456-7890-1234-5678-90abcdef1234');

if ($job) {
    echo "Job: {$job->job_class}\n";
    echo "Status: {$job->status->label()}\n";
    echo "Duration: {$job->getDurationInSeconds()}s\n";
}
```

### Query Jobs with Filters

```php
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED, JobStatus::TIMEOUT],
    queues: ['emails', 'notifications'],
    limit: 100,
    sortBy: 'completed_at',
    sortDirection: 'desc'
);

$jobs = QueueMonitor::getJobs($filters);

foreach ($jobs as $job) {
    echo "{$job->job_class} failed at {$job->completed_at}\n";
}
```

### Get Failed Jobs

```php
$failedJobs = QueueMonitor::getFailedJobs(limit: 50);
```

### Get Recent Jobs

```php
$recentJobs = QueueMonitor::getRecentJobs(limit: 100);
```

## Job Replay

Replay a job from stored payload:

```php
try {
    $replayData = QueueMonitor::replay('9a123456-7890-1234-5678-90abcdef1234');

    echo "Original Job: {$replayData->originalUuid}\n";
    echo "New Job: {$replayData->newUuid}\n";
    echo "Dispatched to: {$replayData->queue}\n";
} catch (\RuntimeException $e) {
    echo "Replay failed: {$e->getMessage()}\n";
}
```

## Job Cancellation

Cancel a queued or processing job:

```php
$cancelled = QueueMonitor::cancel('9a123456-7890-1234-5678-90abcdef1234');

if ($cancelled) {
    echo "Job cancelled successfully\n";
} else {
    echo "Job not found or already finished\n";
}
```

## Retry Chain

Get all retry attempts for a job:

```php
$retryChain = QueueMonitor::getRetryChain('9a123456-7890-1234-5678-90abcdef1234');

echo "Total attempts: {$retryChain->count()}\n";

foreach ($retryChain as $attempt) {
    echo "Attempt {$attempt->attempt}: {$attempt->status->label()}\n";
}
```

## Statistics

### Global Statistics

```php
$stats = QueueMonitor::statistics();

echo "Total Jobs: {$stats['total']}\n";
echo "Success Rate: {$stats['success_rate']}%\n";
echo "Avg Duration: {$stats['avg_duration_ms']}ms\n";
echo "Peak Memory: {$stats['max_memory_mb']}MB\n";
```

### Server Statistics

```php
$serverStats = QueueMonitor::serverStatistics('web-1');

foreach ($serverStats as $server) {
    echo "Server: {$server['server_name']}\n";
    echo "Total: {$server['total']}\n";
    echo "Success Rate: {$server['success_rate']}%\n";
}
```

### Queue Health

```php
$health = QueueMonitor::queueHealth();

foreach ($health as $queue) {
    echo "Queue: {$queue['queue']}\n";
    echo "Health: {$queue['health_score']}% ({$queue['status']})\n";
    echo "Processing: {$queue['processing']}\n";
    echo "Failed (last hour): {$queue['failed']}\n";
}
```

## Maintenance

### Prune Old Jobs

```php
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

// Prune completed jobs older than 30 days
$deleted = QueueMonitor::prune(days: 30, statuses: ['completed']);

echo "Deleted {$deleted} job record(s)\n";

// Prune all jobs older than 60 days
$deleted = QueueMonitor::prune(days: 60);
```

## Using with Eloquent

Since the facade returns Eloquent models, you can use standard Eloquent methods:

```php
$job = QueueMonitor::getJob($uuid);

// Relationships
$parent = $job->retriedFrom;
$retries = $job->retries;
$tags = $job->tagRecords;

// Scopes
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$slowJobs = JobMonitor::slowJobs(5000) // > 5 seconds
    ->onQueue('emails')
    ->get();

$failedToday = JobMonitor::failed()
    ->whereDate('completed_at', today())
    ->count();
```

## Type-Safe Filtering

Using DTOs ensures type safety:

```php
use Carbon\Carbon;

$filters = new JobFilterData(
    statuses: [JobStatus::COMPLETED],
    queuedAfter: Carbon::now()->subHours(24),
    minDurationMs: 1000,
    maxDurationMs: 5000,
    search: 'ProcessOrder',
);

$jobs = QueueMonitor::getJobs($filters);
```
