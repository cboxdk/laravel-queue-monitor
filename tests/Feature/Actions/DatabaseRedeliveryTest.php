<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * The database driver's retry_after, exercised with the real DatabaseQueue:
 * a reserved job whose reservation is older than retry_after is popped again
 * by the next worker, with the same id and the attempts column incremented —
 * while the first worker may still be running it.
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

function popDatabaseJob(): DatabaseJob
{
    $job = Queue::connection('database')->pop();

    expect($job)->toBeInstanceOf(DatabaseJob::class);

    return $job;
}

function asDatabaseWorker(string $name): void
{
    $key = "tests.database-worker.{$name}";

    if (! app()->bound($key)) {
        app()->instance($key, new InFlightAttempts);
    }

    app()->instance(InFlightAttempts::class, app($key));
}

test('the database driver reports the redelivered attempt under the same job id', function () {
    dispatch(new ExampleJob);

    $first = popDatabaseJob();
    $this->travel(91)->seconds();
    $second = popDatabaseJob();

    expect($first->attempts())->toBe(1)
        ->and($second->attempts())->toBe(2)
        ->and((string) $second->getJobId())->toBe((string) $first->getJobId());
});

test('a slow database job finishing after retry_after redelivered it records on its own row', function () {
    dispatch(new ExampleJob);
    $jobKey = (string) JobMonitor::query()->value('job_id');

    asDatabaseWorker('A');
    $first = popDatabaseJob();
    event(new JobProcessing('database', $first));

    $this->travel(120)->seconds();

    asDatabaseWorker('B');
    $second = popDatabaseJob();
    event(new JobProcessing('database', $second));

    $this->travel(30)->seconds();
    asDatabaseWorker('A');
    event(new JobMetricsCompleted(
        jobId: $jobKey,
        jobClass: ExampleJob::class,
        connection: 'database',
        queue: 'default',
        durationMs: 150_000.0,
        memoryMb: 300.0,
        cpuTimeMs: 90_000.0,
    ));
    event(new JobProcessed('database', $first));

    $this->travel(10)->seconds();
    asDatabaseWorker('B');
    event(new JobFailed('database', $second, new RuntimeException('Attempt 2 failed')));

    $rows = JobMonitor::where('job_id', $jobKey)->orderBy('attempt')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->attempt)->toBe(1)
        ->and($rows[0]->status)->toBe(JobStatus::COMPLETED)
        ->and($rows[0]->duration_ms)->toBe(150_000)
        ->and($rows[0]->cpu_time_ms)->toEqual(90_000.0)
        ->and($rows[0]->exception_class)->toBeNull()
        ->and($rows[1]->attempt)->toBe(2)
        ->and($rows[1]->status)->toBe(JobStatus::FAILED)
        ->and($rows[1]->duration_ms)->toBe(40_000)
        ->and($rows[1]->exception_message)->toBe('Attempt 2 failed')
        ->and($rows[1]->cpu_time_ms)->toBeNull()
        ->and($rows[1]->retried_from_id)->toBe($rows[0]->id);
});

test('a hard-killed database job stays timeout once its redelivery completes', function () {
    dispatch(new ExampleJob);
    $jobKey = (string) JobMonitor::query()->value('job_id');

    event(new JobProcessing('database', popDatabaseJob()));
    $this->travel(91)->seconds();

    $second = popDatabaseJob();
    event(new JobProcessing('database', $second));
    $this->travel(2)->seconds();
    event(new JobProcessed('database', $second));

    $rows = JobMonitor::where('job_id', $jobKey)->orderBy('attempt')->get();

    expect($rows->pluck('status')->all())->toBe([JobStatus::TIMEOUT, JobStatus::COMPLETED])
        ->and($rows[0]->duration_ms)->toBeNull()
        ->and($rows[1]->duration_ms)->toBe(2_000);
});
