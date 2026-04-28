<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
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
        $defaultTarget = $slaTargets['*'] ?? 30;

        // Single aggregation query: count total and within-SLA per queue
        $pickupExpr = $driver === 'sqlite'
            ? '(julianday(started_at) - julianday(COALESCE(available_at, queued_at))) * 86400'
            : 'TIMESTAMPDIFF(SECOND, COALESCE(available_at, queued_at), started_at)';

        $rows = DB::table($prefix.'jobs')
            ->selectRaw('queue, COUNT(*) as total')
            ->whereNotNull('started_at')
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('queue')
            ->get();

        if ($rows->isEmpty()) {
            return ['available' => true, 'per_queue' => [], 'source' => empty($slaTargets) ? 'default' : 'autoscale'];
        }

        // For per-queue SLA with different targets, we need pickup times per queue.
        // Use a single query to get all pickup seconds, grouped by queue.
        $allPickups = DB::table($prefix.'jobs')
            ->selectRaw("queue, {$pickupExpr} as pickup_seconds")
            ->whereNotNull('started_at')
            ->where('created_at', '>=', now()->subHour())
            ->get()
            ->groupBy('queue');

        $perQueue = [];
        foreach ($allPickups as $queueName => $queueRows) {
            $queueStr = (string) $queueName;
            $target = $slaTargets[$queueStr] ?? $defaultTarget;
            $total = $queueRows->count();
            $within = $queueRows->filter(fn ($row) => (float) $row->pickup_seconds <= $target)->count();

            $perQueue[] = [
                'queue' => $queueStr,
                'target_seconds' => $target,
                'compliance' => $total > 0 ? round(($within / $total) * 100, 1) : 100.0,
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
                    'sla_breach_predictions' => $summaryCounts['sla_breach_predicted'] ?? 0,
                ];

                // Breach severity stats
                /** @var object{avg_breach_seconds: float|null, max_breach_percentage: float|null}|null $breachData */
                $breachData = DB::connection($dbConnection)
                    ->table($prefix.'scaling_events')
                    ->where('created_at', '>=', now()->subHour())
                    ->where('action', 'sla_breach')
                    ->whereNotNull('breach_seconds')
                    ->selectRaw('AVG(breach_seconds) as avg_breach_seconds, MAX(breach_percentage) as max_breach_percentage')
                    ->first();

                if ($breachData !== null && $breachData->avg_breach_seconds !== null) {
                    $breachSeverity = [
                        'avg_breach_seconds' => round((float) $breachData->avg_breach_seconds, 1),
                        'max_breach_percentage' => round((float) $breachData->max_breach_percentage, 1),
                    ];
                }
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
            'autoscale_version' => $this->detectAutoscaleVersion(),
            'breach_severity' => $breachSeverity ?? null,
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
     * Cluster orchestration data for v3 autoscale. Returns null when not available.
     *
     * @return array<string, mixed>|null
     */
    public function getClusterData(): ?array
    {
        /** @var string|null $dbConnection */
        $dbConnection = config('queue-monitor.database.connection');
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        if (! Schema::connection($dbConnection)->hasTable($prefix.'cluster_events')) {
            return null;
        }

        try {
            if (! ClusterEvent::exists()) {
                return null;
            }

            // Topology: derive from latest presence_changed or manager start/stop events
            $latestPresence = ClusterEvent::ofType('presence_changed')
                ->orderByDesc('created_at')
                ->first();

            /** @var string $clusterId */
            $clusterId = $latestPresence !== null
                ? $latestPresence->cluster_id
                : (string) (ClusterEvent::orderByDesc('created_at')->value('cluster_id') ?? 'unknown');

            // Active managers: started but not yet stopped (last 24h window)
            // A manager is active if its latest start event is more recent than its latest stop event.
            $startedManagers = ClusterEvent::forCluster($clusterId)
                ->ofType('manager_started')
                ->recent(24)
                ->orderByDesc('created_at')
                ->get()
                ->unique('manager_id');

            $latestStopByManager = ClusterEvent::forCluster($clusterId)
                ->ofType('manager_stopped')
                ->recent(24)
                ->orderByDesc('created_at')
                ->get()
                ->unique('manager_id')
                ->keyBy('manager_id');

            $activeManagers = $startedManagers
                ->filter(function (ClusterEvent $m) use ($latestStopByManager): bool {
                    $lastStop = $latestStopByManager->get($m->manager_id);

                    return $lastStop === null || $m->created_at > $lastStop->created_at;
                })
                ->map(fn (ClusterEvent $m) => [
                    'manager_id' => $m->manager_id,
                    'host' => $m->host,
                    'started_at' => $m->meta['started_at'] ?? null,
                ])
                ->values()
                ->all();

            // Current leader
            $latestLeaderChange = ClusterEvent::forCluster($clusterId)
                ->ofType('leader_changed')
                ->orderByDesc('created_at')
                ->first();

            $leaderId = $latestLeaderChange !== null
                ? $latestLeaderChange->leader_id
                : ($latestPresence?->leader_id);

            // Latest scaling signal
            $latestSignal = ClusterEvent::forCluster($clusterId)
                ->ofType('scaling_signal')
                ->orderByDesc('created_at')
                ->first();

            // Signal history for sparkline (last hour, max 50)
            $signalHistory = ClusterEvent::forCluster($clusterId)
                ->ofType('scaling_signal')
                ->recent(1)
                ->orderBy('created_at')
                ->limit(50)
                ->get()
                ->map(fn (ClusterEvent $e) => [
                    'current_hosts' => $e->current_hosts,
                    'recommended_hosts' => $e->recommended_hosts,
                    'current_capacity' => $e->current_capacity,
                    'required_workers' => $e->required_workers,
                    'action' => $e->action,
                    'time' => $e->created_at?->toIso8601String(),
                ])
                ->all();

            // Leader history (last 24h)
            $leaderHistory = ClusterEvent::forCluster($clusterId)
                ->ofType('leader_changed')
                ->recent(24)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (ClusterEvent $e) => [
                    'leader_id' => $e->leader_id,
                    'previous_leader_id' => $e->previous_leader_id,
                    'observed_by' => $e->manager_id,
                    'time' => $e->created_at?->toIso8601String(),
                    'time_human' => $e->created_at?->diffForHumans(),
                ])
                ->all();

            // Manager events (start/stop, last 24h)
            $managerEvents = ClusterEvent::forCluster($clusterId)
                ->whereIn('event_type', ['manager_started', 'manager_stopped'])
                ->recent(24)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (ClusterEvent $e) => [
                    'event_type' => $e->event_type,
                    'manager_id' => $e->manager_id,
                    'host' => $e->host,
                    'reason' => $e->reason,
                    'meta' => $e->meta,
                    'time' => $e->created_at?->toIso8601String(),
                    'time_human' => $e->created_at?->diffForHumans(),
                ])
                ->all();

            return [
                'has_cluster' => true,
                'autoscale_version' => $this->detectAutoscaleVersion() ?? 3,
                'topology' => [
                    'cluster_id' => $clusterId,
                    'leader_id' => $leaderId,
                    'active_managers' => $activeManagers,
                    'host_count' => count($activeManagers),
                ],
                'scaling_signal' => $latestSignal ? [
                    'current_hosts' => $latestSignal->current_hosts,
                    'recommended_hosts' => $latestSignal->recommended_hosts,
                    'current_capacity' => $latestSignal->current_capacity,
                    'required_workers' => $latestSignal->required_workers,
                    'action' => $latestSignal->action,
                    'reason' => $latestSignal->reason,
                    'updated_at' => $latestSignal->created_at?->toIso8601String(),
                ] : null,
                'signal_history' => $signalHistory,
                'leader_history' => $leaderHistory,
                'manager_events' => $managerEvents,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function detectAutoscaleVersion(): ?int
    {
        if (class_exists('Cbox\\LaravelQueueAutoscale\\Events\\ClusterLeaderChanged')) {
            return 3;
        }

        if (class_exists('Cbox\\LaravelQueueAutoscale\\Events\\ScalingDecisionMade')) {
            return 2;
        }

        return null;
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
