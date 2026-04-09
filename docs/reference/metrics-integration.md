---
title: "Queue-Metrics Integration"
description: "Seamless integration with laravel-queue-metrics for enhanced resource tracking"
weight: 3
---

# Queue-Metrics Integration

Queue Monitor for Laravel is built on top of [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) and automatically integrates with its event system for enhanced resource tracking.

## How Integration Works

The integration happens through event subscription:

```
laravel-queue-metrics fires MetricsRecorded event
              ↓
QueueMetricsSubscriber listens
              ↓
UpdateJobMetricsAction extracts data
              ↓
JobMonitorRepository updates job record
              ↓
CPU, memory, FD metrics stored
```

## Metrics Captured

### CPU Time

**Source**: ProcessMetrics tracking in queue-metrics
**Field**: `cpu_time_ms` (decimal)
**Calculation**: User + System CPU time in milliseconds

```php
$job = QueueMonitor::getJob($uuid);
echo "CPU Time: {$job->cpu_time_ms}ms";
```

### Memory Peak

**Source**: ProcessMetrics tracking in queue-metrics
**Field**: `memory_peak_mb` (decimal)
**Includes**: Parent + child process memory

```php
echo "Peak Memory: {$job->memory_peak_mb}MB";
```

### File Descriptors

**Source**: Custom metrics from queue-metrics (if available)
**Field**: `file_descriptors` (integer)
**Note**: May not be available on all systems

```php
if ($job->file_descriptors !== null) {
    echo "File Descriptors: {$job->file_descriptors}";
}
```

## Event Flow

### 1. Job Processing

```php
// Laravel Queue fires JobProcessing event
// queue-monitor records job start

// queue-metrics starts ProcessMetrics tracking
ProcessMetrics::start("job_{$jobId}");
```

### 2. Job Completion

```php
// Job finishes executing

// queue-metrics stops tracking and fires MetricsRecorded
ProcessMetrics::stop("job_{$jobId}");
event(new MetricsRecorded($metricsData));

// queue-monitor receives event
QueueMetricsSubscriber::handleMetricsRecorded($event);

// Metrics extracted and stored
UpdateJobMetricsAction::execute($metricsData, $jobId);
```

### 3. Data Storage

The `UpdateJobMetricsAction` extracts relevant metrics from the `JobMetricsData` DTO and updates the job record:

```php
$this->repository->update($jobMonitor->uuid, [
    'cpu_time_ms' => $metricsData->execution->cpuTimeMs,
    'memory_peak_mb' => $metricsData->memory->peakMb,
    'file_descriptors' => $metricsData->fileDescriptors ?? null,
]);
```

## Accessing Metrics

### Via Model

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$job = JobMonitor::find($id);

echo "CPU: {$job->cpu_time_ms}ms\n";
echo "Memory: {$job->memory_peak_mb}MB\n";
echo "Duration: {$job->duration_ms}ms\n";
```

### Via Facade

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

$job = QueueMonitor::getJob($uuid);

if ($job->cpu_time_ms !== null) {
    $cpuEfficiency = ($job->cpu_time_ms / $job->duration_ms) * 100;
    echo "CPU Efficiency: {$cpuEfficiency}%\n";
}
```

### Via API

```bash
curl /api/queue-monitor/jobs/{uuid}
```

Response includes metrics:

```json
{
  "data": {
    "uuid": "...",
    "metrics": {
      "cpu_time_ms": 245.50,
      "memory_peak_mb": 48.25,
      "file_descriptors": 15,
      "duration_ms": 1250,
      "duration_seconds": 1.25
    }
  }
}
```

## Analytics with Metrics

Statistics endpoints automatically include metric aggregations:

```php
$stats = QueueMonitor::statistics();

echo "Avg CPU Time: {$stats['avg_cpu_time_ms']}ms\n";
echo "Max Memory: {$stats['max_memory_mb']}MB\n";
echo "Avg Duration: {$stats['avg_duration_ms']}ms\n";
```

## Performance Analysis

### Slow Jobs

Find jobs with high resource usage:

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

// High CPU jobs
$cpuIntensive = JobMonitor::where('cpu_time_ms', '>', 1000)
    ->orderByDesc('cpu_time_ms')
    ->get();

// High memory jobs
$memoryIntensive = JobMonitor::where('memory_peak_mb', '>', 100)
    ->orderByDesc('memory_peak_mb')
    ->get();

// Slow jobs
$slowJobs = JobMonitor::slowJobs(5000) // > 5 seconds
    ->get();
```

### Per-Queue Resource Usage

```php
$queueStats = QueueMonitor::serverStatistics();

foreach ($queueStats as $stat) {
    echo "Queue: {$stat['queue']}\n";
    echo "Avg Duration: {$stat['avg_duration_ms']}ms\n";
    echo "Total Jobs: {$stat['total']}\n";
}
```

## Configuration

### Persistence

Queue-metrics has two layers: **instrumentation** (per-job CPU/memory events) and **persistence** (aggregate storage of workers, throughput, baselines).

Queue Monitor only needs the instrumentation layer. If you don't use [queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) or Prometheus export, you can disable persistence:

```php
// config/queue-metrics.php
'persistence' => [
    'enabled' => env('QUEUE_METRICS_PERSISTENCE', true),
],
```

With persistence off: per-job CPU/memory events still fire and Queue Monitor captures them. No storage backend required.

### Storage Backend (when persistence is enabled)

```php
'storage' => [
    // 'redis' (default, recommended) or 'database'
    'driver' => env('QUEUE_METRICS_STORAGE', 'redis'),
    'connection' => env('QUEUE_METRICS_CONNECTION', 'default'),
    'prefix' => 'queue_metrics',
    // Recommended: 1000 for Redis, 500 for database
    'max_samples_per_key' => env('QUEUE_METRICS_MAX_SAMPLES', 1000),
],
```

**Redis** is the recommended driver. **Database** is available for low-scale workloads (< 10 workers). See the [installation guide](../getting-started/installation) for setup.

### Metrics Collection

Queue-metrics instruments jobs automatically — no configuration needed beyond persistence and storage settings.

### Integration Settings

Queue-monitor automatically subscribes to metrics events:

```php
// No configuration needed - automatic integration
```

## Troubleshooting

### Metrics Not Appearing

1. **Verify queue-metrics is installed**:
   ```bash
   composer show cboxdk/laravel-queue-metrics
   ```

2. **Check metrics collection is enabled**:
   ```php
   config('queue-metrics.enabled'); // Should be true
   ```

3. **Verify events are firing**:
   ```php
   Event::listen(MetricsRecorded::class, function($event) {
       Log::info('Metrics recorded', ['data' => $event->metricsData]);
   });
   ```

### Partial Metrics

Some metrics may be unavailable on certain systems:
- **File descriptors**: Requires system support
- **CPU time**: Available on most *nix systems
- **Memory**: Always available via PHP

### Performance Impact

Metrics collection has minimal overhead:
- CPU tracking: <1% overhead
- Memory tracking: <0.1% overhead
- Sample rate can be adjusted to reduce load

## Best Practices

1. **Enable in all environments** - Metrics are valuable in dev, staging, and production
2. **Use sample rates in high-traffic** - Reduce overhead with sampling
3. **Monitor the monitors** - Track queue-monitor's own performance
4. **Correlate with application metrics** - Combine with APM tools
5. **Set up alerts** - React to resource anomalies automatically
