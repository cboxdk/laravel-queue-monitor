---
title: "Installation"
description: "Install and configure Queue Monitor for Laravel in your application"
weight: 2
---

# Installation

## Requirements

- PHP 8.3+
- Laravel 11+
- [cboxdk/laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) ^2.3 (installed automatically)

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

The package will create three tables:
- `queue_monitor_jobs` — Stores individual job records
- `queue_monitor_tags` — Normalized tag storage for analytics
- `queue_monitor_scaling_events` — Autoscale integration (if used)

### 4. Configure Metrics Storage

Queue Monitor depends on [laravel-queue-metrics](https://github.com/cboxdk/laravel-queue-metrics) for per-job CPU and memory instrumentation. Metrics data is stored separately from job records and needs a fast storage backend.

#### Redis (default)

If you already have a Redis connection, no configuration needed — this is the default.

```env
QUEUE_METRICS_STORAGE=redis
QUEUE_METRICS_CONNECTION=default
```

Redis is the recommended driver for all production workloads.

#### Database

If you don't run Redis, metrics can be stored in your application database:

```env
QUEUE_METRICS_STORAGE=database
```

Publish and run the metrics storage migration:

```bash
php artisan vendor:publish --tag="queue-metrics-migrations"
php artisan migrate
```

This creates 4 additional tables (`queue_metrics_keys`, `queue_metrics_hashes`, `queue_metrics_sets`, `queue_metrics_sorted_sets`) used for metrics storage.

> **Performance note:** The database driver is designed for low-scale workloads (< 10 workers). At higher scale, metrics writes compete with your queue jobs for database connections. We recommend `QUEUE_METRICS_MAX_SAMPLES=500` to keep table sizes manageable.

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

## Configuration

The configuration file is published to `config/queue-monitor.php`. Key settings include:

```php
return [
    // Enable/disable monitoring
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    // Store job payloads for replay
    'storage' => [
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', true),
    ],

    // Data retention settings
    'retention' => [
        'days' => 30,
        'prune_statuses' => ['completed'],
    ],

    // REST API settings
    'api' => [
        'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
        'prefix' => 'api/queue-monitor',
        'middleware' => ['api'],
    ],
];
```

## Environment Variables

```env
# Queue Monitor
QUEUE_MONITOR_ENABLED=true
QUEUE_MONITOR_STORE_PAYLOAD=true
QUEUE_MONITOR_API_ENABLED=true

# Metrics Storage (from laravel-queue-metrics)
QUEUE_METRICS_STORAGE=redis          # redis (default) or database
QUEUE_METRICS_CONNECTION=default     # Redis or database connection name
QUEUE_METRICS_MAX_SAMPLES=1000       # Recommended: 500 for database driver
```

## Next Steps

- [Configuration Guide](../guides/configuration)
- [API Reference](../reference/api-reference)
- [Facade Usage](../guides/facade-usage)