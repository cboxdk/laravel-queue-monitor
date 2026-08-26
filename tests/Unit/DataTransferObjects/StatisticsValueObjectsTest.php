<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\FailurePatterns;
use Cbox\LaravelQueueMonitor\DataTransferObjects\GlobalStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobClassStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueHealthEntry;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueTimeline;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ServerStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ThroughputBucket;
use Cbox\LaravelQueueMonitor\DataTransferObjects\TimelineBucket;

/**
 * Representative arrays are written in the exact key order the concrete
 * repository emits, so the round-trip and json_encode assertions double as an
 * edge-identity guard: the value objects must serialize byte-for-key like the
 * arrays they replaced.
 *
 * @return array<string, array{class-string, array<string, mixed>}>
 */
dataset('statistics value objects', [
    'GlobalStatistics' => [
        GlobalStatistics::class,
        [
            'total' => 10,
            'completed' => 7,
            'failed' => 3,
            'timeout' => 1,
            'processing' => 2,
            'queue_backlog' => 4,
            'success_rate' => 70.0,
            'failure_rate' => 30.0,
            'avg_duration_ms' => 123.45,
            'max_duration_ms' => 999,
            'avg_memory_mb' => 12.34,
            'max_memory_mb' => 56.78,
        ],
    ],
    'ServerStatistics' => [
        ServerStatistics::class,
        [
            'server_name' => 'web-1',
            'total_jobs' => 5,
            'completed' => 4,
            'failed' => 1,
            'success_rate' => 80.0,
            'avg_duration_ms' => 250.5,
        ],
    ],
    'QueueStatistics' => [
        QueueStatistics::class,
        [
            'queue' => 'default',
            'connection' => 'redis',
            'total_jobs' => 8,
            'completed' => 6,
            'failed' => 2,
            'processing' => 1,
            'success_rate' => 75.0,
            'avg_duration_ms' => 100.0,
        ],
    ],
    'JobClassStatistics' => [
        JobClassStatistics::class,
        [
            'job_class' => 'App\\Jobs\\ProcessPayment',
            'total_jobs' => 3,
            'completed' => 2,
            'failed' => 1,
            'success_rate' => 66.67,
            'avg_duration_ms' => 50.0,
            'max_duration_ms' => 120,
        ],
    ],
    'FailurePatterns' => [
        FailurePatterns::class,
        [
            'top_exceptions' => [
                ['exception_class' => 'RuntimeException', 'count' => 5, 'affected_job_classes' => 2],
                ['exception_class' => 'LogicException', 'count' => 1, 'affected_job_classes' => 1],
            ],
        ],
    ],
    'QueueHealthEntry' => [
        QueueHealthEntry::class,
        [
            'queue' => 'default',
            'total_last_hour' => 10,
            'processing' => 1,
            'failed' => 2,
            'avg_duration_ms' => 99.9,
            'health_score' => 80.0,
            'status' => 'degraded',
        ],
    ],
    'ThroughputBucket' => [
        ThroughputBucket::class,
        [
            'minute' => '2025-01-01 12:00',
            'total' => 5,
            'completed' => 4,
            'failed' => 1,
        ],
    ],
    'TimelineBucket' => [
        TimelineBucket::class,
        [
            'minute' => '2025-01-01 12:00',
            'ts' => '2025-01-01T12:00:00+00:00',
            'total' => 2,
            'completed' => 1,
            'failed' => 1,
            'avg_duration_ms' => 200.0,
            'max_duration_ms' => 300,
            'avg_memory_mb' => 40.5,
            'max_memory_mb' => 80.0,
        ],
    ],
    'QueueTimeline' => [
        QueueTimeline::class,
        [
            'buckets' => [
                [
                    'minute' => '2025-01-01 12:00',
                    'ts' => '2025-01-01T12:00:00+00:00',
                    'total' => 2,
                    'completed' => 1,
                    'failed' => 1,
                    'avg_duration_ms' => 200.0,
                    'max_duration_ms' => 300,
                    'avg_memory_mb' => 40.5,
                    'max_memory_mb' => 80.0,
                ],
            ],
            'live' => ['processing' => 1, 'waiting' => 2, 'delayed' => 0],
            'memory_limit_mb' => 256.0,
        ],
    ],
]);

test('fromArray then toArray round-trips a representative array', function (string $class, array $original) {
    /** @var GlobalStatistics|ServerStatistics|QueueStatistics|JobClassStatistics|FailurePatterns|QueueHealthEntry|ThroughputBucket|TimelineBucket|QueueTimeline $vo */
    $vo = $class::fromArray($original);

    expect($vo->toArray())->toBe($original);
})->with('statistics value objects');

test('json_encode of the value object equals json_encode of the original array', function (string $class, array $original) {
    $vo = $class::fromArray($original);

    expect(json_encode($vo))->toBe(json_encode($original));
})->with('statistics value objects');

test('array access returns the same values as the serialized array', function (string $class, array $original) {
    $vo = $class::fromArray($original);

    foreach ($original as $key => $value) {
        expect($vo[$key])->toBe($value);
        expect(isset($vo[$key]))->toBeTrue();
    }
})->with('statistics value objects');

test('the empty GlobalStatistics instance matches the no-data zero shape', function () {
    expect((new GlobalStatistics)->toArray())->toBe([
        'total' => 0,
        'completed' => 0,
        'failed' => 0,
        'timeout' => 0,
        'processing' => 0,
        'queue_backlog' => 0,
        'success_rate' => 0,
        'failure_rate' => 0,
        'avg_duration_ms' => null,
        'max_duration_ms' => null,
        'avg_memory_mb' => null,
        'max_memory_mb' => null,
    ]);
});

test('value objects are immutable through array access', function () {
    $vo = new GlobalStatistics(total: 5);

    expect(fn () => $vo['total'] = 9)->toThrow(LogicException::class);
});
