<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
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
 * one (DatabaseQueue::deleteAndRelease), so the redelivery has a new job id.
 * Queue Monitor keys rows by the job id, and the redelivery must still find
 * the released attempt's row: reuse it after a plain release, or link a new
 * row to it after a release on an exception. Otherwise the released row is
 * left `queued` (or `failed` and unlinked) and the redelivery starts a
 * history of its own.
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
    $payload = json_decode((string) DB::table('jobs')->value('payload'), true, flags: JSON_THROW_ON_ERROR);

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('status')->all())->toBe([JobStatus::FAILED, JobStatus::FAILED])
        ->and($rows->pluck('attempt')->all())->toBe([1, 2])
        ->and($rows[1]->retried_from_id)->toBe($rows[0]->id)
        // Until the next delivery starts, both rows wait under the payload uuid.
        ->and($rows->pluck('job_id')->unique()->all())->toBe(['released:'.$payload['uuid']])
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
        ->and($rows->pluck('job_id')->unique()->all())->toBe([(string) $last->getJobId()])
        ->and($rows[2]->exception_message)->toBe('This job always fails')
        ->and(QueueMonitor::getRetryChain($rows[0]->uuid)->pluck('attempt')->all())->toBe([1, 2, 3]);
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
