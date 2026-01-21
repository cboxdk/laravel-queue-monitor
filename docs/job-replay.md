---
title: "Job Replay"
description: "Re-dispatch failed jobs from stored payloads with complete validation"
weight: 30
---

# Job Replay

Job replay allows you to re-dispatch failed or completed jobs using their stored payloads.

## Requirements

Job replay requires payload storage to be enabled:

```php
// config/queue-monitor.php
'storage' => [
    'store_payload' => true,
],
```

**Important**: Payloads are stored as-is, including all job properties and data. Ensure your payload size doesn't exceed the configured limit (default: 64KB).

## How It Works

1. When a job is queued, the package stores its complete serialized payload
2. On replay, the payload is extracted and re-dispatched to the original queue
3. A new job monitor record is created with a link to the original job

## Replaying Jobs

### Via Facade

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

$replayData = QueueMonitor::replay('9a123456-7890-1234-5678-90abcdef1234');

echo "Replayed to queue: {$replayData->queue}\n";
echo "New job ID: {$replayData->newJobId}\n";
```

### Via Artisan Command

```bash
php artisan queue-monitor:replay 9a123456-7890-1234-5678-90abcdef1234
```

### Via REST API

```bash
curl -X POST http://localhost/api/queue-monitor/jobs/{uuid}/replay
```

## Replay Validation

The package performs several validation checks before replaying:

1. **Job exists**: The UUID must correspond to an existing job record
2. **Payload stored**: The job must have a stored payload
3. **Job class exists**: The job class must still be available in your codebase
4. **Not processing**: Cannot replay jobs currently being processed
5. **Queue exists**: The target queue and connection must exist

## Retry Chain

When a job is replayed, it maintains a link to the original:

```php
$original = QueueMonitor::getJob($originalUuid);
$retries = QueueMonitor::getRetryChain($originalUuid);

foreach ($retries as $attempt) {
    echo "Attempt {$attempt->attempt}: {$attempt->status->label()}\n";

    if ($attempt->retried_from_id) {
        echo "  Retried from: {$attempt->retriedFrom->uuid}\n";
    }
}
```

## Use Cases

### Failed Job Recovery

```php
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

$failedJobs = JobMonitor::withStatus(JobStatus::FAILED)
    ->whereDate('completed_at', today())
    ->get();

foreach ($failedJobs as $job) {
    try {
        QueueMonitor::replay($job->uuid);
        Log::info("Replayed failed job: {$job->uuid}");
    } catch (\Exception $e) {
        Log::error("Failed to replay {$job->uuid}: {$e->getMessage()}");
    }
}
```

### Development/Testing

Replay production jobs in development:

```php
// On production
$job = QueueMonitor::getJob($uuid);
$payload = $job->payload;

// Copy payload to development database, then replay
QueueMonitor::replay($uuid);
```

### Bulk Replay

Replay multiple jobs with specific criteria:

```php
$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    jobClasses: ['App\\Jobs\\SendEmail'],
    completedAfter: Carbon::now()->subHours(1),
);

$jobs = QueueMonitor::getJobs($filters);

foreach ($jobs as $job) {
    QueueMonitor::replay($job->uuid);
}
```

## Limitations

1. **State Dependencies**: If a job depends on external state (database records, files, etc.), ensure that state still exists before replaying
2. **Idempotency**: Jobs should be idempotent to safely replay without side effects
3. **Payload Size**: Large payloads may hit storage limits
4. **Class Availability**: The job class must exist in your current codebase

## Events

Job replay triggers events you can listen for:

```php
use Cbox\LaravelQueueMonitor\Events\JobReplayRequested;

Event::listen(JobReplayRequested::class, function ($event) {
    Log::info("Job replayed: {$event->originalJob->uuid}");
    Log::info("New job ID: {$event->replayData->newJobId}");
});
```

## Best Practices

1. **Enable in Development**: Always enable payload storage in development and testing
2. **Disable in Production**: Consider disabling if payload storage is a concern
3. **Prune Regularly**: Remove old payloads to manage storage
4. **Monitor Replay Success**: Track replay success rates
5. **Add Replay Logic to Jobs**: Make jobs replay-safe with idempotency checks
