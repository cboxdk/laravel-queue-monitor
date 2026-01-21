---
title: "Events"
description: "Event-driven architecture with Laravel Queue and package-specific events"
weight: 50
---

# Events

The package uses an event-driven architecture for loose coupling and extensibility.

## Laravel Queue Events (Listeners)

The package automatically listens to Laravel's built-in queue events:

### JobQueued

Fired when a job is pushed to the queue.

**Listener**: `JobQueuedListener`
**Action**: `RecordJobQueuedAction`
**Data Captured**:
- Job class and payload
- Queue and connection
- Server and worker context
- Tags (if job implements `tags()` method)

### JobProcessing

Fired when a worker picks up a job for processing.

**Listener**: `JobProcessingListener`
**Action**: `RecordJobStartedAction`
**Data Updated**:
- Status → processing
- Job ID (Laravel's internal ID)
- Started timestamp
- Attempt number

### JobProcessed

Fired when a job completes successfully.

**Listener**: `JobProcessedListener`
**Action**: `RecordJobCompletedAction`
**Data Updated**:
- Status → completed
- Completed timestamp
- Duration calculation
- Tag storage (normalized)

### JobFailed

Fired when a job fails with an exception.

**Listener**: `JobFailedListener`
**Action**: `RecordJobFailedAction`
**Data Updated**:
- Status → failed
- Exception details (class, message, trace)
- Completed timestamp
- Duration calculation

### JobTimedOut

Fired when a job exceeds its timeout limit.

**Listener**: `JobTimedOutListener`
**Action**: `RecordJobTimeoutAction`
**Data Updated**:
- Status → timeout
- Timeout exception details
- Completed timestamp

## Queue-Metrics Events (Subscriber)

### MetricsRecorded

Fired by laravel-queue-metrics when job metrics are recorded.

**Subscriber**: `QueueMetricsSubscriber`
**Action**: `UpdateJobMetricsAction`
**Data Enriched**:
- CPU time (milliseconds)
- Memory peak (megabytes)
- File descriptors (if available)

**Event Structure**:
```php
use Cbox\LaravelQueueMetrics\Events\MetricsRecorded;

// Event contains:
$event->metricsData; // JobMetricsData DTO
```

## Package Events

These events are dispatched by the queue-monitor package itself:

### JobMonitorRecorded

Fired when a job monitor record is created or updated.

**When**: After any job record modification
**Use Cases**: Real-time dashboards, alerting systems, logging

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->status->isFailed()) {
        // Send alert
    }
});
```

### JobReplayRequested

Fired when a job replay is initiated.

**When**: After successful job replay dispatch
**Use Cases**: Audit logging, replay tracking, notifications

```php
use Cbox\LaravelQueueMonitor\Events\JobReplayRequested;

Event::listen(JobReplayRequested::class, function($event) {
    Log::info('Job replayed', [
        'original_uuid' => $event->originalJob->uuid,
        'new_uuid' => $event->replayData->newUuid,
        'queue' => $event->replayData->queue,
    ]);
});
```

### JobCancelled

Fired when a job is manually cancelled.

**When**: After successful job cancellation
**Use Cases**: Audit trail, cleanup operations, notifications

```php
use Cbox\LaravelQueueMonitor\Events\JobCancelled;

Event::listen(JobCancelled::class, function($event) {
    Log::warning('Job cancelled', [
        'uuid' => $event->jobMonitor->uuid,
        'job_class' => $event->jobMonitor->job_class,
    ]);
});
```

## Event Subscriber Pattern

For multiple related events, use subscribers:

```php
namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;
use Cbox\LaravelQueueMonitor\Events\JobReplayRequested;
use Cbox\LaravelQueueMonitor\Events\JobCancelled;

class QueueMonitorEventSubscriber
{
    public function handleJobRecorded(JobMonitorRecorded $event): void
    {
        // Handle job recorded
    }

    public function handleJobReplayed(JobReplayRequested $event): void
    {
        // Handle job replay
    }

    public function handleJobCancelled(JobCancelled $event): void
    {
        // Handle job cancellation
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            JobMonitorRecorded::class,
            [self::class, 'handleJobRecorded']
        );

        $events->listen(
            JobReplayRequested::class,
            [self::class, 'handleJobReplayed']
        );

        $events->listen(
            JobCancelled::class,
            [self::class, 'handleJobCancelled']
        );
    }
}
```

Register in `EventServiceProvider`:

```php
protected $subscribe = [
    QueueMonitorEventSubscriber::class,
];
```

## Use Cases

### Real-Time Alerting

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;
use Illuminate\Support\Facades\Notification;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->status->isFailed() && $job->job_class === 'App\\Jobs\\CriticalJob') {
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new CriticalJobFailed($job));
    }
});
```

### Custom Metrics

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    if ($job->isFinished()) {
        Metrics::timing('queue.job.duration', $job->duration_ms, [
            'queue' => $job->queue,
            'status' => $job->status->value,
        ]);
    }
});
```

### Automatic Retry for Specific Failures

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

Event::listen(JobMonitorRecorded::class, function($event) {
    $job = $event->jobMonitor;

    // Auto-replay transient failures
    if ($job->status->isFailed() &&
        str_contains($job->exception_message, 'Connection timeout') &&
        $job->attempt < $job->max_attempts) {

        QueueMonitor::replay($job->uuid);
    }
});
```

### Audit Logging

```php
use Cbox\LaravelQueueMonitor\Events\JobReplayRequested;

Event::listen(JobReplayRequested::class, function($event) {
    DB::table('audit_logs')->insert([
        'action' => 'job_replayed',
        'user_id' => auth()->id(),
        'original_job_uuid' => $event->originalJob->uuid,
        'new_job_uuid' => $event->replayData->newUuid,
        'created_at' => now(),
    ]);
});
```

## Event Best Practices

1. **Keep listeners fast** - Offload heavy work to queued listeners or jobs
2. **Handle failures gracefully** - Don't break queue operations with listener errors
3. **Use queued listeners** - For non-critical operations that can be async
4. **Type-hint events** - Use specific event classes, not generic Event
5. **Test event flow** - Ensure events fire in expected scenarios
