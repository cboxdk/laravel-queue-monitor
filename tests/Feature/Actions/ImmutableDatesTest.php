<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Date;

// Hosts commonly call Date::use(CarbonImmutable::class), which makes now() and
// every Eloquent date cast return CarbonImmutable. Recording must not assume
// mutable Carbon anywhere along the job lifecycle.
beforeEach(fn () => Date::use(CarbonImmutable::class));
afterEach(fn () => Date::useDefault());

function immutableDatesMockJob(string $jobId, int $attempts = 1): Job
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn($jobId);
    $job->shouldReceive('attempts')->andReturn($attempts);
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('isReleased')->andReturn(false);
    $job->shouldReceive('resolveName')->andReturn(ExampleJob::class);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('maxTries')->andReturn(3);

    return $job;
}

function queueImmutableDatesJob(string $jobId, mixed $delay = null): JobMonitor
{
    $monitor = app(RecordJobQueuedAction::class)
        ->execute(new JobQueued('redis', 'default', $jobId, new ExampleJob, '{}', $delay));

    expect($monitor)->toBeInstanceOf(JobMonitor::class);

    return $monitor->refresh();
}

test('records a queued job', function () {
    $monitor = queueImmutableDatesJob('queued-1');

    expect(now())->toBeInstanceOf(CarbonImmutable::class)
        ->and($monitor->status)->toBe(JobStatus::QUEUED)
        ->and($monitor->queued_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('records a delayed job', function (mixed $delay) {
    $monitor = queueImmutableDatesJob('delayed-1', $delay);

    expect($monitor->available_at?->isFuture())->toBeTrue();
})->with([
    'seconds' => 60,
    'interval' => fn () => new DateInterval('PT1M'),
    'immutable datetime' => fn () => CarbonImmutable::now()->addMinute(),
]);

test('records the full queued → processing → processed lifecycle', function () {
    queueImmutableDatesJob('lifecycle-1');
    $job = immutableDatesMockJob('lifecycle-1');

    app(RecordJobStartedAction::class)->execute(new JobProcessing('redis', $job));
    $this->travel(2)->seconds();
    app(RecordJobCompletedAction::class)->execute(new JobProcessed('redis', $job));

    $monitor = JobMonitor::where('job_id', 'lifecycle-1')->sole();

    expect($monitor->status)->toBe(JobStatus::COMPLETED)
        ->and($monitor->completed_at)->not->toBeNull()
        ->and($monitor->duration_ms)->toBeGreaterThan(0);
});

test('records a processing job that was never seen as queued', function () {
    app(RecordJobStartedAction::class)
        ->execute(new JobProcessing('redis', immutableDatesMockJob('unseen-1')));

    expect(JobMonitor::where('job_id', 'unseen-1')->sole()->status)->toBe(JobStatus::PROCESSING);
});

test('records a failed job and its retry attempt', function () {
    queueImmutableDatesJob('retry-1');

    app(RecordJobStartedAction::class)
        ->execute(new JobProcessing('redis', immutableDatesMockJob('retry-1')));
    $this->travel(2)->seconds();
    app(RecordJobFailedAction::class)
        ->execute(new JobFailed('redis', immutableDatesMockJob('retry-1'), new RuntimeException('boom')));

    // A second attempt after a failure copies the first row's cast dates into a new row.
    app(RecordJobStartedAction::class)
        ->execute(new JobProcessing('redis', immutableDatesMockJob('retry-1', attempts: 2)));

    $rows = JobMonitor::where('job_id', 'retry-1')->orderBy('attempt')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe(JobStatus::FAILED)
        ->and($rows[0]->duration_ms)->toBeGreaterThan(0)
        ->and($rows[1]->status)->toBe(JobStatus::PROCESSING)
        ->and($rows[1]->queued_at->equalTo($rows[0]->queued_at))->toBeTrue();
});

test('records a timed out job', function () {
    queueImmutableDatesJob('timeout-1');
    $job = immutableDatesMockJob('timeout-1');

    app(RecordJobStartedAction::class)->execute(new JobProcessing('redis', $job));
    $this->travel(2)->seconds();

    $event = new stdClass;
    $event->job = $job;
    app(RecordJobTimeoutAction::class)->execute($event);

    $monitor = JobMonitor::where('job_id', 'timeout-1')->sole();

    expect($monitor->status)->toBe(JobStatus::TIMEOUT)
        ->and($monitor->duration_ms)->toBeGreaterThan(0);
});
