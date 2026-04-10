# Queue Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cboxdk/laravel-queue-monitor.svg?style=flat-square)](https://packagist.org/packages/cboxdk/laravel-queue-monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/cboxdk/laravel-queue-monitor.svg?style=flat-square)](https://packagist.org/packages/cboxdk/laravel-queue-monitor)

**Deep queue monitoring for any Laravel queue driver. Not just Horizon.**

Track every queue job with per-job CPU, memory, payload, exceptions, and retry chains on `database`, `redis`, `sqs`, `beanstalkd`, or any driver that fires Laravel's queue events. No Redis required. No Horizon required.

## Why?

Laravel Horizon works if you run Redis. Most other monitoring tools stop there. If you run database queues, SQS, or anything else, you're flying blind.

Queue Monitor gives you the same depth of insight on any driver:

- Per-job CPU time and memory usage (via [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics))
- Full exception traces with retry chain history
- Job payload storage and replay
- Per-queue, per-server, per-job-class analytics
- SLA compliance tracking
- Health checks and alerting

Works with Horizon and enriches the experience with worker supervisor data, but doesn't need it.

## Features

- **Driver Agnostic**: `database`, `redis`, `sqs`, `beanstalkd`, any custom driver
- **Individual Job Tracking**: Every job from dispatch to completion/failure
- **Per-Job Resource Metrics**: CPU time, peak memory (RSS), file descriptors via process-level instrumentation
- **Full Retry Chain**: Every attempt preserved with its own exception, duration, and metrics
- **Job Replay**: Re-dispatch failed jobs from stored payloads
- **Web Dashboard**: Full-page job detail, drill-down views, deep-linkable URLs
- **Terminal Dashboard**: k9s-style TUI with keyboard navigation (`php artisan queue-monitor:dashboard`)
- **Analytics**: Per-queue, per-server, per-job-class breakdowns with p50/p95/p99 percentiles
- **Infrastructure**: Worker utilization, queue capacity, SLA compliance, [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) integration
- **Health Checks**: Stuck job detection, error rate monitoring, queue backlog alerts
- **REST API**: Paginated, filterable, with payload redaction
- **Horizon Integration**: Optional supervisor data, workload metrics, jobs/minute (auto-detected)
- **Autoscale Integration**: Optional scaling timeline, SLA breach/recovery events (with [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale))

## Requirements

- PHP 8.3+
- Laravel 11+
- [cboxdk/laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) ^2.3 (installed automatically)

## Installation

```bash
composer require cboxdk/laravel-queue-monitor
```

```bash
php artisan vendor:publish --tag="queue-monitor-config"
php artisan migrate
```

That's it. The package automatically starts monitoring all queue jobs.

### Metrics Storage

Queue Monitor depends on [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) for per-job CPU/memory instrumentation. By default, queue-metrics also persists aggregate data (worker heartbeats, throughput, baselines) to a storage backend. Queue Monitor only needs the per-job events, not the aggregate persistence.

If you only use Queue Monitor, you can disable metrics persistence to avoid any storage backend requirement:

```env
QUEUE_METRICS_PERSISTENCE=false
```

This gives you per-job CPU and memory tracking with zero additional infrastructure. No Redis, no extra tables.

> **Note:** [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) requires persistence enabled. It reads worker heartbeats, throughput, and baselines from queue-metrics to make scaling decisions.

#### With persistence enabled (default)

Keep persistence enabled if you want the full metrics stack (Prometheus export, baselines, worker heartbeats) or use queue-autoscale. Two storage options:

**Redis (recommended):**

```env
QUEUE_METRICS_STORAGE=redis
QUEUE_METRICS_CONNECTION=default
```

**Database** (for low-scale workloads without Redis):

```env
QUEUE_METRICS_STORAGE=database
```

```bash
php artisan vendor:publish --tag="queue-metrics-migrations"
php artisan migrate
```

> **Performance note:** The database driver is for low-scale workloads (< 10 workers). At higher scale, metrics writes compete with your queue jobs for database connections. Set `QUEUE_METRICS_MAX_SAMPLES=500` to keep table sizes manageable. See the [laravel-queue-metrics docs](https://github.com/cboxdk/laravel-queue-metrics) for details.

### Optional: Publish views for customization

```bash
php artisan vendor:publish --tag="queue-monitor-views"
```

## Quick Start

### Web Dashboard

Navigate to `/queue-monitor` in your browser. All views are deep-linkable:

| URL | View |
|-----|------|
| `/queue-monitor` | Overview |
| `/queue-monitor#jobs` | Jobs list with filters |
| `/queue-monitor#analytics` | Analytics |
| `/queue-monitor#health` | Health checks |
| `/queue-monitor#infrastructure` | Infrastructure |
| `/queue-monitor/job/{uuid}` | Job detail (shareable) |
| `/queue-monitor/queue/{name}` | Queue drill-down |
| `/queue-monitor/server/{name}` | Server drill-down |
| `/queue-monitor/class/{fqcn}` | Job class drill-down |

### Terminal Dashboard

```bash
php artisan queue-monitor:dashboard
```

k9s-style keyboard navigation: `1-6` switch views, `j/k` navigate, `Enter` for detail, `S` filter status, `W` filter queue, `F` search.

### Facade

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

$job = QueueMonitor::getJob($uuid);
$replayData = QueueMonitor::replay($uuid);
$stats = QueueMonitor::statistics();

$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queuedAfter: Carbon::now()->subHours(24)
);
$failedJobs = QueueMonitor::getJobs($filters);
```

### Artisan Commands

```bash
php artisan queue-monitor:stats
php artisan queue-monitor:replay {uuid}
php artisan queue-monitor:prune --days=30 --statuses=completed
```

### REST API

```
GET  /api/queue-monitor/jobs?statuses[]=failed&limit=50
GET  /api/queue-monitor/jobs/{uuid}
POST /api/queue-monitor/jobs/{uuid}/replay
GET  /api/queue-monitor/statistics
GET  /api/queue-monitor/statistics/queue-health
```

## Driver Compatibility

| Driver | Job Tracking | Payload/Replay | CPU/Memory | Retry Chain | Health Checks |
|--------|:-----------:|:--------------:|:----------:|:-----------:|:-------------:|
| `database` | yes | yes | yes | yes | yes |
| `redis` | yes | yes | yes | yes | yes |
| `sqs` | yes | yes | yes | yes | yes |
| `beanstalkd` | yes | yes | yes | yes | yes |
| Custom drivers | yes | yes | yes | yes | yes |

**With Horizon (optional):** adds worker supervisor data, workload metrics, jobs/minute, busy/total workers.

**With [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) (optional):** adds scaling timeline, SLA breach/recovery tracking, scaling decision history.

### Cbox Ecosystem

Queue Monitor is part of a suite of first-party packages:

| Package | Purpose |
|---------|---------|
| [cboxdk/laravel-queue-monitor](https://github.com/cboxdk/laravel-queue-monitor) | Per-job tracking, dashboard, replay, analytics |
| [cboxdk/laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) | Process-level CPU/memory instrumentation |
| [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) | SLA-based predictive autoscaling |
| [cboxdk/system-metrics](https://github.com/cboxdk/system-metrics) | Low-level system metrics (RSS, CPU) for PHP |

Install any combination. They auto-discover each other via events.

## Configuration

Key options in `config/queue-monitor.php`:

```php
return [
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    'storage' => [
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', true),
        'payload_max_size' => 65535,
    ],

    'retention' => [
        'days' => 30,
        'prune_statuses' => ['completed'],
    ],

    // Web dashboard
    'ui' => [
        'enabled' => true,
        'route_prefix' => 'queue-monitor',
        'middleware' => ['web'],
    ],

    // REST API
    'api' => [
        'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
        'prefix' => 'api/queue-monitor',
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
```

## Security

### Dashboard Authentication

```php
// In AuthServiceProvider or a service provider
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

LaravelQueueMonitor::auth(function ($request) {
    return $request->user()?->isAdmin();
});
```

### API Authentication

The REST API exposes job payloads and exception traces. Always add auth middleware in production:

```php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

### Payload Redaction

The API automatically masks sensitive keys (`password`, `token`, `secret`, etc.). Raw payloads are stored for replay; only API responses are redacted.

## Architecture

- **Action Pattern**: Single-responsibility action classes for all business logic
- **Repository Pattern**: Data access through contracts with Eloquent implementations
- **Event-Driven**: Listens to Laravel queue events (driver-agnostic) and queue-metrics events
- **DTO Pattern**: Strictly-typed data transfer objects throughout

## Testing

```bash
composer test        # Pest test suite
composer analyse     # PHPStan Level 9
composer format      # Laravel Pint
```

## Credits

- [Sylvester Damgaard](https://github.com/Cbox)
- Built with [laravel-package-tools](https://github.com/spatie/laravel-package-tools)

## License

The MIT License (MIT). See [License File](LICENSE.md).
