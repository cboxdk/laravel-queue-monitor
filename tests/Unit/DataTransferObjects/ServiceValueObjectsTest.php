<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\AlertEntry;
use Cbox\LaravelQueueMonitor\DataTransferObjects\CapacityData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ClusterData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\FailedJobsReport;
use Cbox\LaravelQueueMonitor\DataTransferObjects\HealthCheckResult;
use Cbox\LaravelQueueMonitor\DataTransferObjects\LiveClusterState;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueInfraData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ReadinessResult;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ScalingData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ScalingTimeline;
use Cbox\LaravelQueueMonitor\DataTransferObjects\SlaData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\SlaQueueCompliance;
use Cbox\LaravelQueueMonitor\DataTransferObjects\StatisticsReport;
use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerTypeBreakdown;

/**
 * Representative arrays are written in the exact key order the concrete services
 * emit, so the round-trip and json_encode assertions double as an edge-identity
 * guard: the value objects must serialize byte-for-key like the arrays they
 * replaced — including the int-vs-float distinction (a bare `0` versus `0.0`).
 *
 * @return array<string, array{class-string, array<string, mixed>}>
 */
dataset('service value objects', [
    'WorkerData (unavailable)' => [
        WorkerData::class,
        ['available' => false],
    ],
    'WorkerData (available)' => [
        WorkerData::class,
        [
            'available' => true,
            'total_processes' => 6,
            'supervisors' => [
                ['name' => 'supervisor-1', 'status' => 'running', 'processes' => 6, 'queues' => ['default', 'high']],
            ],
            'workload' => [
                ['queue' => 'default', 'length' => 3, 'wait' => 12, 'processes' => 2],
            ],
            'jobs_per_minute' => 42,
        ],
    ],
    'WorkerTypeBreakdown' => [
        WorkerTypeBreakdown::class,
        [
            'by_type' => [
                ['type' => 'horizon', 'label' => 'Horizon', 'total_jobs' => 10, 'total_workers' => 2, 'queues' => ['default'], 'breakdown' => []],
            ],
            'per_queue' => [
                ['worker_type' => 'horizon', 'queue' => 'default', 'total' => 10, 'completed' => 9, 'failed' => 1, 'success_rate' => 90.0, 'avg_duration_ms' => 120.0, 'unique_workers' => 2],
            ],
        ],
    ],
    'QueueInfraData (unavailable)' => [
        QueueInfraData::class,
        ['available' => false],
    ],
    'QueueInfraData (available)' => [
        QueueInfraData::class,
        [
            'available' => true,
            'queues' => [
                ['connection' => 'redis', 'queue' => 'default', 'depth' => 5, 'pending' => 5, 'scheduled' => 0, 'reserved' => 0, 'active_workers' => 2, 'throughput_per_minute' => 30, 'avg_duration_ms' => 100, 'failure_rate' => 0, 'utilization_rate' => 50, 'oldest_job_age' => 3, 'health_status' => 'ok', 'health_score' => 100],
            ],
        ],
    ],
    'SlaQueueCompliance' => [
        SlaQueueCompliance::class,
        ['queue' => 'default', 'target_seconds' => 30, 'compliance' => 100.0, 'total' => 10, 'within' => 10, 'breached' => 0],
    ],
    'SlaData' => [
        SlaData::class,
        [
            'available' => true,
            'per_queue' => [
                ['queue' => 'default', 'target_seconds' => 30, 'compliance' => 95.5, 'total' => 100, 'within' => 95, 'breached' => 5],
            ],
            'source' => 'autoscale',
        ],
    ],
    'ScalingData' => [
        ScalingData::class,
        [
            'utilization' => ['percentage' => 45.5, 'total_processing_ms' => 1000, 'busy_workers' => 2, 'total_workers' => 4, 'window_seconds' => 3600, 'status' => 'underutilized'],
            'history' => [
                ['queue' => 'default', 'action' => 'scale_up', 'current_workers' => 2, 'target_workers' => 4, 'reason' => 'load', 'time' => '2025-01-01T12:00:00+00:00', 'time_human' => '1 minute ago'],
            ],
            'summary' => ['total_decisions' => 5, 'scale_ups' => 3, 'scale_downs' => 2, 'sla_breaches' => 0, 'sla_recoveries' => 0, 'sla_breach_predictions' => 0, 'fuse_trips' => 0],
            'open_fuses' => [
                ['connection' => 'redis', 'queue' => 'webhooks', 'state' => 'tripped', 'held_at_workers' => 0, 'reason' => 'errors', 'since' => '2025-01-01T12:00:00+00:00', 'since_human' => '5 minutes ago'],
            ],
            'has_autoscale' => true,
            'autoscale_version' => 3,
            'breach_severity' => ['avg_breach_seconds' => 15.0, 'max_breach_percentage' => 50.0],
        ],
    ],
    'ScalingData (no breach severity)' => [
        ScalingData::class,
        [
            'utilization' => ['percentage' => 0, 'total_processing_ms' => 0, 'busy_workers' => 0, 'total_workers' => 1, 'window_seconds' => 3600, 'status' => 'idle'],
            'history' => [],
            'summary' => [],
            'open_fuses' => [],
            'has_autoscale' => false,
            'autoscale_version' => null,
            'breach_severity' => null,
        ],
    ],
    'CapacityData' => [
        CapacityData::class,
        [
            'queues' => [
                ['queue' => 'default', 'avg_duration_ms' => 120.0, 'workers' => 2, 'max_jobs_per_minute' => 60.0, 'peak_jobs_per_minute' => 30, 'headroom_percent' => 50.0, 'status' => 'optimal'],
            ],
        ],
    ],
    'CapacityData (no-data row keeps int zeros)' => [
        CapacityData::class,
        [
            'queues' => [
                ['queue' => 'idle', 'avg_duration_ms' => 0.0, 'workers' => 0, 'max_jobs_per_minute' => 0, 'peak_jobs_per_minute' => 0, 'headroom_percent' => 0, 'status' => 'no_data'],
            ],
        ],
    ],
    'ClusterData' => [
        ClusterData::class,
        [
            'has_cluster' => true,
            'autoscale_version' => 3,
            'topology' => [
                'cluster_id' => 'c1',
                'leader_id' => 'mgr-1',
                'active_managers' => [
                    ['manager_id' => 'mgr-1', 'host' => 'web-01', 'started_at' => null, 'started_at_human' => '1 minute ago'],
                ],
                'host_count' => 1,
            ],
            'scaling_signal' => ['current_hosts' => 3, 'recommended_hosts' => 5, 'current_capacity' => 15, 'required_workers' => 20, 'action' => 'scale_up', 'reason' => 'load', 'updated_at' => '2025-01-01T12:00:00+00:00'],
            'leadership' => ['unstable' => false, 'changes_in_window' => 1, 'window_seconds' => 60, 'reason' => null, 'flagged_at' => null],
            'signal_history' => [
                ['current_hosts' => 3, 'recommended_hosts' => 5, 'current_capacity' => 15, 'required_workers' => 20, 'action' => 'scale_up', 'time' => '2025-01-01T12:00:00+00:00'],
            ],
            'leader_history' => [
                ['leader_id' => 'mgr-1', 'previous_leader_id' => null, 'observed_by' => 'mgr-1', 'time' => '2025-01-01T12:00:00+00:00', 'time_human' => '1 minute ago'],
            ],
            'manager_events' => [
                ['event_type' => 'manager_started', 'manager_id' => 'mgr-1', 'host' => 'web-01', 'reason' => null, 'meta' => ['started_at' => 123], 'time' => '2025-01-01T12:00:00+00:00', 'time_human' => '1 minute ago'],
            ],
        ],
    ],
    'ClusterData (no scaling signal)' => [
        ClusterData::class,
        [
            'has_cluster' => true,
            'autoscale_version' => 3,
            'topology' => ['cluster_id' => 'c1', 'leader_id' => null, 'active_managers' => [], 'host_count' => 0],
            'scaling_signal' => null,
            'leadership' => ['unstable' => false, 'changes_in_window' => 0, 'window_seconds' => 60, 'reason' => null, 'flagged_at' => null],
            'signal_history' => [],
            'leader_history' => [],
            'manager_events' => [],
        ],
    ],
    'LiveClusterState' => [
        LiveClusterState::class,
        [
            'cluster_id' => 'c1',
            'leader_id' => 'mgr-1',
            'manager_count' => 2,
            'total_workers' => 10,
            'required_workers' => 12,
            'total_worker_capacity' => 20,
            'utilization_percent' => 50,
            'scale_signal' => 'scale_up',
            'generated_at' => '2025-01-01T12:00:00+00:00',
            'hosts' => [
                ['manager_id' => 'mgr-1', 'host' => 'web-01', 'is_leader' => true, 'total_workers' => 5, 'max_workers' => 10, 'available_worker_capacity' => 5, 'capacity_limiter' => null, 'cpu_percent' => null, 'cpu_cores' => null, 'memory_percent' => null, 'memory_total_mb' => null, 'memory_used_mb' => null, 'memory_free_mb' => null, 'queue_count' => 2, 'group_count' => 1, 'queue_workers' => [], 'package_version' => null, 'last_seen_human' => null],
            ],
            'workloads' => [
                ['type' => 'queue', 'connection' => 'redis', 'name' => 'default', 'current_workers' => 2, 'target_workers' => 3, 'worker_min' => 0, 'worker_max' => 10, 'sla_target_seconds' => null, 'pending' => 5, 'oldest_job_age' => 3, 'oldest_job_age_status' => 'normal', 'throughput_per_minute' => 30, 'active_workers' => 2, 'utilization_percent' => 50, 'action' => 'hold'],
            ],
        ],
    ],
    'ScalingTimeline' => [
        ScalingTimeline::class,
        [
            'events' => [
                ['t' => '2025-01-01T12:00:00+00:00', 'current_workers' => 2, 'target_workers' => 5, 'action' => 'scale_up', 'reason' => 'load'],
            ],
            'worker_range' => ['min' => 2, 'max' => 5],
        ],
    ],
    'ScalingTimeline (empty)' => [
        ScalingTimeline::class,
        [
            'events' => [],
            'worker_range' => ['min' => null, 'max' => null],
        ],
    ],
    'HealthCheckResult' => [
        HealthCheckResult::class,
        [
            'status' => 'healthy',
            'checks' => [
                'database' => ['healthy' => true, 'message' => 'ok', 'details' => ['total_jobs' => 5, 'connection' => 'default']],
                'storage' => ['healthy' => true, 'message' => 'ok'],
            ],
            'timestamp' => '2025-01-01T12:00:00+00:00',
        ],
    ],
    'ReadinessResult' => [
        ReadinessResult::class,
        [
            'status' => 'ready',
            'checks' => [
                'access_control' => ['healthy' => true, 'message' => 'ok'],
                'api_middleware' => ['healthy' => true, 'message' => 'ok', 'details' => ['middleware' => ['auth']]],
            ],
            'timestamp' => '2025-01-01T12:00:00+00:00',
        ],
    ],
    'AlertEntry' => [
        AlertEntry::class,
        ['severity' => 'warning', 'message' => '3 jobs stuck in processing', 'count' => 3],
    ],
    'StatisticsReport' => [
        StatisticsReport::class,
        [
            'generated_at' => '2025-01-01T12:00:00+00:00',
            'global' => ['total' => 10, 'completed' => 8],
            'servers' => [['server_name' => 'web-1', 'total_jobs' => 5]],
            'queue_health' => [['queue' => 'default', 'health_score' => 90.0]],
        ],
    ],
    'FailedJobsReport' => [
        FailedJobsReport::class,
        [
            'generated_at' => '2025-01-01T12:00:00+00:00',
            'total_failed' => 3,
            'by_exception' => [
                'RuntimeException' => ['count' => 2, 'jobs' => ['uuid-1', 'uuid-2']],
                'LogicException' => ['count' => 1, 'jobs' => ['uuid-3']],
            ],
            'by_queue' => ['default' => 2, 'high' => 1],
            'recent_failures' => [
                ['uuid' => 'uuid-1', 'job_class' => 'App\\Jobs\\Example', 'exception' => 'RuntimeException', 'failed_at' => '2025-01-01T12:00:00+00:00'],
            ],
        ],
    ],
]);

test('fromArray then toArray round-trips a representative array', function (string $class, array $original) {
    /** @var object{toArray: callable} $vo */
    $vo = $class::fromArray($original);

    expect($vo->toArray())->toBe($original);
})->with('service value objects');

test('json_encode of the value object equals json_encode of the original array', function (string $class, array $original) {
    $vo = $class::fromArray($original);

    expect(json_encode($vo))->toBe(json_encode($original));
})->with('service value objects');

test('array access returns the same values as the serialized array', function (string $class, array $original) {
    $vo = $class::fromArray($original);

    foreach ($original as $key => $value) {
        expect($vo[$key])->toBe($value);
        expect(isset($vo[$key]))->toBeTrue();
    }
})->with('service value objects');

test('an unavailable WorkerData collapses to the single available key', function () {
    expect((new WorkerData(false))->toArray())->toBe(['available' => false]);
});

test('an unavailable QueueInfraData collapses to the single available key', function () {
    expect((new QueueInfraData(false))->toArray())->toBe(['available' => false]);
});

test('service value objects are immutable through array access', function () {
    $entry = new AlertEntry(severity: 'warning', message: 'stuck', count: 1);

    expect(fn () => $entry['severity'] = 'critical')->toThrow(LogicException::class);
    expect(fn () => $entry['severity'])->not->toThrow(LogicException::class);
});
