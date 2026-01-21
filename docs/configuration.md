---
title: "Configuration"
description: "Configure database, payload storage, retention policies, and API settings"
weight: 3
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
