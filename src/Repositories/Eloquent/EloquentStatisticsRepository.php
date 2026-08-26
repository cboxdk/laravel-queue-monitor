<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Eloquent;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Cbox\LaravelQueueMonitor\DataTransferObjects\FailurePatterns;
use Cbox\LaravelQueueMonitor\DataTransferObjects\GlobalStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobClassStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueHealthEntry;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueTimeline;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ServerStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ThroughputBucket;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\DashboardCacheServiceContract;
use Cbox\LaravelQueueMonitor\Utilities\DatabaseExpressionHelper;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class EloquentStatisticsRepository implements StatisticsRepositoryContract
{
    public function __construct(
        private DashboardCacheServiceContract $dashboardCache,
    ) {}

    public function getGlobalStatistics(): GlobalStatistics
    {
        return $this->remember('global_statistics', fn () => $this->computeGlobalStatistics());
    }

    private function computeGlobalStatistics(): GlobalStatistics
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = $this->table($prefix.'jobs')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as timeout'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as queued'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
                DB::raw('MAX(duration_ms) as max_duration_ms'),
                DB::raw('AVG(CASE WHEN memory_peak_mb IS NOT NULL THEN memory_peak_mb END) as avg_memory_mb'),
                DB::raw('MAX(memory_peak_mb) as max_memory_mb'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
                JobStatus::PROCESSING->value,
                JobStatus::QUEUED->value,
            ]);

        $this->applyMetricsWindow($query);

        $stats = $query->first();

        /** @var object{total: int, completed: int, failed: int, timeout: int, processing: int, queued: int, avg_duration_ms: float|null, max_duration_ms: int|null, avg_memory_mb: float|null, max_memory_mb: float|null}|null $stats */
        if ($stats === null) {
            return new GlobalStatistics;
        }

        $total = (int) $stats->total;
        $completed = (int) $stats->completed;
        $failed = (int) $stats->failed + (int) $stats->timeout;
        $finished = $completed + $failed;

        return GlobalStatistics::fromArray([
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'timeout' => (int) $stats->timeout,
            'processing' => (int) $stats->processing,
            'queue_backlog' => (int) $stats->queued,
            'success_rate' => $finished > 0 ? round(($completed / $finished) * 100, 2) : 0,
            'failure_rate' => $finished > 0 ? round(($failed / $finished) * 100, 2) : 0,
            'avg_duration_ms' => $stats->avg_duration_ms !== null ? round((float) $stats->avg_duration_ms, 2) : null,
            'max_duration_ms' => $stats->max_duration_ms !== null ? (int) $stats->max_duration_ms : null,
            'avg_memory_mb' => $stats->avg_memory_mb !== null ? round((float) $stats->avg_memory_mb, 2) : null,
            'max_memory_mb' => $stats->max_memory_mb !== null ? round((float) $stats->max_memory_mb, 2) : null,
        ]);
    }

    /**
     * @return array<int, ServerStatistics>
     */
    public function getServerStatistics(?string $serverName = null): array
    {
        $cacheKey = 'server_statistics_'.($serverName ?? 'all');

        return $this->remember($cacheKey, fn () => $this->computeServerStatistics($serverName));
    }

    /**
     * @return array<int, ServerStatistics>
     */
    private function computeServerStatistics(?string $serverName = null): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = $this->table($prefix.'jobs')
            ->select([
                'server_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->groupBy('server_name');

        $this->applyMetricsWindow($query);

        if ($serverName !== null) {
            $query->where('server_name', $serverName);
        }

        /** @var array<int, ServerStatistics> $result */
        $result = $query->get()
            ->map(function ($row) {
                $completed = (int) $row->completed;
                $failed = (int) $row->failed;
                $finished = $completed + $failed;

                return ServerStatistics::fromArray([
                    'server_name' => $row->server_name,
                    'total_jobs' => (int) $row->total,
                    'completed' => $completed,
                    'failed' => $failed,
                    'success_rate' => $finished > 0
                        ? round(($completed / $finished) * 100, 2)
                        : 0,
                    'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
                ]);
            })
            ->values()
            ->all();

        return $result;
    }

    /**
     * @return array<int, QueueStatistics>
     */
    public function getQueueStatistics(?string $queue = null): array
    {
        $cacheKey = 'queue_statistics_'.($queue ?? 'all');

        return $this->remember($cacheKey, fn () => $this->computeQueueStatistics($queue));
    }

    /**
     * @return array<int, QueueStatistics>
     */
    private function computeQueueStatistics(?string $queue = null): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = $this->table($prefix.'jobs')
            ->select([
                'queue',
                'connection',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
                JobStatus::PROCESSING->value,
            ])
            ->groupBy('queue', 'connection');

        $this->applyMetricsWindow($query);

        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        /** @var array<int, QueueStatistics> $result */
        $result = $query->get()
            ->map(function ($row) {
                $completed = (int) $row->completed;
                $failed = (int) $row->failed;
                $finished = $completed + $failed;

                return QueueStatistics::fromArray([
                    'queue' => $row->queue,
                    'connection' => $row->connection,
                    'total_jobs' => (int) $row->total,
                    'completed' => $completed,
                    'failed' => $failed,
                    'processing' => (int) $row->processing,
                    'success_rate' => $finished > 0
                        ? round(($completed / $finished) * 100, 2)
                        : 0,
                    'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
                ]);
            })
            ->values()
            ->all();

        return $result;
    }

    /**
     * @return array<int, JobClassStatistics>
     */
    public function getJobClassStatistics(?string $jobClass = null): array
    {
        $cacheKey = 'job_class_statistics_'.($jobClass !== null ? md5($jobClass) : 'all');

        return $this->remember($cacheKey, fn () => $this->computeJobClassStatistics($jobClass));
    }

    /**
     * @return array<int, JobClassStatistics>
     */
    private function computeJobClassStatistics(?string $jobClass = null): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = $this->table($prefix.'jobs')
            ->select([
                'job_class',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
                DB::raw('MAX(duration_ms) as max_duration_ms'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->groupBy('job_class')
            ->orderByDesc(DB::raw('COUNT(*)'));

        $this->applyMetricsWindow($query);

        if ($jobClass !== null) {
            $query->where('job_class', $jobClass);
        }

        /** @var array<int, JobClassStatistics> $result */
        $result = $query->get()
            ->map(function ($row) {
                $completed = (int) $row->completed;
                $failed = (int) $row->failed;
                $finished = $completed + $failed;

                return JobClassStatistics::fromArray([
                    'job_class' => $row->job_class,
                    'total_jobs' => (int) $row->total,
                    'completed' => $completed,
                    'failed' => $failed,
                    'success_rate' => $finished > 0
                        ? round(($completed / $finished) * 100, 2)
                        : 0,
                    'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
                    'max_duration_ms' => $row->max_duration_ms !== null ? (int) $row->max_duration_ms : null,
                ]);
            })
            ->values()
            ->all();

        return $result;
    }

    public function getFailurePatterns(): FailurePatterns
    {
        return $this->remember('failure_patterns', fn () => $this->computeFailurePatterns());
    }

    private function computeFailurePatterns(): FailurePatterns
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = $this->table($prefix.'jobs')
            ->select([
                'exception_class',
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT job_class) as affected_job_classes'),
            ])
            ->whereNotNull('exception_class')
            ->groupBy('exception_class');

        $this->applyMetricsWindow($query);

        /** @var array<int, array<string, mixed>> $exceptionStats */
        $exceptionStats = $query
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'exception_class' => $row->exception_class,
                'count' => (int) $row->count,
                'affected_job_classes' => (int) $row->affected_job_classes,
            ])
            ->values()
            ->all();

        return new FailurePatterns($exceptionStats);
    }

    /**
     * @return array<int, QueueHealthEntry>
     */
    public function getQueueHealth(): array
    {
        return $this->remember('queue_health', fn () => $this->computeQueueHealth(), 30);
    }

    /**
     * @return array<int, QueueHealthEntry>
     */
    private function computeQueueHealth(): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        /** @var array<int, QueueHealthEntry> $queueHealth */
        $queueHealth = $this->table($prefix.'jobs')
            ->select([
                'queue',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::PROCESSING->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('queue')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total;
                $completed = (int) $row->completed;
                $processing = (int) $row->processing;
                $failed = (int) $row->failed;
                $finished = $completed + $failed;
                $avgDuration = $row->avg_duration_ms !== null ? (float) $row->avg_duration_ms : null;

                // Health score based on finished jobs only (excludes queued/processing)
                $failureRate = $finished > 0 ? ($failed / $finished) : 0;
                $health = 100 - ($failureRate * 100);

                return QueueHealthEntry::fromArray([
                    'queue' => $row->queue,
                    'total_last_hour' => $total,
                    'processing' => $processing,
                    'failed' => $failed,
                    'avg_duration_ms' => $avgDuration !== null ? round($avgDuration, 2) : null,
                    'health_score' => round($health, 2),
                    'status' => $health >= 95 ? 'healthy' : ($health >= 75 ? 'degraded' : 'unhealthy'),
                ]);
            })
            ->values()
            ->all();

        return $queueHealth;
    }

    /**
     * @return array<int, ThroughputBucket>
     */
    public function getThroughputByMinute(int $minutes = 60): array
    {
        return $this->remember("throughput_{$minutes}", fn () => $this->computeThroughputByMinute($minutes), 30);
    }

    public function getQueueTimeline(string $queue, int $minutes = 60): QueueTimeline
    {
        return $this->remember(
            'queue_timeline_'.md5($queue).'_'.$minutes,
            fn (): QueueTimeline => $this->computeQueueTimeline($queue, $minutes),
            15
        );
    }

    public function computeQueueTimeline(string $queue, int $minutes = 60): QueueTimeline
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $dateFormat = DatabaseExpressionHelper::minuteBucket('queued_at', $this->databaseDriver());

        $rows = $this->table($prefix.'jobs')
            ->select([
                DB::raw("{$dateFormat} as minute"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(duration_ms) as avg_duration_ms'),
                DB::raw('MAX(duration_ms) as max_duration_ms'),
                DB::raw('AVG(memory_peak_mb) as avg_memory_mb'),
                DB::raw('MAX(memory_peak_mb) as max_memory_mb'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->where('queue', $queue)
            ->where('queued_at', '>=', now()->subMinutes($minutes))
            ->groupBy(DB::raw($dateFormat))
            ->orderBy(DB::raw($dateFormat))
            ->get()
            ->keyBy('minute');

        $buckets = [];
        $period = CarbonPeriod::create(
            now()->subMinutes($minutes)->startOfMinute(),
            '1 minute',
            now()->startOfMinute()
        );

        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->format('Y-m-d H:i');
            $row = $rows->get($key);
            $buckets[] = [
                'minute' => $key,
                'ts' => $date->toIso8601String(),
                'total' => $row ? (int) $row->total : 0,
                'completed' => $row ? (int) $row->completed : 0,
                'failed' => $row ? (int) $row->failed : 0,
                'avg_duration_ms' => $row && $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 1) : null,
                'max_duration_ms' => $row && $row->max_duration_ms !== null ? (int) $row->max_duration_ms : null,
                'avg_memory_mb' => $row && $row->avg_memory_mb !== null ? round((float) $row->avg_memory_mb, 1) : null,
                'max_memory_mb' => $row && $row->max_memory_mb !== null ? round((float) $row->max_memory_mb, 1) : null,
            ];
        }

        $now = now();

        $live = [
            'processing' => (int) $this->table($prefix.'jobs')
                ->where('queue', $queue)
                ->where('status', JobStatus::PROCESSING->value)
                ->count(),
            'waiting' => (int) $this->table($prefix.'jobs')
                ->where('queue', $queue)
                ->where('status', JobStatus::QUEUED->value)
                ->where(fn (Builder $q) => $q->whereNull('available_at')->orWhere('available_at', '<=', $now))
                ->count(),
            'delayed' => (int) $this->table($prefix.'jobs')
                ->where('queue', $queue)
                ->where('status', JobStatus::QUEUED->value)
                ->where('available_at', '>', $now)
                ->count(),
        ];

        $memoryLimit = $this->table($prefix.'jobs')
            ->where('queue', $queue)
            ->whereNotNull('worker_memory_limit_mb')
            ->max('worker_memory_limit_mb');

        return QueueTimeline::fromArray([
            'buckets' => $buckets,
            'live' => $live,
            'memory_limit_mb' => is_numeric($memoryLimit) ? (float) $memoryLimit : null,
        ]);
    }

    /**
     * @param  array<string, string>|null  $filter  Optional filter e.g. ['queue' => 'payments']
     * @return array<int, ThroughputBucket>
     */
    public function computeThroughputByMinute(int $minutes = 60, ?array $filter = null): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $dateFormat = DatabaseExpressionHelper::minuteBucket('queued_at', $this->databaseDriver());

        $query = $this->table($prefix.'jobs')
            ->select([
                DB::raw("{$dateFormat} as minute"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->where('queued_at', '>=', now()->subMinutes($minutes))
            ->groupBy(DB::raw($dateFormat))
            ->orderBy(DB::raw($dateFormat));

        if ($filter !== null) {
            foreach ($filter as $column => $value) {
                $query->where($column, $value);
            }
        }

        $rows = $query->get()->keyBy('minute');

        // Fill gaps with zeros so every minute has an entry
        $result = [];
        $period = CarbonPeriod::create(
            now()->subMinutes($minutes)->startOfMinute(),
            '1 minute',
            now()->startOfMinute()
        );

        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->format('Y-m-d H:i');
            $row = $rows->get($key);
            $result[] = ThroughputBucket::fromArray([
                'minute' => $key,
                'total' => $row ? (int) $row->total : 0,
                'completed' => $row ? (int) $row->completed : 0,
                'failed' => $row ? (int) $row->failed : 0,
            ]);
        }

        return $result;
    }

    /**
     * Apply the configured metrics time window to limit aggregation queries
     * to recent data, preventing full-table scans on high-throughput systems.
     */
    private function applyMetricsWindow(Builder $query): void
    {
        /** @var int|null $hours */
        $hours = config('queue-monitor.metrics_window_hours');

        if ($hours !== null && $hours > 0) {
            $query->where('created_at', '>=', now()->subHours($hours));
        }
    }

    private function table(string $table): Builder
    {
        return DB::connection($this->databaseConnectionName())->table($table);
    }

    private function databaseDriver(): string
    {
        return DB::connection($this->databaseConnectionName())->getDriverName();
    }

    private function databaseConnectionName(): ?string
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');

        return $connection;
    }

    /**
     * Cache a value with configurable TTL and atomic lock to prevent cache stampede.
     *
     * When multiple concurrent requests try to compute the same expensive statistic,
     * only one process does the actual computation. Others wait for the result.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        if (! config('queue-monitor.cache.enabled', true)) {
            return $callback();
        }

        /** @var int $cacheTtl */
        $cacheTtl = config('queue-monitor.cache.ttl', 300);

        /** @var string|null $cacheStore */
        $cacheStore = config('queue-monitor.cache.store');

        $cache = $cacheStore !== null
            ? Cache::store($cacheStore)
            : Cache::store();

        $fullKey = $this->dashboardCache->scopedKey($key);
        $effectiveTtl = $ttl ?? $cacheTtl;

        // Return cached value if available
        $cached = $cache->get($fullKey);
        if ($cached !== null) {
            return $cached;
        }

        // Use atomic lock to prevent cache stampede — only one process computes at a time.
        // Others wait up to 30s for the lock holder to finish, then get the cached result.
        if ($cache instanceof LockProvider) {
            $lockKey = $fullKey.':lock';

            return $cache->lock($lockKey, 15)->block(30, function () use ($cache, $fullKey, $effectiveTtl, $callback) {
                // Double-check after acquiring lock (another process may have filled it)
                $cached = $cache->get($fullKey);
                if ($cached !== null) {
                    return $cached;
                }

                $value = $callback();
                $cache->put($fullKey, $value, $effectiveTtl);

                return $value;
            });
        }

        // Fallback for cache stores that don't support locking (e.g. array, file on some drivers)
        $value = $callback();
        $cache->put($fullKey, $value, $effectiveTtl);

        return $value;
    }
}
