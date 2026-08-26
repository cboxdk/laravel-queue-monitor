<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\DataTransferObjects\HealthCheckResult;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ReadinessResult;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\Contracts\HealthCheckServiceContract;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class HealthCheckService implements HealthCheckServiceContract
{
    /**
     * Perform comprehensive health check
     */
    public function check(): HealthCheckResult
    {
        /** @var string $cachePrefix */
        $cachePrefix = config('queue-monitor.cache.prefix', 'queue_monitor_');

        if (config('queue-monitor.cache.enabled', true)) {
            /** @var HealthCheckResult|null $cached */
            $cached = Cache::get($cachePrefix.'health_check');
            if ($cached !== null) {
                return $cached;
            }
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'recent_activity' => $this->checkRecentActivity(),
            'stuck_jobs' => $this->checkStuckJobs(),
            'error_rate' => $this->checkErrorRate(),
            'queue_backlog' => $this->checkQueueBacklog(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['healthy']);

        $result = new HealthCheckResult(
            status: $healthy ? 'healthy' : 'degraded',
            checks: $checks,
            timestamp: now()->toIso8601String(),
        );

        if (config('queue-monitor.cache.enabled', true)) {
            Cache::put($cachePrefix.'health_check', $result, 15);
        }

        return $result;
    }

    /**
     * Check production readiness configuration.
     */
    public function readiness(): ReadinessResult
    {
        $checks = [
            'access_control' => $this->checkAccessControlReadiness(),
            'api_middleware' => $this->checkApiMiddlewareReadiness(),
            'payload_storage' => $this->checkPayloadStorageReadiness(),
            'retention' => $this->checkRetentionReadiness(),
            'horizon_timeouts' => $this->checkHorizonTimeoutReadiness(),
        ];

        $ready = collect($checks)->every(fn (array $check): bool => (bool) $check['healthy']);

        return new ReadinessResult(
            status: $ready ? 'ready' : 'attention',
            checks: $checks,
            timestamp: now()->toIso8601String(),
        );
    }

    /**
     * Check database connectivity
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkDatabase(): array
    {
        try {
            /** @var string|null $connection */
            $connection = config('queue-monitor.database.connection');
            DB::connection($connection)->getPdo();

            $count = JobMonitor::count();

            return [
                'healthy' => true,
                'message' => 'Database connection successful',
                'details' => [
                    'total_jobs' => $count,
                    'connection' => $connection ?? 'default',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check for recent job activity
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkRecentActivity(): array
    {
        $recentJobs = QueryBuilderHelper::lastHours(1)->count();

        return [
            'healthy' => true, // No activity is not unhealthy — idle queues are fine
            'message' => $recentJobs > 0
                ? "Jobs processed in last hour: {$recentJobs}"
                : 'No jobs processed in last hour (idle)',
            'details' => [
                'jobs_last_hour' => $recentJobs,
            ],
        ];
    }

    /**
     * Check for stuck jobs
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkStuckJobs(): array
    {
        $thresholdMinutes = $this->intConfig('queue-monitor.health.stuck_job_minutes', 30);

        $stuckJobs = QueryBuilderHelper::stuck($thresholdMinutes)
            ->select(['uuid', 'job_class', 'queue', 'server_name', 'started_at', 'attempt'])
            ->limit(10)
            ->get();

        $stuck = $stuckJobs->count();
        $healthy = $stuck === 0;

        return [
            'healthy' => $healthy,
            'message' => $stuck > 0
                ? "{$stuck} ".($stuck === 1 ? 'job' : 'jobs').' stuck in processing'
                : 'No stuck jobs detected',
            'details' => [
                'stuck_count' => $stuck,
                'threshold_minutes' => $thresholdMinutes,
                'stuck_jobs' => $stuckJobs->map(fn ($job) => [
                    'uuid' => $job->uuid,
                    'job_class' => $job->job_class,
                    'queue' => $job->queue,
                    'server' => $job->server_name,
                    'stuck_since' => $job->started_at,
                    'attempt' => $job->attempt,
                ])->all(),
            ],
        ];
    }

    /**
     * Check error rate
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkErrorRate(): array
    {
        $threshold = $this->floatConfig('queue-monitor.health.error_rate_threshold', 10.0);
        $recentTotal = QueryBuilderHelper::lastHours(1)->count();
        $recentFailed = QueryBuilderHelper::lastHours(1)
            ->whereIn('status', [JobStatus::FAILED->value, JobStatus::TIMEOUT->value])
            ->count();

        $errorRate = $recentTotal > 0 ? ($recentFailed / $recentTotal) * 100 : 0;
        $healthy = $errorRate < $threshold;

        return [
            'healthy' => $healthy,
            'message' => sprintf('Error rate: %.2f%%', $errorRate),
            'details' => [
                'error_rate' => round($errorRate, 2),
                'total_jobs' => $recentTotal,
                'failed_jobs' => $recentFailed,
                'threshold' => $threshold,
            ],
        ];
    }

    /**
     * Check queue backlog
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkQueueBacklog(): array
    {
        $queuedThreshold = $this->intConfig('queue-monitor.health.queued_jobs_threshold', 1000);
        $processingThreshold = $this->intConfig('queue-monitor.health.processing_jobs_threshold', 100);
        $processing = JobMonitor::where('status', JobStatus::PROCESSING)->count();
        $queued = JobMonitor::where('status', JobStatus::QUEUED)->count();

        $healthy = $queued < $queuedThreshold && $processing < $processingThreshold;

        return [
            'healthy' => $healthy,
            'message' => "Queued: {$queued}, Processing: {$processing}",
            'details' => [
                'queued' => $queued,
                'processing' => $processing,
                'total_pending' => $queued + $processing,
                'queued_threshold' => $queuedThreshold,
                'processing_threshold' => $processingThreshold,
            ],
        ];
    }

    /**
     * Check storage status
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkStorage(): array
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');
        $maxMb = $this->floatConfig('queue-monitor.health.storage_max_mb', 1000.0);

        try {
            $database = DB::connection($connection);

            if (! in_array($database->getDriverName(), ['mysql', 'mariadb'], true)) {
                return [
                    'healthy' => true,
                    'message' => 'Storage check not available for this database driver',
                    'details' => [
                        'connection' => $connection ?? 'default',
                        'driver' => $database->getDriverName(),
                    ],
                ];
            }

            $tableSize = $database->select(
                'SELECT
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                    TABLE_ROWS as row_count
                FROM information_schema.TABLES
                WHERE TABLE_NAME = ?
                AND TABLE_SCHEMA = DATABASE()',
                [$this->monitorJobsTable($database)]
            );

            if (empty($tableSize)) {
                return [
                    'healthy' => true,
                    'message' => 'Storage check not available (non-MySQL database)',
                ];
            }

            $sizeMb = $tableSize[0]->size_mb ?? 0;
            $rows = $tableSize[0]->row_count ?? 0;

            $healthy = $sizeMb < $maxMb;

            return [
                'healthy' => $healthy,
                'message' => "{$sizeMb}MB, {$rows} rows",
                'details' => [
                    'size_mb' => $sizeMb,
                    'row_count' => $rows,
                    'threshold_mb' => $maxMb,
                    'connection' => $connection ?? 'default',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => true,
                'message' => 'Storage check not available',
            ];
        }
    }

    /**
     * Resolve the physical name of the monitor jobs table on a connection.
     *
     * Schema and query builders apply the connection's own table prefix
     * (database.connections.*.prefix) automatically, but raw lookups against
     * information_schema do not — so it must be prepended to the package's
     * configured table prefix here.
     */
    public function monitorJobsTable(Connection $database): string
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $database->getTablePrefix().$prefix.'jobs';
    }

    /**
     * Get overall health score (0-100)
     */
    public function getHealthScore(): int
    {
        return $this->scoreFromChecks($this->check()->checks);
    }

    /**
     * Compute the health score (0-100) from an already-run set of checks, so a
     * caller that already holds the checks (the dashboard) does not run them
     * again to score them.
     *
     * @param  array<string, array<string, mixed>>  $checks
     */
    public function scoreFromChecks(array $checks): int
    {
        $healthyCount = collect($checks)->filter(fn (array $c): bool => (bool) ($c['healthy'] ?? false))->count();

        return (int) round(($healthyCount / max(count($checks), 1)) * 100);
    }

    /**
     * Check if system is healthy
     */
    public function isHealthy(): bool
    {
        return $this->check()->status === 'healthy';
    }

    /**
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkAccessControlReadiness(): array
    {
        if (app()->environment('local')) {
            return [
                'healthy' => true,
                'message' => 'Local environment uses the built-in local-only access fallback',
            ];
        }

        $surfaceEnabled = (bool) config('queue-monitor.ui.enabled', true)
            || (bool) config('queue-monitor.api.enabled', app()->environment('local'));

        if (! $surfaceEnabled) {
            return [
                'healthy' => true,
                'message' => 'Dashboard and API surfaces are disabled',
            ];
        }

        $hasCallback = LaravelQueueMonitor::hasAuthCallback();

        return [
            'healthy' => $hasCallback,
            'message' => $hasCallback
                ? 'Explicit authorization callback registered'
                : 'Register LaravelQueueMonitor::auth(...) before exposing Queue Monitor outside local',
        ];
    }

    /**
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkApiMiddlewareReadiness(): array
    {
        $apiEnabled = (bool) config('queue-monitor.api.enabled', app()->environment('local'));

        if (! $apiEnabled) {
            return [
                'healthy' => true,
                'message' => 'API routes are disabled',
            ];
        }

        if (app()->environment('local')) {
            return [
                'healthy' => true,
                'message' => 'API is enabled for local development',
            ];
        }

        $middleware = $this->middlewareList(config('queue-monitor.api.middleware', []));
        $hasProtectiveMiddleware = collect($middleware)
            ->contains(fn (string $entry): bool => str_starts_with($entry, 'auth')
                || str_starts_with($entry, 'can:')
                || str_contains($entry, 'Authenticate'));

        return [
            'healthy' => $hasProtectiveMiddleware,
            'message' => $hasProtectiveMiddleware
                ? 'API middleware includes an authentication or authorization layer'
                : 'Add auth middleware before enabling the API outside local',
            'details' => [
                'middleware' => $middleware,
            ],
        ];
    }

    /**
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkPayloadStorageReadiness(): array
    {
        $storePayload = (bool) config('queue-monitor.storage.store_payload', app()->environment('local'));

        if (app()->environment('local') || ! $storePayload) {
            return [
                'healthy' => true,
                'message' => $storePayload
                    ? 'Payload storage is enabled for local development'
                    : 'Payload storage is disabled outside local',
            ];
        }

        return [
            'healthy' => false,
            'message' => 'Payload storage is enabled outside local; confirm this is required for replay and acceptable for stored data',
        ];
    }

    /**
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkRetentionReadiness(): array
    {
        $days = config('queue-monitor.retention.days');
        $maxRows = config('queue-monitor.retention.max_rows');

        $hasLimit = (is_numeric($days) && (int) $days > 0) || (is_numeric($maxRows) && (int) $maxRows > 0);

        return [
            'healthy' => $hasLimit,
            'message' => $hasLimit
                ? 'Retention limits are configured; schedule queue-monitor:prune in production'
                : 'Configure a retention day or row limit before production use',
            'details' => [
                'days' => $days,
                'max_rows' => $maxRows,
            ],
        ];
    }

    /**
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkHorizonTimeoutReadiness(): array
    {
        $timeouts = $this->horizonTimeouts();
        $retryAfterValues = $this->queueRetryAfterValues();

        if ($timeouts === [] || $retryAfterValues === []) {
            return [
                'healthy' => true,
                'message' => 'No Horizon timeout and queue retry_after pair found to validate',
                'details' => [
                    'horizon_timeouts' => $timeouts,
                    'queue_retry_after' => $retryAfterValues,
                ],
            ];
        }

        $maxTimeout = max($timeouts);
        $minRetryAfter = min($retryAfterValues);
        $healthy = $maxTimeout < $minRetryAfter;

        return [
            'healthy' => $healthy,
            'message' => $healthy
                ? 'Horizon timeout values are lower than queue retry_after values'
                : 'Horizon timeout must be lower than queue retry_after to avoid duplicate processing',
            'details' => [
                'max_horizon_timeout' => $maxTimeout,
                'min_queue_retry_after' => $minRetryAfter,
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function horizonTimeouts(): array
    {
        $timeouts = [];
        $this->collectTimeouts(config('horizon.defaults', []), $timeouts);
        $this->collectTimeouts(config('horizon.environments', []), $timeouts);

        return array_values(array_unique($timeouts));
    }

    /**
     * @param  array<int, int>  $timeouts
     */
    private function collectTimeouts(mixed $value, array &$timeouts): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if ($key === 'timeout' && is_numeric($item)) {
                $timeouts[] = (int) $item;

                continue;
            }

            $this->collectTimeouts($item, $timeouts);
        }
    }

    /**
     * @return array<int, int>
     */
    private function queueRetryAfterValues(): array
    {
        $connections = config('queue.connections', []);

        if (! is_array($connections)) {
            return [];
        }

        $values = [];

        foreach ($connections as $connection) {
            if (is_array($connection) && isset($connection['retry_after']) && is_numeric($connection['retry_after'])) {
                $values[] = (int) $connection['retry_after'];
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, string>
     */
    private function middlewareList(mixed $middleware): array
    {
        if (is_string($middleware)) {
            return [$middleware];
        }

        if (! is_array($middleware)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $entry): string => is_string($entry) ? $entry : '', $middleware),
            fn (string $entry): bool => $entry !== ''
        ));
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function floatConfig(string $key, float $default): float
    {
        $value = config($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}
