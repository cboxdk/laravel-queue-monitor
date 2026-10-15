<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMetrics\Events\JobMetricsFailed;
use Cbox\LaravelQueueMonitor\Actions\ResolveStuckJobAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;

/**
 * A job that is merely slow outlives the broker's redelivery window — the SQS
 * visibility timeout, Redis's retry_after — and is handed to a second worker
 * while the first is still running it. Both workers then report on the same
 * job key: the redelivery starts attempt 2 (closing attempt 1 as TIMEOUT, on
 * the assumption its worker was killed), and later attempt 1's worker reports
 * its real outcome.
 *
 * Every outcome must land on the row of the attempt that produced it, and a
 * late real outcome replaces the TIMEOUT that was inferred for it. Before this
 * was fixed the slow attempt's outcome was written to "the latest attempt",
 * i.e. onto attempt 2's row, and attempt 1 stayed TIMEOUT forever.
 *
 * Each test drives the real listeners through event(), with the framework's
 * own RedisJob / SqsJob objects so attempts() is the driver's own answer.
 * Each "worker" is a separate process, so it gets its own InFlightAttempts.
 */
beforeEach(function () {
    // Durations below are exact: time only moves when a test travels.
    $this->freezeSecond();
});

dataset('brokers', [
    'redis (retry_after)' => ['redis'],
    'sqs (visibility timeout)' => ['sqs'],
]);

function brokerJob(string $broker, int $attempt): Job
{
    return match ($broker) {
        'redis' => BrokerJobs::redis('redis-job-7', $attempt),
        'sqs' => BrokerJobs::sqs('5f0c3c8e-sqs-message-7', $attempt),
    };
}

function brokerJobKey(string $broker): string
{
    return match ($broker) {
        'redis' => 'redis-job-7',
        'sqs' => '5f0c3c8e-sqs-message-7',
    };
}

/**
 * Switch to a worker process: its own in-process attempt memory.
 */
function onWorker(string $name): void
{
    $key = "tests.in-flight-attempts.{$name}";

    if (! app()->bound($key)) {
        app()->instance($key, new InFlightAttempts);
    }

    app()->instance(InFlightAttempts::class, app($key));
}

function attemptRow(string $jobKey, int $attempt): JobMonitor
{
    $rows = JobMonitor::where('job_id', $jobKey)->where('attempt', $attempt)->get();

    expect($rows)->toHaveCount(1);

    return $rows->first();
}

/**
 * Worker A starts attempt 1; 120 s later the broker redelivers the job to
 * worker B, which starts attempt 2 while A is still running.
 */
function slowAttemptRedelivered(string $broker): void
{
    onWorker('A');
    event(new JobProcessing($broker, brokerJob($broker, 1)));

    test()->travel(120)->seconds();

    onWorker('B');
    event(new JobProcessing($broker, brokerJob($broker, 2)));
}

test('the redelivery closes the slow attempt as timeout and opens its own row', function (string $broker) {
    slowAttemptRedelivered($broker);

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);
    $second = attemptRow($key, 2);

    expect(JobMonitor::count())->toBe(2)
        ->and($first->status)->toBe(JobStatus::TIMEOUT)
        ->and($first->duration_ms)->toBeNull()
        ->and($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->retried_from_id)->toBe($first->id)
        ->and($first->job_class)->toBe(ExampleJob::class);
})->with('brokers');

test('a slow attempt completing after the redelivery started records on its own row', function (string $broker) {
    slowAttemptRedelivered($broker);
    $this->travel(30)->seconds();

    onWorker('A');
    event(new JobProcessed($broker, brokerJob($broker, 1)));

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);
    $second = attemptRow($key, 2);

    // The real outcome replaces the inferred TIMEOUT, with the real duration.
    expect($first->status)->toBe(JobStatus::COMPLETED)
        ->and($first->duration_ms)->toBe(150_000)
        ->and($first->exception_class)->toBeNull();

    // The redelivered attempt is still running and untouched.
    expect($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->completed_at)->toBeNull()
        ->and($second->duration_ms)->toBeNull();
})->with('brokers');

test('the slow attempt finishing first and the redelivery second keeps both outcomes', function (string $broker) {
    slowAttemptRedelivered($broker);

    $this->travel(30)->seconds();
    onWorker('A');
    event(new JobProcessed($broker, brokerJob($broker, 1)));

    $this->travel(15)->seconds();
    onWorker('B');
    event(new JobProcessed($broker, brokerJob($broker, 2)));

    $key = brokerJobKey($broker);

    expect(attemptRow($key, 1)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 1)->duration_ms)->toBe(150_000)
        ->and(attemptRow($key, 2)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 2)->duration_ms)->toBe(45_000);
})->with('brokers');

test('the redelivery finishing first and the slow attempt second keeps both outcomes', function (string $broker) {
    slowAttemptRedelivered($broker);

    $this->travel(10)->seconds();
    onWorker('B');
    event(new JobProcessed($broker, brokerJob($broker, 2)));

    $this->travel(20)->seconds();
    onWorker('A');
    event(new JobProcessed($broker, brokerJob($broker, 1)));

    $key = brokerJobKey($broker);

    expect(attemptRow($key, 2)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 2)->duration_ms)->toBe(10_000)
        ->and(attemptRow($key, 1)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 1)->duration_ms)->toBe(150_000);
})->with('brokers');

test('a slow attempt failing after the redelivery started records the failure on its own row', function (string $broker) {
    slowAttemptRedelivered($broker);
    $this->travel(30)->seconds();

    onWorker('A');
    event(new JobFailed($broker, brokerJob($broker, 1), new RuntimeException('Slow attempt failed')));

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);
    $second = attemptRow($key, 2);

    expect($first->status)->toBe(JobStatus::FAILED)
        ->and($first->duration_ms)->toBe(150_000)
        ->and($first->exception_class)->toBe(RuntimeException::class)
        ->and($first->exception_message)->toBe('Slow attempt failed');

    expect($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->exception_class)->toBeNull()
        ->and($second->completed_at)->toBeNull();
})->with('brokers');

test('a slow attempt throwing with retries left replaces the inferred timeout with failed', function (string $broker) {
    slowAttemptRedelivered($broker);
    $this->travel(30)->seconds();

    // An intermediate failure raises JobExceptionOccurred only; JobFailed is
    // reserved for the final attempt.
    onWorker('A');
    event(new JobExceptionOccurred($broker, brokerJob($broker, 1), new RuntimeException('Transient failure')));

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);
    $second = attemptRow($key, 2);

    expect($first->status)->toBe(JobStatus::FAILED)
        ->and($first->exception_message)->toBe('Transient failure')
        ->and($first->duration_ms)->toBe(150_000)
        ->and($first->completed_at)->not->toBeNull();

    expect($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->exception_class)->toBeNull();
})->with('brokers');

test('an exception on the running attempt closes it as failed with its own duration', function (string $broker) {
    slowAttemptRedelivered($broker);

    $this->travel(3)->seconds();
    onWorker('B');
    event(new JobExceptionOccurred($broker, brokerJob($broker, 2), new RuntimeException('Attempt 2 threw')));

    $key = brokerJobKey($broker);
    $second = attemptRow($key, 2);

    // The attempt ended when it threw: Laravel releases it for a retry right
    // after this event, so its duration stops here rather than at the next
    // attempt's start, which would add the backoff and the redelivery wait.
    expect($second->status)->toBe(JobStatus::FAILED)
        ->and($second->exception_message)->toBe('Attempt 2 threw')
        ->and($second->duration_ms)->toBe(3_000)
        ->and($second->completed_at)->not->toBeNull()
        ->and(attemptRow($key, 1)->exception_class)->toBeNull()
        ->and(attemptRow($key, 1)->status)->toBe(JobStatus::TIMEOUT);
})->with('brokers');

test('the redelivery failing and the slow attempt completing each keep their own outcome', function (string $broker) {
    slowAttemptRedelivered($broker);

    $this->travel(5)->seconds();
    onWorker('B');
    event(new JobFailed($broker, brokerJob($broker, 2), new RuntimeException('Attempt 2 failed')));

    $this->travel(25)->seconds();
    onWorker('A');
    event(new JobProcessed($broker, brokerJob($broker, 1)));

    $key = brokerJobKey($broker);

    expect(attemptRow($key, 2)->status)->toBe(JobStatus::FAILED)
        ->and(attemptRow($key, 2)->exception_message)->toBe('Attempt 2 failed')
        ->and(attemptRow($key, 1)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 1)->exception_class)->toBeNull();
})->with('brokers');

test('a slow attempt timing out late records its real timeout on its own row', function (string $broker) {
    slowAttemptRedelivered($broker);
    $this->travel(30)->seconds();

    onWorker('A');
    event(new JobTimedOut($broker, brokerJob($broker, 1)));

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);

    expect($first->status)->toBe(JobStatus::TIMEOUT)
        ->and($first->duration_ms)->toBe(150_000)
        ->and($first->exception_class)->toBe('JobTimeout')
        ->and(attemptRow($key, 2)->status)->toBe(JobStatus::PROCESSING)
        ->and(attemptRow($key, 2)->exception_class)->toBeNull();
})->with('brokers');

test('a hard-killed attempt that never reports stays timeout while the redelivery completes', function (string $broker) {
    slowAttemptRedelivered($broker);

    // Worker A was SIGKILLed: it never raises another event.
    $this->travel(10)->seconds();
    onWorker('B');
    event(new JobProcessed($broker, brokerJob($broker, 2)));

    $key = brokerJobKey($broker);
    $first = attemptRow($key, 1);

    expect($first->status)->toBe(JobStatus::TIMEOUT)
        ->and($first->duration_ms)->toBeNull()
        ->and($first->exception_class)->toBeNull()
        ->and(attemptRow($key, 2)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 2)->duration_ms)->toBe(10_000);
})->with('brokers');

test('a superseded slow attempt that releases itself is not parked as queued', function (string $broker) {
    slowAttemptRedelivered($broker);

    onWorker('A');
    event(new JobProcessed($broker, BrokerJobs::released(brokerJob($broker, 1))));

    $key = brokerJobKey($broker);

    // Parking it would leave a row queued forever: the next delivery continues
    // on attempt 2's row, never on this one.
    expect(attemptRow($key, 1)->status)->toBe(JobStatus::TIMEOUT)
        ->and(attemptRow($key, 2)->status)->toBe(JobStatus::PROCESSING);
})->with('brokers');

test('a release then redelivery reuses one row and completes it as the redelivered attempt', function (string $broker) {
    onWorker('A');
    event(new JobProcessing($broker, brokerJob($broker, 1)));
    event(new JobProcessed($broker, BrokerJobs::released(brokerJob($broker, 1))));

    $key = brokerJobKey($broker);
    expect(attemptRow($key, 1)->status)->toBe(JobStatus::QUEUED);

    $this->travel(10)->seconds();
    onWorker('B');
    event(new JobProcessing($broker, brokerJob($broker, 2)));

    $this->travel(3)->seconds();
    event(new JobProcessed($broker, brokerJob($broker, 2)));

    $row = attemptRow($key, 2);

    expect(JobMonitor::count())->toBe(1)
        ->and($row->status)->toBe(JobStatus::COMPLETED)
        ->and($row->retried_from_id)->toBeNull()
        ->and($row->duration_ms)->toBe(3_000);
})->with('brokers');

test('per-job metrics land on the attempt each worker ran', function (string $broker) {
    slowAttemptRedelivered($broker);
    $key = brokerJobKey($broker);

    // queue-metrics raises its per-job event in the worker that ran the job,
    // with the job key but no attempt number. Attempt 2 is the latest row and
    // the one still `processing`, so a key-only lookup picks it for worker A.
    onWorker('A');
    event(new JobMetricsCompleted(
        jobId: $key,
        jobClass: ExampleJob::class,
        connection: $broker,
        queue: 'default',
        durationMs: 150_000.0,
        memoryMb: 512.0,
        cpuTimeMs: 140_000.0,
    ));

    expect(attemptRow($key, 1)->cpu_time_ms)->toEqual(140_000.0)
        ->and(attemptRow($key, 1)->memory_peak_mb)->toEqual(512.0)
        ->and(attemptRow($key, 2)->cpu_time_ms)->toBeNull();

    onWorker('B');
    event(new JobMetricsFailed(
        jobId: $key,
        jobClass: ExampleJob::class,
        connection: $broker,
        queue: 'default',
        durationMs: 20_000.0,
        memoryMb: 64.0,
        cpuTimeMs: 1_500.0,
        exceptionMessage: 'Attempt 2 failed',
    ));

    expect(attemptRow($key, 2)->cpu_time_ms)->toEqual(1_500.0)
        ->and(attemptRow($key, 2)->memory_peak_mb)->toEqual(64.0)
        ->and(attemptRow($key, 1)->cpu_time_ms)->toEqual(140_000.0);
})->with('brokers');

test('a stuck-job resolution is replaced by the real outcome when the job finishes', function (string $broker) {
    onWorker('A');
    event(new JobProcessing($broker, brokerJob($broker, 1)));

    $key = brokerJobKey($broker);
    $this->travel(45)->minutes();

    $result = app(ResolveStuckJobAction::class)->execute([attemptRow($key, 1)->uuid], 'timeout');
    expect($result['resolved'])->toBe(1)
        ->and(attemptRow($key, 1)->status)->toBe(JobStatus::TIMEOUT);

    $this->travel(1)->minutes();
    event(new JobProcessed($broker, brokerJob($broker, 1)));

    expect(attemptRow($key, 1)->status)->toBe(JobStatus::COMPLETED)
        ->and(attemptRow($key, 1)->duration_ms)->toBe(46 * 60_000);
})->with('brokers');

test('the stuck-job resolver leaves an attempt closed by redelivery alone', function (string $broker) {
    slowAttemptRedelivered($broker);

    $result = app(ResolveStuckJobAction::class)->execute([attemptRow(brokerJobKey($broker), 1)->uuid], 'timeout');

    // Only `processing` rows are stuck; a redelivery-closed attempt is not.
    expect($result['resolved'])->toBe(0)
        ->and($result['errors'][0])->toContain('is not stuck (status: timeout)');
})->with('brokers');
