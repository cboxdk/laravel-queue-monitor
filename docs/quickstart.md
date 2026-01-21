---
title: "Quick Start"
description: "Get started with Laravel Queue Monitor in 5 minutes with essential examples"
weight: 4
---

# Quick Start

Get Laravel Queue Monitor up and running in 5 minutes.

## Installation

```bash
composer require cboxdk/laravel-queue-monitor
php artisan vendor:publish --tag="queue-monitor-config"
php artisan migrate
```

That's it! Jobs are now being monitored automatically.

## View Your First Job

Dispatch any job in your application:

```php
use App\Jobs\SendWelcomeEmail;

SendWelcomeEmail::dispatch($user);
```

Check it was monitored:

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

$recentJobs = QueueMonitor::getRecentJobs(10);

foreach ($recentJobs as $job) {
    echo "{$job->job_class}: {$job->status->label()}\n";
}
```

## View Statistics

```php
$stats = QueueMonitor::statistics();

echo "Total Jobs: {$stats['total']}\n";
echo "Success Rate: {$stats['success_rate']}%\n";
echo "Avg Duration: {$stats['avg_duration_ms']}ms\n";
```

## Replay a Failed Job

```php
// Find failed jobs
$failed = QueueMonitor::getFailedJobs(10);

// Replay first failed job
if ($failed->isNotEmpty()) {
    try {
        $replayData = QueueMonitor::replay($failed->first()->uuid);
        echo "Job replayed: {$replayData->newJobId}\n";
    } catch (\Exception $e) {
        echo "Replay failed: {$e->getMessage()}\n";
    }
}
```

## Use the REST API

The package provides a complete REST API:

```bash
# List failed jobs
curl http://localhost/api/queue-monitor/jobs?statuses[]=failed

# Get job details
curl http://localhost/api/queue-monitor/jobs/{uuid}

# Replay a job
curl -X POST http://localhost/api/queue-monitor/jobs/{uuid}/replay

# Get statistics
curl http://localhost/api/queue-monitor/statistics
```

## Filter Jobs

```php
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Carbon\Carbon;

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queues: ['emails'],
    queuedAfter: Carbon::now()->subHours(24),
    limit: 50
);

$failedEmails = QueueMonitor::getJobs($filters);
```

## Add Tags to Jobs

Make your jobs more discoverable:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailJob implements ShouldQueue
{
    public function tags(): array
    {
        return ['email', 'notifications', 'high-priority'];
    }
}
```

Query by tags:

```php
$filters = new JobFilterData(
    tags: ['high-priority']
);

$priorityJobs = QueueMonitor::getJobs($filters);
```

## Schedule Automatic Pruning

Keep your database clean:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue-monitor:prune', ['--days' => 30])
    ->daily()
    ->at('02:00');
```

## Use Artisan Commands

```bash
# View statistics in terminal
php artisan queue-monitor:stats

# Replay a job
php artisan queue-monitor:replay {uuid}

# Prune old completed jobs
php artisan queue-monitor:prune --days=30 --statuses=completed
```

## What's Next?

- [Facade Usage](facade-usage) - Learn all facade methods
- [Job Replay](job-replay) - Master the replay system
- [API Reference](api-reference) - Explore all endpoints
- [Advanced Usage](advanced-usage) - Custom monitoring and dashboards
- [Configuration](configuration) - Customize behavior
