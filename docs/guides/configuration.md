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
    'table_prefix' => env('QUEUE_MONITOR_TABLE_PREFIX', 'queue_monitor_'),
],
```

This allows you to store monitoring data separately from your application data if desired.

`table_prefix` is prepended to every table the package creates (`queue_monitor_jobs`, `queue_monitor_tags`, `queue_monitor_scaling_events`, `queue_monitor_cluster_events`). Change it only when the default names collide with existing tables or your naming conventions require something else. Set it **before** running the package migrations — the prefix is baked into the physical table names at migration time. Changing it after installation requires manually renaming the existing tables to match; the package does not rename tables or migrate data for you.

Dashboard analytics generate database-specific SQL for timestamp bucketing and queue pickup latency. The package supports MySQL/MariaDB, PostgreSQL, SQL Server, and SQLite for those expressions.

## Payload Storage

Control how job payloads are stored for replay functionality:

```php
'storage' => [
    // Store complete job payload for replay capability.
    // Defaults to enabled in local only; set QUEUE_MONITOR_STORE_PAYLOAD explicitly to override.
    'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', env('APP_ENV') === 'local'),

    // Maximum payload size in bytes (default: 64KB)
    'payload_max_size' => 65535,
],
```

**Important**: Payload storage is required for job replay. By default it is enabled in `local` and disabled outside `local` unless `QUEUE_MONITOR_STORE_PAYLOAD` is explicitly set. If disabled, replay functionality will not work.

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
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue-monitor:prune')->daily();
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
    'enabled' => env('QUEUE_MONITOR_API_ENABLED', env('APP_ENV') === 'local'),
    'prefix' => 'api/queue-monitor',
    'middleware' => ['api'],
    'rate_limit' => '60,1', // 60 requests per minute
],
```

The API defaults to enabled only in `local`. Set `QUEUE_MONITOR_API_ENABLED=true` explicitly in staging or production after adding authentication middleware and an authorization callback.

You can add custom middleware for authentication:

```php
'middleware' => ['api', 'auth:sanctum'],
```

## Dashboard Assets

Control how the built-in dashboard loads CSS and JavaScript:

```php
'ui' => [
    'assets' => [
        // inline, public, or none
        'mode' => env('QUEUE_MONITOR_ASSET_MODE', 'inline'),
        'url' => env('QUEUE_MONITOR_ASSET_URL'),
    ],
],
```

Use `inline` for zero setup, `public` after publishing `queue-monitor-assets`, and `none` when a published/custom dashboard view loads assets from your application's own build. See [Dashboard Assets](dashboard-assets) for the full override workflow.

## Health Checks

Tune health thresholds for your workload:

```php
'health' => [
    'stuck_job_minutes' => env('QUEUE_MONITOR_HEALTH_STUCK_JOB_MINUTES', 30),
    'error_rate_threshold' => env('QUEUE_MONITOR_HEALTH_ERROR_RATE_THRESHOLD', 10.0),
    'queued_jobs_threshold' => env('QUEUE_MONITOR_HEALTH_QUEUED_JOBS_THRESHOLD', 1000),
    'processing_jobs_threshold' => env('QUEUE_MONITOR_HEALTH_PROCESSING_JOBS_THRESHOLD', 100),
    'storage_max_mb' => env('QUEUE_MONITOR_HEALTH_STORAGE_MAX_MB', 1000),
],
```

Use the readiness mode before launch:

```bash
php artisan queue-monitor:health --readiness
php artisan queue-monitor:health --readiness --json
```

Readiness checks validate production-sensitive configuration such as access control, API middleware, payload storage, retention limits, and Horizon timeout alignment.

## Metrics Storage

Queue Monitor depends on [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) for per-job CPU and memory instrumentation. Queue-metrics also provides aggregate persistence (worker heartbeats, throughput, baselines), but Queue Monitor doesn't need it.

### Disable persistence (simplest setup)

If you only use Queue Monitor, disable metrics persistence to skip any storage backend:

```env
QUEUE_METRICS_PERSISTENCE=false
```

Per-job CPU/memory still works. Only aggregate persistence is skipped.

> **Note:** [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) requires persistence enabled. It reads worker heartbeats, throughput, and baselines from queue-metrics to make scaling decisions.

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

**Redis** is the recommended driver. **Database** is available for low-scale workloads (< 10 workers) without Redis. See the [installation guide](../getting-started/installation) for setup.

For full metrics configuration options, see the [laravel-queue-metrics documentation](https://github.com/cboxdk/laravel-queue-metrics).

## Scaling Timeline

The autoscale tab opens with a per-queue scaling timeline: job volume, duration,
memory, and the autoscaler's worker decisions rendered on one shared time axis, so
you can see how scaling reacted to load — and what it cost — at a glance.

- Job volume, duration, and memory come from the recorded jobs (bucketed per
  minute over a selectable 15m/1h/6h/24h window).
- The workers panel steps through the recorded scaling events: the solid line is
  the actual worker count, the dashed line the autoscaler's target.
- The strip above the charts shows the queue's live processing/waiting/delayed
  counts, the worker range observed in the window, and the worker memory limit.

Nothing needs configuring. The load panels work with the data Queue Monitor already
records; the workers panel appears when scaling events exist (i.e. when
[queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) is installed).

## Autoscale Failure Fuse

When [queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) trips its
failure fuse, it stops adding workers to a queue whose jobs are failing — scaling up a
queue whose jobs all fail just burns capacity faster. Queue Monitor records the trip,
the recovery probe, and the recovery as scaling events, so the dashboard can say why a
deep queue lost its workers instead of leaving it looking like a malfunction.

The autoscale tab reports both the transitions and the current state:

- `scaling.history` — the moment each fuse tripped, probed or recovered
- `scaling.open_fuses` — the queues a fuse is still holding, and at how many workers
- `scaling.summary.fuse_trips` — trips in the last hour, counted separately from
  scaling decisions, because a trip is the autoscaler declining to make one

The distinction that matters is state versus transition. A fuse that tripped an hour
ago has scrolled off the timeline while the queue it holds is still at zero workers, so
`open_fuses` is what answers "why does this queue have no workers right now".

Nothing needs configuring — the events are bound automatically when the autoscale
package is installed.

## Autoscale Cluster Leadership

When [queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) runs in
cluster mode, one manager holds the lease and makes the scaling decisions. Taking that
lease discards worker placement, the anti-flapping window and the fair-share ledger's
position, because each of them describes a cluster the new leader has not observed.

One failover costs a cycle and is normal — a deploy causes one. Leadership that keeps
moving means none of that state ever completes, and the symptom on the dashboard is a
fleet that scales but never settles. Queue Monitor records a `leadership_unstable`
cluster event when the changes pile up:

```php
'cluster' => [
    'leadership_window_seconds' => env('QUEUE_MONITOR_LEADERSHIP_WINDOW', 60),
    'leadership_change_threshold' => env('QUEUE_MONITOR_LEADERSHIP_THRESHOLD', 3),
],
```

The default window matches the autoscaler's own anti-flapping window, which is the
yardstick every piece of discarded state is sized in. The event is recorded once per
window rather than once per change, so an unstable cluster does not bury the timeline
you are reading it from. Set the threshold below `2` to turn the check off.

The usual causes are a lease shorter than a scaling cycle takes, a manager the network
keeps partitioning, and a host whose clock has drifted past the lease TTL.

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
