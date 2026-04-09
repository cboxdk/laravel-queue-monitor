---
title: "Configuration"
description: "Configure database, payload storage, retention policies, and API settings"
weight: 1
---

# Configuration

## Database Configuration

Configure which database connection to use for monitoring data:

```php
'database' => [
    'connection' => env('QUEUE_MONITOR_DB_CONNECTION'),
    'table_prefix' => 'queue_monitor_',
],
```

This allows you to store monitoring data separately from your application data if desired.

## Payload Storage

Control how job payloads are stored for replay functionality:

```php
'storage' => [
    // Store complete job payload for replay capability
    'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', true),

    // Maximum payload size in bytes (default: 64KB)
    'payload_max_size' => 65535,
],
```

**Important**: Payload storage is required for job replay. If disabled, replay functionality will not work.

## Data Retention

Configure automatic cleanup of old job records:

```php
'retention' => [
    // Number of days to retain job records
    'days' => 30,

    // Which statuses to prune (empty array = prune all statuses)
    'prune_statuses' => ['completed'],
],
```

Run pruning manually or via scheduled task:

```php
// In app/Console/Kernel.php
$schedule->command('queue-monitor:prune')->daily();
```

## Worker Detection

Customize how workers and servers are identified:

```php
'worker_detection' => [
    // Custom callable for determining server name
    // If null, uses gethostname()
    'server_name_callable' => null,

    // Enable Horizon detection
    'horizon_detection' => true,
],
```

Example custom server name:

```php
'server_name_callable' => function() {
    return config('app.server_name', gethostname());
},
```

## REST API

Configure the REST API for external integrations:

```php
'api' => [
    'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
    'prefix' => 'api/queue-monitor',
    'middleware' => ['api'],
    'rate_limit' => '60,1', // 60 requests per minute
],
```

You can add custom middleware for authentication:

```php
'middleware' => ['api', 'auth:sanctum'],
```

## Metrics Storage

Queue Monitor depends on [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) for per-job CPU and memory instrumentation. Queue-metrics also provides aggregate persistence (worker heartbeats, throughput, baselines) — but Queue Monitor doesn't need it.

### Disable persistence (simplest setup)

If you only use Queue Monitor (not [queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale)), disable metrics persistence to skip any storage backend:

```env
QUEUE_METRICS_PERSISTENCE=false
```

Per-job CPU/memory still works — only aggregate persistence is skipped.

### With persistence enabled (default)

When persistence is on, configure a storage backend in `config/queue-metrics.php`:

```php
'persistence' => [
    'enabled' => env('QUEUE_METRICS_PERSISTENCE', true),
],

'storage' => [
    'driver' => env('QUEUE_METRICS_STORAGE', 'redis'),
    'connection' => env('QUEUE_METRICS_CONNECTION', 'default'),
    'prefix' => 'queue_metrics',
    // Recommended: 1000 for Redis, 500 for database driver
    'max_samples_per_key' => env('QUEUE_METRICS_MAX_SAMPLES', 1000),
],
```

**Redis** is the recommended driver. **Database** is available for low-scale workloads (< 10 workers) without Redis — see the [installation guide](../getting-started/installation) for setup.

For full metrics configuration options, see the [laravel-queue-metrics documentation](https://github.com/cboxdk/laravel-queue-metrics).

## Repository Bindings

Override default repository implementations:

```php
'repositories' => [
    JobMonitorRepositoryContract::class => CustomJobMonitorRepository::class,
    TagRepositoryContract::class => CustomTagRepository::class,
    StatisticsRepositoryContract::class => CustomStatisticsRepository::class,
],
```

## Action Bindings

Override default action implementations:

```php
'actions' => [
    'record_job_queued' => CustomRecordJobQueuedAction::class,
    'replay_job' => CustomReplayJobAction::class,
    // ... more actions
],
```
