<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Cbox\LaravelQueueMonitor\Tests\Support\FailingJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * The database driver releases a job by deleting its row and inserting a new
 * one (DatabaseQueue::deleteAndRelease), so the next delivery has a new job
 * id. Queue Monitor keys rows by the job id, and the delivery must still find
 * the released attempt's row: reuse it after a plain release, or link a new
 * row to it after a release on an exception. Otherwise the released row is
 * left `queued` (or `failed` and unlinked) and the delivery starts a history
 * of its own.
 *
 * The new jobs row exists as soon as release() returns, so another worker can
 * start the next delivery before the releasing worker records the release.
 * Both orders are covered, and only the released attempt's own row may move:
 * a slow attempt redelivered under the same id keeps its row, and its late
 * outcome still lands there.
 *
 * Exercised with the real DatabaseQueue and, for exceptions, the real Worker,
 * so the event order and the release are the framework's own.
 */
beforeEach(function () {
    $this->freezeSecond();

    Schema::create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', [
        'driver' => 'database',
        'connection' => 'testing',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ]);
});

function popReleasedDatabaseJob(): DatabaseJob
{
    $job = Queue::connection('database')->pop();

    expect($job)->toBeInstanceOf(DatabaseJob::class);

    return $job;
}

/**
 * Run one attempt through the real worker, which raises the queue events,
 * and on an exception fails or releases the job the way it does in production.
 */
function workDatabaseJob(DatabaseJob $job): void
{
    try {
        app(Worker::class, ['isDownForMaintenance' => fn (): bool => false])
            ->process('database', $job, new WorkerOptions(backoff: 0, maxTries: 0));
    } catch (Throwable) {
        // The worker rethrows the job's exception after handling it.
    }
}

test('a released database job reuses its row on the redelivery', function () {
    dispatch(new ExampleJob);

    $first = popReleasedDatabaseJob();
    event(new JobProcessing('database', $first));
    $this->travel(1)->seconds();
    $first->release(5);
    event(new JobProcessed('database', $first));

    $this->travel(10)->seconds();
    $second = popReleasedDatabaseJob();
    event(new JobProcessing('database', $second));
    $this->travel(2)->seconds();
    event(new JobProcessed('database', $second));

    $rows = JobMonitor::all();

    expect((string) $second->getJobId())->not->toBe((string) $first->getJobId())
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]->status)->toBe(JobStatus::COMPLETED)
        ->and($rows[0]->attempt)->toBe(2)
        ->and($rows[0]->job_id)->toBe((string) $second->getJobId())
        ->and($rows[0]->duration_ms)->toBe(2_000);
});

test('a database job released on an exception links its redelivery to the failed attempt', function () {
    dispatch(new FailingJob);

    workDatabaseJob(popReleasedDatabaseJob());
    workDatabaseJob(popReleasedDatabaseJob());

    $rows = JobMonitor::orderBy('id')->get();
    $nextId = (string) DB::table('jobs')->value('id');

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('status')->all())->toBe([JobStatus::FAILED, JobStatus::FAILED])
        ->and($rows->pluck('attempt')->all())->toBe([1, 2])
        ->and($rows[1]->retried_from_id)->toBe($rows[0]->id)
        // Each failed attempt keeps the id it ran under; the job is waiting
        // under a third one.
        ->and($rows->pluck('job_id')->unique())->toHaveCount(2)
        ->and($rows->pluck('job_id')->all())->not->toContain($nextId)
        ->and(JobMonitor::where('status', JobStatus::QUEUED)->count())->toBe(0);
});

test('the final database failure closes the chain under the last delivery\'s id', function () {
    dispatch(new FailingJob);

    workDatabaseJob(popReleasedDatabaseJob());
    workDatabaseJob(popReleasedDatabaseJob());
    $last = popReleasedDatabaseJob();
    workDatabaseJob($last);

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('status')->all())->toBe([JobStatus::FAILED, JobStatus::FAILED, JobStatus::FAILED])
        ->and($rows->pluck('attempt')->all())->toBe([1, 2, 3])
        ->and($rows[2]->job_id)->toBe((string) $last->getJobId())
        ->and($rows[1]->retried_from_id)->toBe($rows[0]->id)
        ->and($rows[2]->retried_from_id)->toBe($rows[1]->id)
        ->and($rows[2]->exception_message)->toBe('This job always fails')
        ->and(QueueMonitor::getRetryChain($rows[2]->uuid)->pluck('attempt')->all())->toBe([1, 2, 3]);
});

test('a database job released twice keeps one row until it completes', function () {
    dispatch(new ExampleJob);

    foreach ([1, 2] as $attempt) {
        $job = popReleasedDatabaseJob();
        event(new JobProcessing('database', $job));
        $job->release();
        event(new JobProcessed('database', $job));
    }

    $last = popReleasedDatabaseJob();
    event(new JobProcessing('database', $last));
    event(new JobProcessed('database', $last));

    $rows = JobMonitor::all();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->attempt)->toBe(3)
        ->and($rows[0]->status)->toBe(JobStatus::COMPLETED)
        ->and($rows[0]->job_id)->toBe((string) $last->getJobId());
});

test('a superseded database attempt released on an exception leaves the newer attempt\'s rows alone', function () {
    dispatch(new ExampleJob);

    $slow = popReleasedDatabaseJob();
    event(new JobProcessing('database', $slow));

    // retry_after lapses: the same row, same id, is reserved again.
    $this->travel(91)->seconds();
    $redelivery = popReleasedDatabaseJob();
    event(new JobProcessing('database', $redelivery));

    // The slow attempt throws and the worker releases it.
    $this->travel(5)->seconds();
    event(new JobExceptionOccurred('database', $slow, new RuntimeException('slow attempt threw')));
    $slow->release();
    event(new JobReleasedAfterException('database', $slow, 0, new RuntimeException('slow attempt threw')));

    $this->travel(5)->seconds();
    event(new JobProcessed('database', $redelivery));

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('status')->all())->toBe([JobStatus::FAILED, JobStatus::COMPLETED])
        ->and($rows->pluck('job_id')->unique()->all())->toBe([(string) $redelivery->getJobId()])
        ->and($rows[0]->exception_message)->toBe('slow attempt threw');
});

test('a released database job whose release left no rows starts a row of its own', function () {
    dispatch(new ExampleJob);

    $first = popReleasedDatabaseJob();
    $first->release();
    JobMonitor::query()->delete();

    $second = popReleasedDatabaseJob();
    event(new JobProcessing('database', $second));

    $row = JobMonitor::sole();

    expect($row->job_id)->toBe((string) $second->getJobId())
        ->and($row->attempt)->toBe(2)
        ->and($row->status)->toBe(JobStatus::PROCESSING);
});

test('a delivery that starts before the release is recorded takes the parked row\'s place', function () {
    dispatch(new ExampleJob);

    $first = popReleasedDatabaseJob();
    event(new JobProcessing('database', $first));
    $first->release();

    // Another worker picks up the new jobs row before the first worker
    // raises JobProcessed for the release.
    $second = popReleasedDatabaseJob();
    event(new JobProcessing('database', $second));
    event(new JobProcessed('database', $first));

    $this->travel(2)->seconds();
    event(new JobProcessed('database', $second));

    $row = JobMonitor::sole();

    expect($row->status)->toBe(JobStatus::COMPLETED)
        ->and($row->attempt)->toBe(2)
        ->and($row->job_id)->toBe((string) $second->getJobId())
        ->and($row->duration_ms)->toBe(2_000);
});

test('a delivery that starts before an exception release is recorded links to the failed attempt', function () {
    dispatch(new ExampleJob);

    $first = popReleasedDatabaseJob();
    event(new JobProcessing('database', $first));
    event(new JobExceptionOccurred('database', $first, new RuntimeException('attempt 1 threw')));
    $first->release();

    $second = popReleasedDatabaseJob();
    event(new JobProcessing('database', $second));
    event(new JobReleasedAfterException('database', $first, 0, new RuntimeException('attempt 1 threw')));

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe(JobStatus::FAILED)
        ->and($rows[0]->job_id)->toBe((string) $first->getJobId())
        ->and($rows[1]->status)->toBe(JobStatus::PROCESSING)
        ->and($rows[1]->attempt)->toBe(2)
        ->and($rows[1]->retried_from_id)->toBe($rows[0]->id);
});

test('a slow attempt keeps its row when the redelivery that superseded it is released', function () {
    dispatch(new ExampleJob);
    $slowId = null;

    $slow = popReleasedDatabaseJob();
    event(new JobProcessing('database', $slow));
    $slowId = (string) $slow->getJobId();

    // retry_after lapses: the same jobs row, same id, is reserved again.
    $this->travel(91)->seconds();
    $redelivery = popReleasedDatabaseJob();
    event(new JobProcessing('database', $redelivery));

    // The redelivery releases itself (rate limited, say).
    $redelivery->release();
    event(new JobProcessed('database', $redelivery));

    // The slow attempt finishes late, under the id it ran with.
    $this->travel(30)->seconds();
    event(new JobProcessed('database', $slow));

    $next = popReleasedDatabaseJob();
    event(new JobProcessing('database', $next));

    $slowRow = JobMonitor::where('attempt', 1)->sole();
    $releasedRow = JobMonitor::where('id', '!=', $slowRow->id)->sole();

    expect(JobMonitor::count())->toBe(2)
        ->and($slowRow->status)->toBe(JobStatus::COMPLETED)
        ->and($slowRow->job_id)->toBe($slowId)
        ->and($slowRow->duration_ms)->toBe(121_000)
        ->and($releasedRow->status)->toBe(JobStatus::PROCESSING)
        ->and($releasedRow->attempt)->toBe(3)
        ->and($releasedRow->job_id)->toBe((string) $next->getJobId())
        ->and($releasedRow->retried_from_id)->toBe($slowRow->id);
});

test('metrics reported under the released attempt\'s id after its row moved still land on it', function () {
    dispatch(new ExampleJob);

    $first = popReleasedDatabaseJob();
    $releasedId = (string) $first->getJobId();
    event(new JobProcessing('database', $first));
    $first->release();
    event(new JobProcessed('database', $first));

    $second = popReleasedDatabaseJob();
    event(new JobProcessing('database', $second));

    // laravel-queue-metrics reports the released attempt's figures under the
    // id it ran with, after the next delivery has taken the row over.
    app()->instance(InFlightAttempts::class, tap(new InFlightAttempts)->remember($releasedId, 1));
    event(new JobMetricsCompleted(
        jobId: $releasedId,
        jobClass: ExampleJob::class,
        connection: 'database',
        queue: 'default',
        durationMs: 50.0,
        memoryMb: 64.0,
        cpuTimeMs: 40.0,
    ));

    $row = JobMonitor::sole();

    expect($row->job_id)->toBe((string) $second->getJobId())
        ->and($row->cpu_time_ms)->toEqual(40.0)
        ->and($row->memory_peak_mb)->toEqual(64.0);
});
