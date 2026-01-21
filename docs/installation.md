---
title: "Installation"
description: "Install and configure Laravel Queue Monitor in your Laravel application"
weight: 2
---

# Installation

## Requirements

- PHP 8.3+
- Laravel 10.0+
- **cboxdk/laravel-queue-metrics** (hard dependency)

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

The package will create two tables:
- `queue_monitor_jobs` - Stores individual job records
- `queue_monitor_tags` - Normalized tag storage for analytics

### 4. Advanced Installation (Optional)

If you need to customize the migrations, you can publish them to your application:

```bash
php artisan vendor:publish --tag="queue-monitor-migrations"
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
QUEUE_MONITOR_ENABLED=true
QUEUE_MONITOR_STORE_PAYLOAD=true
QUEUE_MONITOR_API_ENABLED=true
QUEUE_MONITOR_DB_CONNECTION=mysql
```

## Next Steps

- [Configuration Guide](configuration)
- [API Reference](api-reference)
- [Facade Usage](facade-usage)