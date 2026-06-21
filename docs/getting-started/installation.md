---
title: "Installation"
description: "Install and configure Queue Monitor for Laravel in your application"
weight: 2
---

# Installation

## Requirements

- PHP 8.3+
- Laravel 11+
- [cboxdk/laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) ^3.0 (installed automatically)

## Installation Steps

### 1. Install via Composer

```bash
composer require cboxdk/laravel-queue-monitor
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag="queue-monitor-config"
```

### 3. Run Migrations

```bash
php artisan migrate
```

The package will create four tables:
- `queue_monitor_jobs`: Stores individual job records
- `queue_monitor_tags`: Normalized tag storage for analytics
- `queue_monitor_scaling_events`: Autoscale integration (if used)
- `queue_monitor_cluster_events`: Autoscale v3 cluster orchestration events (if used)

### 4. Configure Metrics Storage

Queue Monitor depends on [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) for per-job CPU and memory instrumentation. By default, queue-metrics also persists aggregate data (worker heartbeats, throughput, baselines) to a storage backend.

**Queue Monitor only needs the per-job events, not the aggregate persistence.** If you only use Queue Monitor, you can disable persistence entirely:

```env
QUEUE_METRICS_PERSISTENCE=false
```

This gives you per-job CPU and memory tracking with zero additional infrastructure. No Redis, no extra database tables. Skip to step 5.

> **Note:** [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) requires persistence enabled. It reads worker heartbeats, throughput, and baselines from queue-metrics to make scaling decisions. Leave persistence on if you use autoscale.

#### With persistence enabled (default)

Keep persistence enabled if you want the full metrics stack (Prometheus export, baselines, worker heartbeats) or use queue-autoscale. Choose a storage backend:

**Redis (recommended):**

```env
QUEUE_METRICS_STORAGE=redis
QUEUE_METRICS_CONNECTION=default
```

No extra setup needed if you already have a Redis connection.

**Database** (for low-scale workloads without Redis):

```env
QUEUE_METRICS_STORAGE=database
```

Publish and run the metrics storage migration:

```bash
php artisan vendor:publish --tag="queue-metrics-migrations"
php artisan migrate
```

> **Performance note:** The database driver is for low-scale workloads (< 10 workers). At higher scale, metrics writes compete with your queue jobs for database connections. Set `QUEUE_METRICS_MAX_SAMPLES=500` to keep table sizes manageable.

For full configuration options, see the [laravel-queue-metrics documentation](https://github.com/cboxdk/laravel-queue-metrics).

### 5. Advanced Installation (Optional)

Publish migrations for customization:

```bash
php artisan vendor:publish --tag="queue-monitor-migrations"
```

Publish views for customization:

```bash
php artisan vendor:publish --tag="queue-monitor-views"
```

Published views are copied into your application and will not receive package UI updates automatically. After upgrading Queue Monitor, remove or republish customized views if you want the latest dashboard.

The dashboard ships with precompiled package-local CSS and JavaScript. If your CSP requires external static files instead of inline package assets, publish them with:

```bash
php artisan vendor:publish --tag="queue-monitor-assets"
```

Then set:

```env
QUEUE_MONITOR_ASSET_MODE=public
```

For a full app-level build override, also publish the source asset files:

```bash
php artisan vendor:publish --tag="queue-monitor-asset-sources"
```

See [Dashboard Assets](../guides/dashboard-assets) for `inline`, `public`, and `none` asset modes.

## Configuration

The configuration file is published to `config/queue-monitor.php`. Key settings include:

```php
return [
    // Enable/disable monitoring
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    // Store job payloads for replay
    'storage' => [
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', env('APP_ENV') === 'local'),
    ],

    // Data retention settings
    'retention' => [
        'days' => 30,
        'prune_statuses' => ['completed'],
    ],

    // Batch operation limits
    'batch' => [
        'chunk_size' => env('QUEUE_MONITOR_BATCH_CHUNK_SIZE', 100),
        'max_jobs' => env('QUEUE_MONITOR_BATCH_MAX_JOBS', 1000),
    ],

    // REST API settings
    'api' => [
        'enabled' => env('QUEUE_MONITOR_API_ENABLED', env('APP_ENV') === 'local'),
        'prefix' => 'api/queue-monitor',
        'middleware' => ['api'],
        'default_limit' => env('QUEUE_MONITOR_API_DEFAULT_LIMIT', 50),
        'max_limit' => env('QUEUE_MONITOR_API_MAX_LIMIT', 1000),
    ],

    // Export limits
    'export' => [
        'default_limit' => env('QUEUE_MONITOR_EXPORT_DEFAULT_LIMIT', 1000),
        'max_rows' => env('QUEUE_MONITOR_EXPORT_MAX_ROWS', 5000),
    ],

    // Web dashboard assets
    'ui' => [
        'assets' => [
            'mode' => env('QUEUE_MONITOR_ASSET_MODE', 'inline'),
        ],
    ],
];
```

## Production Checklist

- Add framework auth middleware to the UI and API routes.
- Register `LaravelQueueMonitor::auth(...)` with an explicit admin/internal access rule.
- Enable the REST API explicitly outside local development with `QUEUE_MONITOR_API_ENABLED=true`.
- Schedule `queue-monitor:prune` so retention settings actually run.
- Decide whether `QUEUE_MONITOR_STORE_PAYLOAD=true` is acceptable for your data. Payload storage defaults on in `local` and off outside `local` unless the env var is explicitly set. Payload redaction only applies to API/dashboard responses; raw payloads are stored for replay.
- If you published `config/queue-monitor.php` before this default changed, update the published `storage.store_payload` and `api.enabled` entries manually.
- Review Content Security Policy. The bundled dashboard uses package-local CSS and JavaScript, inlined from `resources/dist`; publish `queue-monitor-assets` and customize the view if your CSP disallows inline assets.
- If you have published `queue-monitor-views`, remove or republish them after package upgrades to pick up dashboard fixes.
- Run `php artisan queue-monitor:health --readiness` in staging or production to catch unsafe launch configuration.

## Environment Variables

```env
# Queue Monitor
QUEUE_MONITOR_ENABLED=true
QUEUE_MONITOR_STORE_PAYLOAD=false # defaults true only in local
QUEUE_MONITOR_API_ENABLED=false   # defaults true only in local
QUEUE_MONITOR_API_DEFAULT_LIMIT=50
QUEUE_MONITOR_API_MAX_LIMIT=1000
QUEUE_MONITOR_EXPORT_DEFAULT_LIMIT=1000
QUEUE_MONITOR_EXPORT_MAX_ROWS=5000
QUEUE_MONITOR_BATCH_CHUNK_SIZE=100
QUEUE_MONITOR_BATCH_MAX_JOBS=1000
QUEUE_MONITOR_HEALTH_STUCK_JOB_MINUTES=30
QUEUE_MONITOR_HEALTH_ERROR_RATE_THRESHOLD=10
QUEUE_MONITOR_HEALTH_QUEUED_JOBS_THRESHOLD=1000
QUEUE_MONITOR_HEALTH_PROCESSING_JOBS_THRESHOLD=100
QUEUE_MONITOR_HEALTH_STORAGE_MAX_MB=1000

# Metrics Storage (from laravel-queue-metrics)
QUEUE_METRICS_STORAGE=redis          # redis (default) or database
QUEUE_METRICS_CONNECTION=default     # Redis or database connection name
QUEUE_METRICS_MAX_SAMPLES=1000       # Recommended: 500 for database driver
```

## Next Steps

- [Configuration Guide](../guides/configuration)
- [API Reference](../reference/api-reference)
- [Facade Usage](../guides/facade-usage)
