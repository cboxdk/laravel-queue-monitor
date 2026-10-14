<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Cbox\LaravelQueueMonitor\Tests\Support\NestedSyncJob;
use Illuminate\Queue\Events\JobQueued;

/**
 * Laravel's SyncJob::getJobId() returns '', so before JobKey every sync job was
 * recorded under the same job_id and a nested sync dispatch wrote its lifecycle
 * onto the outer job's row.
 */
beforeEach(function () {
    config()->set('queue.default', 'sync');
});

test('a sync job dispatching another sync job records both rows under distinct keys', function () {
    dispatch_sync(new NestedSyncJob);

    // The outer job starts first, so it owns the first row.
    [$outer, $inner] = JobMonitor::query()->orderBy('id')->get()->all();

    expect(JobMonitor::count())->toBe(2)
        ->and($outer->job_class)->toBe(NestedSyncJob::class)
        ->and($outer->status)->toBe(JobStatus::COMPLETED)
        ->and($inner->status)->toBe(JobStatus::COMPLETED)
        ->and($outer->job_id)->not->toBe('')
        ->and($inner->job_id)->not->toBe('')
        ->and($outer->job_id)->not->toBe($inner->job_id);

    // The key is the payload uuid, the same value laravel-queue-metrics reports.
    expect($outer->job_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('an outer sync job failing after its inner sync job completed fails only its own row', function () {
    expect(fn () => dispatch_sync(new NestedSyncJob(failAfterInner: true)))
        ->toThrow(RuntimeException::class, 'Outer job failed after the inner job completed');

    [$outer, $inner] = JobMonitor::query()->orderBy('id')->get()->all();

    expect(JobMonitor::count())->toBe(2)
        ->and($outer->job_class)->toBe(NestedSyncJob::class)
        ->and($outer->status)->toBe(JobStatus::FAILED)
        ->and($outer->exception_message)->toBe('Outer job failed after the inner job completed')
        ->and($inner->status)->toBe(JobStatus::COMPLETED)
        ->and($inner->exception_class)->toBeNull();
});

test('a queue-metrics event keyed by the payload uuid updates the matching sync row', function () {
    $outer = JobMonitor::factory()->processing()->create([
        'job_id' => 'outer-payload-uuid',
        'cpu_time_ms' => null,
        'memory_peak_mb' => null,
    ]);
    $inner = JobMonitor::factory()->processing()->create([
        'job_id' => 'inner-payload-uuid',
        'cpu_time_ms' => null,
        'memory_peak_mb' => null,
    ]);

    event(new JobMetricsCompleted(
        jobId: 'outer-payload-uuid',
        jobClass: NestedSyncJob::class,
        connection: 'sync',
        queue: 'default',
        durationMs: 12.0,
        memoryMb: 8.5,
        cpuTimeMs: 4.25,
    ));

    expect($outer->refresh()->cpu_time_ms)->toEqual(4.25)
        ->and($outer->memory_peak_mb)->toEqual(8.5)
        ->and($inner->refresh()->cpu_time_ms)->toBeNull();
});

test('a queued event with a driver id keeps recording that id', function () {
    $monitor = app(RecordJobQueuedAction::class)->execute(
        new JobQueued('redis', 'default', 'redis-id-42', new ExampleJob, '{"uuid":"payload-uuid"}', null)
    );

    expect($monitor?->job_id)->toBe('redis-id-42');
});

test('a queued event without a driver id records the payload uuid', function (string|int|null $driverJobId) {
    $monitor = app(RecordJobQueuedAction::class)->execute(
        new JobQueued('custom', 'default', $driverJobId, new ExampleJob, '{"uuid":"payload-uuid"}', null)
    );

    expect($monitor?->job_id)->toBe('payload-uuid');
})->with([
    'null id' => [null],
    'empty id' => [''],
]);
