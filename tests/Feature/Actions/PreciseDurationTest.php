<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Jobs\RedisJob;

/**
 * `started_at` is stored to the whole second, so a duration measured from it
 * is up to a second too long: a 100 ms job that starts at .950 and ends at
 * 1.050 would read 1 050 ms. The worker that ran the attempt measures from the
 * start it recorded in memory instead. A process that did not see the start
 * falls back to the column.
 *
 * Every test starts the job 950 ms into a second and ends it 100 ms later,
 * across the second boundary.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00.950'));
});

function preciseJob(int $attempt = 1): RedisJob
{
    return BrokerJobs::redis('precise-job', $attempt);
}

function startPreciseJob(int $attempt = 1): void
{
    event(new JobProcessing('redis', preciseJob($attempt)));
    test()->travel(100)->milliseconds();
}

function preciseRow(int $attempt = 1): JobMonitor
{
    return JobMonitor::where('attempt', $attempt)->orderByDesc('id')->firstOrFail();
}

test('a completed job records its duration to the millisecond', function () {
    startPreciseJob();
    event(new JobProcessed('redis', preciseJob()));

    expect(preciseRow()->status)->toBe(JobStatus::COMPLETED)
        ->and(preciseRow()->duration_ms)->toBe(100);
});

test('a failed job records its duration to the millisecond', function () {
    startPreciseJob();
    event(new JobFailed('redis', preciseJob(), new RuntimeException('final')));

    expect(preciseRow()->duration_ms)->toBe(100);
});

test('an intermediate failure records its duration to the millisecond', function () {
    startPreciseJob();
    event(new JobExceptionOccurred('redis', preciseJob(), new RuntimeException('retry me')));

    expect(preciseRow()->status)->toBe(JobStatus::FAILED)
        ->and(preciseRow()->duration_ms)->toBe(100);
});

test('a timed out job records its duration to the millisecond', function () {
    startPreciseJob();
    event(new JobTimedOut('redis', preciseJob()));

    expect(preciseRow()->status)->toBe(JobStatus::TIMEOUT)
        ->and(preciseRow()->duration_ms)->toBe(100);
});

test('a slow attempt superseded by a redelivery still measures from its own start', function () {
    $workerA = new InFlightAttempts;
    $workerB = new InFlightAttempts;

    app()->instance(InFlightAttempts::class, $workerA);
    event(new JobProcessing('redis', preciseJob(1)));

    $this->travel(90)->seconds();
    app()->instance(InFlightAttempts::class, $workerB);
    event(new JobProcessing('redis', preciseJob(2)));

    $this->travel(30)->seconds();
    app()->instance(InFlightAttempts::class, $workerA);
    event(new JobProcessed('redis', preciseJob(1)));

    expect(preciseRow(1)->status)->toBe(JobStatus::COMPLETED)
        ->and(preciseRow(1)->duration_ms)->toBe(120_000);
});

test('an outcome from a process that did not see the start falls back to started_at', function () {
    startPreciseJob();

    // The worker restarted, or another process reports the outcome.
    app()->instance(InFlightAttempts::class, new InFlightAttempts);
    event(new JobProcessed('redis', preciseJob()));

    // started_at holds 12:00:00, so the measured span is 1.05 s.
    expect(preciseRow()->duration_ms)->toBe(1_050);
});

test('a start recorded for another attempt is not used', function () {
    startPreciseJob(1);
    app(InFlightAttempts::class)->remember('precise-job', 2, now()->subSeconds(30));

    event(new JobProcessed('redis', preciseJob(1)));

    expect(preciseRow()->duration_ms)->toBe(1_050);
});

test('the start time is forgotten once the outcome is recorded, but the attempt is kept', function () {
    startPreciseJob();
    event(new JobProcessed('redis', preciseJob()));

    $inFlight = app(InFlightAttempts::class);

    expect($inFlight->startedAtFor('precise-job', 1))->toBeNull()
        ->and($inFlight->attemptFor('precise-job'))->toBe(1);
});

test('a released attempt forgets its start time', function () {
    startPreciseJob();
    event(new JobProcessed('redis', BrokerJobs::released(preciseJob())));

    expect(app(InFlightAttempts::class)->startedAtFor('precise-job', 1))->toBeNull();
});
