<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provides infrastructure query methods for worker utilization, queue capacity,
 * SLA compliance, and scaling data used by the health dashboard.
 */
final class InfrastructureService
{
    /**
     * @return array<string, mixed>
     */
    public function getWorkerData(): array
    {
        if (! class_exists('Laravel\Horizon\Horizon')) {
            return ['available' => false];
        }

        try {
            $supervisorRepo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            $workloadRepo = app('Laravel\Horizon\Contracts\WorkloadRepository');
            $metricsRepo = app('Laravel\Horizon\Contracts\MetricsRepository');

            /** @var array<int, object> $supervisors */
            $supervisors = $supervisorRepo->all();
            /** @var array<int, array<string, mixed>> $workload */
            $workload = $workloadRepo->get();

            $totalProcesses = 0;
            $supervisorData = [];
            foreach ($supervisors as $sup) {
                /** @var array<string, mixed> $supArray */
                $supArray = (array) $sup;
                /** @var array<string, int> $supProcesses */
                $supProcesses = (array) ($supArray['processes'] ?? []);
                /** @var int $processes */
                $processes = collect($supProcesses)->sum();
                $totalProcesses += $processes;
                $supervisorData[] = [
                    'name' => $supArray['name'] ?? '',
                    'status' => $supArray['status'] ?? 'unknown',
                    'processes' => $processes,
                    'queues' => array_keys($supProcesses),
                ];
            }

            return [
                'available' => true,
                'total_processes' => $totalProcesses,
                'supervisors' => $supervisorData,
                'workload' => collect($workload)->map(fn (array $w) => [
                    'queue' => $w['name'],
                    'length' => $w['length'],
                    'wait' => $w['wait'],
                    'processes' => $w['processes'],
                ])->values()->all(),
                'jobs_per_minute' => $metricsRepo->jobsProcessedPerMinute(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['available' => false];
        }
    }

    /**
     * Breakdown of job processing by worker type (horizon, autoscale, queue_work) per queue.
     * Shows which manager handles which queue and relative workload distribution.
     *
     * @return array<string, mixed>
     */
    public function getWorkerTypeBreakdown(): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        // Per worker_type + queue: job count, avg duration, failure rate
        /** @var array<int, array<string, mixed>> $breakdown */
        $breakdown = DB::table($prefix.'jobs')
            ->selectRaw('worker_type, queue, COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms,
                COUNT(DISTINCT worker_id) as unique_workers')
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::FAILED->value,
                JobStatus::TIMEOUT->value,
            ])
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('worker_type', 'queue')
            ->orderBy('worker_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get()
            ->map(fn ($row) => [
                'worker_type' => $row->worker_type,
                'queue' => $row->queue,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'failed' => (int) $row->failed,
                'success_rate' => (int) $row->total > 0 ? round(((int) $row->completed / (int) $row->total) * 100, 1) : 0,
                'avg_duration_ms' => $row->avg_duration_ms !== null ? round((float) $row->avg_duration_ms) : null,
                'unique_workers' => (int) $row->unique_workers,
            ])
            ->all();

        // Aggregate per worker type
        $byType = collect($breakdown)->groupBy('worker_type')->map(function ($items, $type) {
            return [
                'type' => $type,
                'label' => match ($type) {
                    'horizon' => 'Horizon',
                    'autoscale' => 'Autoscale',
                    'queue_work' => 'Queue Worker',
                    default => ucfirst((string) $type),
                },
                'total_jobs' => $items->sum('total'),
                'total_workers' => $items->sum('unique_workers'),
                'queues' => $items->pluck('queue')->unique()->values()->all(),
                'breakdown' => $items->values()->all(),
            ];
        })->values()->all();

        return [
            'by_type' => $byType,
            'per_queue' => $breakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueueInfraData(): array
    {
        if (! class_exists('Cbox\LaravelQueueMetrics\Services\QueueMetricsQueryService')) {
            return ['available' => false];
        }

        try {
            $service = app('Cbox\LaravelQueueMetrics\Services\QueueMetricsQueryService');
            /** @var array<int, array<string, mixed>> $queues */
            $queues = $service->getAllQueuesWithMetrics();

            return [
                'available' => true,
                'queues' => collect($queues)->map(function (array $q): array {
                    /** @var array<string, mixed>|null $health */
                    $health = $q['health'] ?? null;

                    return [
                        'connection' => $q['connection'] ?? null,
                        'queue' => $q['queue'] ?? $q['name'] ?? 'unknown',
                        'depth' => is_array($q['depth'] ?? 0) ? ($q['depth']['total'] ?? 0) : ($q['depth'] ?? 0),
                        'pending' => $q['pending'] ?? 0,
                        'scheduled' => $q['scheduled'] ?? 0,
                        'reserved' => $q['reserved'] ?? 0,
                        'active_workers' => $q['active_workers'] ?? 0,
                        'throughput_per_minute' => $q['throughput_per_minute'] ?? 0,
                        'avg_duration_ms' => $q['avg_duration_ms'] ?? $q['avgDuration'] ?? null,
                        'failure_rate' => $q['failure_rate'] ?? $q['failureRate'] ?? 0,
                        'utilization_rate' => $q['utilization_rate'] ?? $q['utilizationRate'] ?? 0,
                        'oldest_job_age' => $q['oldest_job_age'] ?? $q['oldestJobAge'] ?? 0,
                        'health_status' => (is_array($health) ? ($health['status'] ?? null) : null) ?? $q['ageStatus'] ?? 'unknown',
                        'health_score' => is_array($health) ? ($health['score'] ?? 0) : 0,
                    ];
                })->values()->all(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['available' => false];
        }
    }

    /**
     * SLA compliance per queue, using autoscale config targets when available.
     * Falls back to a default 30s target if autoscale is not configured.
     *
     * @return array<string, mixed>
     */
    public function getSlaData(): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');
        $driver = DB::connection()->getDriverName();

        // Get SLA targets from autoscale config (per queue)
        $slaTargets = $this->getAutoscaleSlaTargets();

        // Get all queues with jobs in last hour
        $queues = DB::table($prefix.'jobs')
            ->whereNotNull('started_at')
            ->where('created_at', '>=', now()->subHour())
            ->select('queue')
            ->distinct()
            ->pluck('queue')
            ->all();

        if (empty($queues)) {
            return ['available' => true, 'per_queue' => [], 'source' => empty($slaTargets) ? 'default' : 'autoscale'];
        }

        $perQueue = [];
        foreach ($queues as $queue) {
            $queueStr = (string) $queue;
            $target = $slaTargets[$queueStr] ?? $slaTargets['*'] ?? 30;

            $total = DB::table($prefix.'jobs')
                ->whereNotNull('started_at')
                ->where('created_at', '>=', now()->subHour())
                ->where('queue', $queueStr)
                ->count();

            if ($total === 0) {
                $perQueue[] = ['queue' => $queueStr, 'target_seconds' => $target, 'compliance' => 100.0, 'total' => 0, 'within' => 0, 'breached' => 0];

                continue;
            }

            if ($driver === 'sqlite') {
                $within = DB::table($prefix.'jobs')
                    ->whereNotNull('started_at')
                    ->where('created_at', '>=', now()->subHour())
                    ->where('queue', $queueStr)
                    ->whereRaw('(julianday(started_at) - julianday(COALESCE(available_at, queued_at))) * 86400 <= ?', [$target])
                    ->count();
            } else {
                $within = DB::table($prefix.'jobs')
                    ->whereNotNull('started_at')
                    ->where('created_at', '>=', now()->subHour())
                    ->where('queue', $queueStr)
                    ->whereRaw('TIMESTAMPDIFF(SECOND, COALESCE(available_at, queued_at), started_at) <= ?', [$target])
                    ->count();
            }

            $perQueue[] = [
                'queue' => $queueStr,
                'target_seconds' => $target,
                'compliance' => round(($within / $total) * 100, 1),
                'total' => $total,
                'within' => $within,
                'breached' => $total - $within,
            ];
        }

        // Sort by compliance ascending (worst first)
        usort($perQueue, fn ($a, $b) => $a['compliance'] <=> $b['compliance']);

        return [
            'available' => true,
            'per_queue' => $perQueue,
            'source' => empty($slaTargets) ? 'default' : 'autoscale',
        ];
    }

    /**
     * Get SLA targets from autoscale config. Returns queue => seconds mapping.
     *
     * @return array<string, int>
     */
    private function getAutoscaleSlaTargets(): array
    {
        // Try autoscale config
        /** @var array<string, mixed> $queues */
        $queues = config('queue-autoscale.queues', []);
        /** @var array<string, mixed> $defaults */
        $defaults = config('queue-autoscale.sla_defaults', []);

        $targets = [];

        // Default SLA from autoscale defaults
        if (isset($defaults['max_pickup_time_seconds'])) {
            $targets['*'] = (int) $defaults['max_pickup_time_seconds'];
        }

        // Per-queue overrides
        foreach ($queues as $queueKey => $queueConfig) {
            if (is_array($queueConfig) && isset($queueConfig['max_pickup_time_seconds'])) {
                $targets[(string) $queueKey] = (int) $queueConfig['max_pickup_time_seconds'];
            }
        }

        return $targets;
    }

    /**
     * @return array<string, mixed>
     */
    public function getScalingData(): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        // Worker utilization from job data
        $totalProcessingMs = (int) DB::table($prefix.'jobs')
            ->whereNotNull('duration_ms')
            ->where('started_at', '>=', now()->subHour())
            ->sum('duration_ms');

        // Busy workers = unique workers that processed jobs in the last hour
        $busyWorkers = (int) DB::table($prefix.'jobs')
            ->where('started_at', '>=', now()->subHour())
            ->distinct()
            ->count('worker_id');

        // Total workers = from Horizon if available, otherwise from job data
        $horizonWorkers = $this->getHorizonTotalProcesses();
        $totalWorkers = $horizonWorkers > 0 ? $horizonWorkers : max($busyWorkers, 1);

        $windowMs = 3600 * 1000;
        $totalCapacityMs = $totalWorkers * $windowMs;
        $utilization = min(100, round(($totalProcessingMs / $totalCapacityMs) * 100, 1));

        // Scaling history from scaling_events table
        /** @var string|null $dbConnection */
        $dbConnection = config('queue-monitor.database.connection');
        $hasScalingTable = Schema::connection($dbConnection)
            ->hasTable($prefix.'scaling_events');

        $scalingHistory = [];
        $scalingSummary = [];

        if ($hasScalingTable) {
            try {
                $scalingHistory = ScalingEvent::orderByDesc('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (ScalingEvent $e) => [
                        'queue' => $e->queue,
                        'action' => $e->action,
                        'current_workers' => $e->current_workers,
                        'target_workers' => $e->target_workers,
                        'reason' => $e->reason,
                        'predicted_pickup_time' => $e->predicted_pickup_time,
                        'sla_target' => $e->sla_target,
                        'sla_breach_risk' => $e->sla_breach_risk,
                        'time' => $e->created_at->toIso8601String(),
                        'time_human' => $e->created_at->diffForHumans(),
                    ])
                    ->all();

                // Summary: aggregate counts (no full table load)
                $summaryCounts = ScalingEvent::where('created_at', '>=', now()->subHour())
                    ->selectRaw('action, COUNT(*) as cnt')
                    ->groupBy('action')
                    ->pluck('cnt', 'action')
                    ->all();
                $scalingSummary = [
                    'total_decisions' => array_sum($summaryCounts),
                    'scale_ups' => $summaryCounts['scale_up'] ?? 0,
                    'scale_downs' => $summaryCounts['scale_down'] ?? 0,
                    'sla_breaches' => $summaryCounts['sla_breach'] ?? 0,
                    'sla_recoveries' => $summaryCounts['sla_recovered'] ?? 0,
                ];
            } catch (\Throwable) {
                // Silently handle if table exists but queries fail
            }
        }

        return [
            'utilization' => [
                'percentage' => $utilization,
                'total_processing_ms' => $totalProcessingMs,
                'busy_workers' => $busyWorkers,
                'total_workers' => $totalWorkers,
                'window_seconds' => 3600,
                'status' => $utilization > 85 ? 'overloaded' : ($utilization > 60 ? 'optimal' : ($utilization > 30 ? 'underutilized' : 'idle')),
            ],
            'history' => $scalingHistory,
            'summary' => $scalingSummary,
            'has_autoscale' => $hasScalingTable && count($scalingHistory) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCapacityData(): array
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $driver = DB::connection()->getDriverName();
        $dateFormat = $driver === 'sqlite' ? "strftime('%Y-%m-%d %H:%M', queued_at)" : "DATE_FORMAT(queued_at, '%Y-%m-%d %H:%i')";

        $peakThroughput = DB::table($prefix.'jobs')
            ->selectRaw("queue, $dateFormat as minute, COUNT(*) as job_count")
            ->where('queued_at', '>=', now()->subHour())
            ->groupBy('queue', DB::raw($dateFormat))
            ->orderByDesc('job_count')
            ->limit(20)
            ->get();

        $queuePeaks = [];
        foreach ($peakThroughput as $row) {
            $queue = $row->queue;
            if (! isset($queuePeaks[$queue]) || $row->job_count > $queuePeaks[$queue]['peak_per_minute']) {
                $queuePeaks[$queue] = [
                    'queue' => $queue,
                    'peak_per_minute' => (int) $row->job_count,
                    'peak_minute' => $row->minute,
                ];
            }
        }

        $avgDurations = DB::table($prefix.'jobs')
            ->selectRaw('queue, AVG(duration_ms) as avg_ms, COUNT(DISTINCT worker_id) as workers')
            ->whereNotNull('duration_ms')
            ->where('started_at', '>=', now()->subHour())
            ->groupBy('queue')
            ->get();

        $capacity = [];
        foreach ($avgDurations as $row) {
            $avgMs = (float) $row->avg_ms;
            $workers = (int) $row->workers;
            $maxJobsPerMinute = $avgMs > 0 ? round(($workers * 60000) / $avgMs, 1) : 0;
            $peak = $queuePeaks[$row->queue]['peak_per_minute'] ?? 0;

            $capacity[] = [
                'queue' => $row->queue,
                'avg_duration_ms' => round($avgMs),
                'workers' => $workers,
                'max_jobs_per_minute' => $maxJobsPerMinute,
                'peak_jobs_per_minute' => $peak,
                'headroom_percent' => $maxJobsPerMinute > 0 ? round((($maxJobsPerMinute - $peak) / $maxJobsPerMinute) * 100, 1) : 0,
                'status' => $maxJobsPerMinute > 0
                    ? ($peak / $maxJobsPerMinute > 0.85 ? 'at_capacity' : ($peak / $maxJobsPerMinute > 0.6 ? 'optimal' : 'over_provisioned'))
                    : 'no_data',
            ];
        }

        return ['queues' => $capacity];
    }

    /**
     * Get total worker process count from Horizon (0 if not available)
     */
    private function getHorizonTotalProcesses(): int
    {
        if (! class_exists('Laravel\Horizon\Horizon')) {
            return 0;
        }

        try {
            $supervisorRepo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            /** @var array<int, object> $supervisors */
            $supervisors = $supervisorRepo->all();
            $total = 0;
            foreach ($supervisors as $sup) {
                /** @var array<string, int> $processes */
                $processes = (array) (((array) $sup)['processes'] ?? []);
                $total += (int) collect($processes)->sum();
            }

            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }
}
