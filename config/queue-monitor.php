<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateJobStatisticsAction;
use PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateQueueHealthAction;
use PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateServerStatisticsAction;
use PHPeek\LaravelQueueMonitor\Actions\Batch\BatchDeleteAction;
use PHPeek\LaravelQueueMonitor\Actions\Batch\BatchReplayAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\CancelJobAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use PHPeek\LaravelQueueMonitor\Actions\Core\UpdateJobMetricsAction;
use PHPeek\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use PHPeek\LaravelQueueMonitor\Repositories\Eloquent\EloquentJobMonitorRepository;
use PHPeek\LaravelQueueMonitor\Repositories\Eloquent\EloquentStatisticsRepository;
use PHPeek\LaravelQueueMonitor\Repositories\Eloquent\EloquentTagRepository;

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Monitor Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the queue monitor package is active.
    | Set to false to completely disable job monitoring without uninstalling.
    |
    */
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the database connection and table prefix for queue monitoring.
    | Leave connection null to use the default application connection.
    |
    */
    'database' => [
        'connection' => env('QUEUE_MONITOR_DB_CONNECTION'),
        'table_prefix' => 'queue_monitor_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Storage
    |--------------------------------------------------------------------------
    |
    | Configure how job payloads are stored for replay functionality.
    |
    */
    'storage' => [
        // Store complete job payload for replay capability
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', true),

        // Maximum payload size in bytes (default: 64KB)
        'payload_max_size' => 65535,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of old job records.
    |
    */
    'retention' => [
        // Number of days to retain job records
        'days' => 30,

        // Which statuses to prune (empty array = prune all statuses)
        'prune_statuses' => ['completed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Detection
    |--------------------------------------------------------------------------
    |
    | Configure how workers and servers are detected and identified.
    |
    */
    'worker_detection' => [
        // Custom callable for determining server name
        // If null, uses gethostname()
        'server_name_callable' => null,

        // Enable Horizon detection
        'horizon_detection' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for statistics queries to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('QUEUE_MONITOR_CACHE_ENABLED', true),
        'store' => env('QUEUE_MONITOR_CACHE_STORE'),
        'ttl' => env('QUEUE_MONITOR_CACHE_TTL', 60), // seconds
        'prefix' => 'queue_monitor_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batch Operations Configuration
    |--------------------------------------------------------------------------
    |
    | Configure batch operation behavior for bulk actions.
    |
    */
    'batch' => [
        'chunk_size' => env('QUEUE_MONITOR_BATCH_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the REST API for external integrations and dashboards.
    |
    */
    'api' => [
        'enabled' => env('QUEUE_MONITOR_API_ENABLED', true),
        'prefix' => 'api/queue-monitor',
        'middleware' => ['api'],
        'rate_limit' => '60,1', // 60 requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository Bindings
    |--------------------------------------------------------------------------
    |
    | Map repository contracts to their concrete implementations.
    | Override these to provide custom repository implementations.
    |
    */
    'repositories' => [
        JobMonitorRepositoryContract::class => EloquentJobMonitorRepository::class,
        TagRepositoryContract::class => EloquentTagRepository::class,
        StatisticsRepositoryContract::class => EloquentStatisticsRepository::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Bindings
    |--------------------------------------------------------------------------
    |
    | Map action classes for dependency injection and extensibility.
    | Override these to provide custom action implementations.
    |
    */
    'actions' => [
        // Core Actions
        'record_job_queued' => RecordJobQueuedAction::class,
        'record_job_started' => RecordJobStartedAction::class,
        'record_job_completed' => RecordJobCompletedAction::class,
        'record_job_failed' => RecordJobFailedAction::class,
        'record_job_timeout' => RecordJobTimeoutAction::class,
        'update_job_metrics' => UpdateJobMetricsAction::class,
        'cancel_job' => CancelJobAction::class,
        'prune_jobs' => PruneJobsAction::class,

        // Replay Actions
        'replay_job' => ReplayJobAction::class,

        // Batch Actions
        'batch_replay' => BatchReplayAction::class,
        'batch_delete' => BatchDeleteAction::class,

        // Analytics Actions
        'calculate_job_statistics' => CalculateJobStatisticsAction::class,
        'calculate_server_statistics' => CalculateServerStatisticsAction::class,
        'calculate_queue_health' => CalculateQueueHealthAction::class,
    ],
];
