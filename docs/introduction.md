---
title: "Introduction"
description: "Laravel Queue Monitor tracks individual jobs with payload storage and replay capability"
weight: 1
---

# Introduction

**Laravel Queue Monitor** is a production-ready package for monitoring individual queue jobs in Laravel applications. Unlike aggregate metrics packages, Queue Monitor tracks every single job from queue to completion, stores payloads for replay, and provides comprehensive analytics.

## What Makes It Different

### Individual Job Tracking
Most queue monitoring solutions provide aggregate metrics (jobs per minute, average duration). Queue Monitor tracks **every individual job** with complete lifecycle visibility.

### Job Replay
Failed jobs can be replayed from stored payloads - perfect for development debugging and production recovery scenarios.

### Worker & Server Tracking
Know exactly which server and worker processed each job, with automatic Horizon vs queue:work detection.

### Resource Metrics
Track CPU time, memory usage, and file descriptors for each job (via integration with laravel-queue-metrics).

## Key Features

- **Individual Job Records** - Every job tracked with unique UUID
- **Payload Storage** - Full job payload saved for replay
- **Retry Chain Tracking** - Complete visibility into retry attempts
- **Server Identification** - Track which server/worker processed each job
- **Resource Metrics** - CPU, memory, duration for each job
- **Tag Organization** - Categorize jobs with custom tags
- **Comprehensive API** - 14 REST endpoints for dashboards
- **Job Replay** - Re-dispatch jobs from stored payloads
- **Failure Analytics** - Pattern detection and trend analysis
- **Queue Health** - Real-time health scoring per queue

## Quick Example

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

// Get failed jobs from last 24 hours
$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queuedAfter: Carbon::now()->subHours(24)
);

$failedJobs = QueueMonitor::getJobs($filters);

// Replay each failed job
foreach ($failedJobs as $job) {
    try {
        $replayData = QueueMonitor::replay($job->uuid);
        echo "Replayed: {$replayData->newJobId}\n";
    } catch (\Exception $e) {
        echo "Replay failed: {$e->getMessage()}\n";
    }
}

// View statistics
$stats = QueueMonitor::statistics();
echo "Success Rate: {$stats['success_rate']}%\n";
echo "Average Duration: {$stats['avg_duration_ms']}ms\n";
```

## Architecture

Built on solid architectural patterns:

- **Action Pattern** - All business logic in single-responsibility actions
- **DTO Pattern** - Type-safe data transfer objects
- **Repository Pattern** - Clean data access layer
- **Event-Driven** - Loosely coupled via events
- **SOLID Principles** - Maintainable and extensible

## Requirements

- PHP 8.3+
- Laravel 10+
- **cboxdk/laravel-queue-metrics** ^1.0

## Next Steps

- [Installation Guide](installation) - Get started in 5 minutes
- [Facade Usage](facade-usage) - Learn the programmatic API
- [Job Replay](job-replay) - Master the replay system
- [API Reference](api-reference) - Complete REST API docs
