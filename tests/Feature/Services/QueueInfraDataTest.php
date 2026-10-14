<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Services\InfrastructureService;

/**
 * One row as laravel-queue-metrics v3 getAllQueuesWithMetrics() returns it:
 * keyed by connection:queue, with the figures nested per section.
 *
 * @return array<string, mixed>
 */
function queueMetricsV3Row(string $connection, string $queue): array
{
    return [
        'connection' => $connection,
        'queue' => $queue,
        'driver' => $connection,
        'depth' => [
            'total' => 12,
            'pending' => 7,
            'scheduled' => 3,
            'reserved' => 2,
            'delayed_due_now' => 0,
            'oldest_job_age_seconds' => 42,
            'oldest_job_age_status' => '2026-10-14T10:00:00+00:00',
        ],
        'performance_60s' => [
            'throughput_per_minute' => 18.5,
            'avg_duration_ms' => 230.0,
            'window_seconds' => 60,
        ],
        'lifetime' => ['failure_rate_percent' => 4.2],
        'workers' => [
            'active_count' => 3,
            'current_busy_percent' => 66.67,
            'lifetime_busy_percent' => 50.0,
        ],
        'baseline' => null,
        'trends' => [],
        'timestamp' => '2026-10-14T10:00:00+00:00',
    ];
}

test('queue infra rows read the nested queue-metrics v3 shape', function () {
    app()->instance('Cbox\LaravelQueueMetrics\Services\QueueMetricsQueryService', new class
    {
        /**
         * @return array<string, array<string, mixed>>
         */
        public function getAllQueuesWithMetrics(): array
        {
            return [
                'sqs:orders.fifo' => queueMetricsV3Row('sqs', 'orders.fifo'),
                'redis:tenant:42' => queueMetricsV3Row('redis', 'tenant:42'),
            ];
        }
    });

    $data = app(InfrastructureService::class)->getQueueInfraData();

    expect($data->available)->toBeTrue()
        ->and($data->queues)->toHaveCount(2);

    $row = $data->queues[0];

    expect($row['connection'])->toBe('sqs')
        ->and($row['queue'])->toBe('orders.fifo')
        ->and($row['depth'])->toBe(12)
        ->and($row['pending'])->toBe(7)
        ->and($row['scheduled'])->toBe(3)
        ->and($row['reserved'])->toBe(2)
        ->and($row['active_workers'])->toBe(3)
        ->and($row['throughput_per_minute'])->toBe(18.5)
        ->and($row['avg_duration_ms'])->toBe(230.0)
        ->and($row['failure_rate'])->toBe(4.2)
        ->and($row['utilization_rate'])->toBe(66.67)
        ->and($row['oldest_job_age'])->toBe(42);

    // Unescaped by queue-metrics, so a colon in a queue name survives intact.
    expect($data->queues[1]['queue'])->toBe('tenant:42');
});

test('queue infra rows still read the flat pre-v3 shape', function () {
    app()->instance('Cbox\LaravelQueueMetrics\Services\QueueMetricsQueryService', new class
    {
        /**
         * @return array<int, array<string, mixed>>
         */
        public function getAllQueuesWithMetrics(): array
        {
            return [[
                'connection' => 'redis',
                'queue' => 'default',
                'depth' => 5,
                'pending' => 4,
                'active_workers' => 2,
                'throughput_per_minute' => 9.0,
                'failure_rate' => 1.5,
            ]];
        }
    });

    $row = app(InfrastructureService::class)->getQueueInfraData()->queues[0];

    expect($row['depth'])->toBe(5)
        ->and($row['pending'])->toBe(4)
        ->and($row['active_workers'])->toBe(2)
        ->and($row['throughput_per_minute'])->toBe(9.0)
        ->and($row['failure_rate'])->toBe(1.5)
        ->and($row['avg_duration_ms'])->toBeNull();
});
