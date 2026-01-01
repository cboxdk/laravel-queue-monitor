# Laravel Queue Monitor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gophpeek/laravel-queue-monitor.svg?style=flat-square)](https://packagist.org/packages/gophpeek/laravel-queue-monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/gophpeek/laravel-queue-monitor.svg?style=flat-square)](https://packagist.org/packages/gophpeek/laravel-queue-monitor)

**Production-ready Laravel queue monitoring with individual job tracking, payload storage, and comprehensive analytics.**

Track every queue job with detailed metrics, replay failed jobs from stored payloads, and gain deep insights into your queue performance. Built on top of [laravel-queue-metrics](https://github.com/gophpeek/laravel-queue-metrics) for enhanced resource tracking.

## Features

- ✅ **Individual Job Tracking** - Monitor every job from queue to completion
- ✅ **Full Payload Storage** - Store complete job payloads for replay capability
- ✅ **Worker & Server Identification** - Track which server/worker processed each job
- ✅ **Horizon Support** - Automatic detection of Horizon vs queue:work
- ✅ **Job Replay** - Re-dispatch failed jobs from stored payloads
- ✅ **Resource Metrics** - CPU, memory, file descriptor tracking via queue-metrics integration
- ✅ **Retry Chain Tracking** - Complete visibility into job retry attempts
- ✅ **Comprehensive Analytics** - Per-queue, per-server, per-job-class statistics
- ✅ **REST API** - Full-featured API for dashboard integration
- ✅ **Tag Analytics** - Track and analyze jobs by custom tags
- ✅ **Artisan Commands** - CLI tools for monitoring and maintenance
- ✅ **PHPStan Level 9** - Maximum static analysis compliance
- ✅ **Pest 4 Ready** - Modern testing framework support

## Requirements

- PHP 8.3+
- Laravel 10+
- **gophpeek/laravel-queue-metrics** ^1.0 (hard dependency)

## Installation

Install via Composer:

```bash
composer require gophpeek/laravel-queue-monitor
```

Publish configuration and run migrations:

```bash
php artisan vendor:publish --tag="queue-monitor-config"
php artisan migrate
```

That's it! The package automatically starts monitoring all queue jobs.

## Quick Start

### Facade Usage

```php
use PHPeek\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

// Get job details
$job = QueueMonitor::getJob($uuid);

// Replay a failed job
$replayData = QueueMonitor::replay($uuid);

// Get statistics
$stats = QueueMonitor::statistics();
echo "Success Rate: {$stats['success_rate']}%";

// Query jobs with filters
$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queuedAfter: Carbon::now()->subHours(24)
);
$failedJobs = QueueMonitor::getJobs($filters);
```

### Artisan Commands

```bash
# Show statistics
php artisan queue-monitor:stats

# Replay a job
php artisan queue-monitor:replay {uuid}

# Prune old jobs
php artisan queue-monitor:prune --days=30 --statuses=completed
```

### REST API

```bash
# List jobs
GET /api/queue-monitor/jobs?statuses[]=failed&limit=50

# Get job details
GET /api/queue-monitor/jobs/{uuid}

# Replay job
POST /api/queue-monitor/jobs/{uuid}/replay

# Get statistics
GET /api/queue-monitor/statistics

# Queue health
GET /api/queue-monitor/statistics/queue-health
```

## Architecture

### Action Pattern
All business logic is encapsulated in single-responsibility Action classes:

```php
RecordJobQueuedAction
RecordJobStartedAction
RecordJobCompletedAction
RecordJobFailedAction
ReplayJobAction
CalculateJobStatisticsAction
```

### DTO Pattern
All data transfer uses strictly-typed DTOs:

```php
JobMonitorData
WorkerContextData
ExceptionData
JobFilterData
JobReplayData
```

### Repository Pattern
Data access through contracts with Eloquent implementations:

```php
JobMonitorRepositoryContract
TagRepositoryContract
StatisticsRepositoryContract
```

### Event-Driven
Integrates seamlessly with Laravel Queue events and queue-metrics events.

## Configuration

Key configuration options in `config/queue-monitor.php`:

```php
return [
    // Enable/disable monitoring
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    // Payload storage for replay
    'storage' => [
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', true),
        'payload_max_size' => 65535,
    ],

    // Data retention
    'retention' => [
        'days' => 30,
        'prune_statuses' => ['completed'],
    ],

    // REST API (see Security section for authentication)
    'api' => [
        'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
        'prefix' => 'api/queue-monitor',
        'middleware' => ['api', 'auth:sanctum'], // Add authentication!
    ],

    // Worker detection
    'worker_detection' => [
        'server_name_callable' => null,
        'horizon_detection' => true,
    ],
];
```

## Security

### API Authentication

**The REST API exposes sensitive queue data including job payloads and exception traces.** You should always add authentication middleware in production.

#### Recommended Setup (Laravel Sanctum)

```php
// config/queue-monitor.php
'api' => [
    'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
    'prefix' => 'api/queue-monitor',
    'middleware' => ['api', 'auth:sanctum'],
],
```

#### Alternative Authentication Methods

```php
// Using Laravel's built-in auth
'middleware' => ['api', 'auth'],

// Using custom middleware
'middleware' => ['api', 'auth.admin'],

// Using abilities/permissions
'middleware' => ['api', 'auth:sanctum', 'ability:queue-monitor'],
```

#### IP Whitelisting (Additional Layer)

For maximum security, combine authentication with IP restrictions:

```php
// app/Http/Middleware/QueueMonitorAccess.php
class QueueMonitorAccess
{
    public function handle($request, Closure $next)
    {
        $allowedIps = ['10.0.0.0/8', '192.168.0.0/16'];

        if (!$this->ipIsAllowed($request->ip(), $allowedIps)) {
            abort(403);
        }

        return $next($request);
    }
}
```

### Payload Storage

Job payloads may contain sensitive data. Consider:

1. **Disable payload storage** if replay isn't needed:
   ```php
   'storage' => ['store_payload' => false],
   ```

2. **Use a separate database** for monitoring data:
   ```php
   'database' => ['connection' => 'queue_monitor'],
   ```

3. **Implement data retention policies** to limit exposure:
   ```php
   'retention' => ['days' => 7],
   ```

### Environment-Based Configuration

Disable the API in production if only using the facade/commands:

```env
QUEUE_MONITOR_API_ENABLED=false
```

## Documentation

- [Installation Guide](docs/installation.md)
- [Configuration](docs/configuration.md)
- [API Reference](docs/api-reference.md)
- [Facade Usage](docs/facade-usage.md)
- [Job Replay](docs/job-replay.md)

## Integration with Queue-Metrics

This package is built on top of [laravel-queue-metrics](https://github.com/gophpeek/laravel-queue-metrics) and automatically:

- Captures CPU time, memory usage, and file descriptors
- Subscribes to `MetricsRecorded` events
- Enriches job records with performance metrics
- Leverages Horizon detection utilities

## Testing

```bash
composer test
composer analyse  # PHPStan Level 9
composer format   # Laravel Pint
```

## Credits

- [Sylvester Damgaard](https://github.com/PHPeek)
- Built with [laravel-package-tools](https://github.com/spatie/laravel-package-tools)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
