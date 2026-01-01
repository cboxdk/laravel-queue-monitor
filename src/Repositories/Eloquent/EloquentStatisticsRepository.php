<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Repositories\Eloquent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

final class EloquentStatisticsRepository implements StatisticsRepositoryContract
{
    public function getGlobalStatistics(): array
    {
        return $this->remember('global_statistics', fn () => $this->computeGlobalStatistics());
    }

    private function computeGlobalStatistics(): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $stats = DB::table($prefix.'jobs')
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as timeout'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing'),
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
            ])
            ->first();

        $total = (int) $stats->total;
        $completed = (int) $stats->completed;
        $failed = (int) $stats->failed + (int) $stats->timeout;

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'timeout' => (int) $stats->timeout,
            'processing' => (int) $stats->processing,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'avg_duration_ms' => $stats->avg_duration_ms !== null ? round((float) $stats->avg_duration_ms, 2) : null,
            'max_duration_ms' => $stats->max_duration_ms !== null ? (int) $stats->max_duration_ms : null,
            'avg_memory_mb' => $stats->avg_memory_mb !== null ? round((float) $stats->avg_memory_mb, 2) : null,
            'max_memory_mb' => $stats->max_memory_mb !== null ? round((float) $stats->max_memory_mb, 2) : null,
        ];
    }

    public function getServerStatistics(?string $serverName = null): array
    {
        $cacheKey = 'server_statistics_'.($serverName ?? 'all');

        return $this->remember($cacheKey, fn () => $this->computeServerStatistics($serverName));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeServerStatistics(?string $serverName = null): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = DB::table($prefix.'jobs')
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

        if ($serverName !== null) {
            $query->where('server_name', $serverName);
        }

        return $query->get()
            ->map(fn ($row) => [
                'server_name' => $row->server_name,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'failed' => (int) $row->failed,
                'success_rate' => (int) $row->total > 0
                    ? round(((int) $row->completed / (int) $row->total) * 100, 2)
                    : 0,
                'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
            ])
            ->toArray();
    }

    public function getQueueStatistics(?string $queue = null): array
    {
        $cacheKey = 'queue_statistics_'.($queue ?? 'all');

        return $this->remember($cacheKey, fn () => $this->computeQueueStatistics($queue));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeQueueStatistics(?string $queue = null): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = DB::table($prefix.'jobs')
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

        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        return $query->get()
            ->map(fn ($row) => [
                'queue' => $row->queue,
                'connection' => $row->connection,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'failed' => (int) $row->failed,
                'processing' => (int) $row->processing,
                'success_rate' => (int) $row->total > 0
                    ? round(((int) $row->completed / (int) $row->total) * 100, 2)
                    : 0,
                'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
            ])
            ->toArray();
    }

    public function getJobClassStatistics(?string $jobClass = null): array
    {
        $cacheKey = 'job_class_statistics_'.($jobClass !== null ? md5($jobClass) : 'all');

        return $this->remember($cacheKey, fn () => $this->computeJobClassStatistics($jobClass));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeJobClassStatistics(?string $jobClass = null): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $query = DB::table($prefix.'jobs')
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

        if ($jobClass !== null) {
            $query->where('job_class', $jobClass);
        }

        return $query->get()
            ->map(fn ($row) => [
                'job_class' => $row->job_class,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'failed' => (int) $row->failed,
                'success_rate' => (int) $row->total > 0
                    ? round(((int) $row->completed / (int) $row->total) * 100, 2)
                    : 0,
                'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms, 2) : null,
                'max_duration_ms' => $row->max_duration_ms !== null ? (int) $row->max_duration_ms : null,
            ])
            ->toArray();
    }

    public function getFailurePatterns(): array
    {
        return $this->remember('failure_patterns', fn () => $this->computeFailurePatterns());
    }

    /**
     * @return array{top_exceptions: array<int, array<string, mixed>>}
     */
    private function computeFailurePatterns(): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $exceptionStats = DB::table($prefix.'jobs')
            ->select([
                'exception_class',
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT job_class) as affected_job_classes'),
            ])
            ->whereNotNull('exception_class')
            ->groupBy('exception_class')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'exception_class' => $row->exception_class,
                'count' => (int) $row->count,
                'affected_job_classes' => (int) $row->affected_job_classes,
            ])
            ->toArray();

        return [
            'top_exceptions' => $exceptionStats,
        ];
    }

    public function getQueueHealth(): array
    {
        return $this->remember('queue_health', fn () => $this->computeQueueHealth(), 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeQueueHealth(): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $queueHealth = DB::table($prefix.'jobs')
            ->select([
                'queue',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as processing'),
                DB::raw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed'),
                DB::raw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms'),
            ])
            ->addBinding([
                JobStatus::PROCESSING->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('queue')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total;
                $processing = (int) $row->processing;
                $failed = (int) $row->failed;
                $avgDuration = $row->avg_duration_ms !== null ? (float) $row->avg_duration_ms : null;

                // Simple health score calculation
                $failureRate = $total > 0 ? ($failed / $total) : 0;
                $health = 100 - ($failureRate * 100);

                return [
                    'queue' => $row->queue,
                    'total_last_hour' => $total,
                    'processing' => $processing,
                    'failed' => $failed,
                    'avg_duration_ms' => $avgDuration !== null ? round($avgDuration, 2) : null,
                    'health_score' => round($health, 2),
                    'status' => $health >= 95 ? 'healthy' : ($health >= 75 ? 'degraded' : 'unhealthy'),
                ];
            })
            ->toArray();

        return $queueHealth;
    }

    /**
     * Cache a value with configurable TTL
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        /** @var bool $cacheEnabled */
        $cacheEnabled = config('queue-monitor.cache.enabled', true);

        if (! $cacheEnabled) {
            return $callback();
        }

        /** @var string $prefix */
        $prefix = config('queue-monitor.cache.prefix', 'queue_monitor_');
        /** @var string|null $store */
        $store = config('queue-monitor.cache.store');
        /** @var int $defaultTtl */
        $defaultTtl = config('queue-monitor.cache.ttl', 60);

        $cache = $store !== null ? Cache::store($store) : Cache::store();

        return $cache->remember(
            $prefix.$key,
            $ttl ?? $defaultTtl,
            $callback
        );
    }
}
