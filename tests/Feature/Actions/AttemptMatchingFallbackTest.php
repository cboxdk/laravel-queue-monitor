<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Listeners\JobExceptionOccurredListener;
use Cbox\LaravelQueueMonitor\Listeners\QueueMetricsSubscriber;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Eloquent\EloquentJobMonitorRepository;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Collection;

/**
 * Per-attempt matching degrades to "the latest attempt" — the behaviour before
 * it existed — whenever the attempt's own row cannot be found.
 */
test('an outcome for an attempt with no row of its own lands on the latest attempt', function () {
    // ApproximateReceiveCount is approximate: SQS can report a receive count
    // that skips a number, so no row carries the attempt the job reports.
    $row = JobMonitor::factory()->processing()->create([
        'job_id' => 'sqs-over-reported',
        'attempt' => 2,
        'started_at' => now()->subSeconds(4),
    ]);

    event(new JobProcessed('sqs', BrokerJobs::sqs('sqs-over-reported', 3)));

    expect($row->refresh()->status)->toBe(JobStatus::COMPLETED)
        ->and(JobMonitor::count())->toBe(1);
});

test('a job first seen on a later delivery records the attempt the broker reports', function () {
    // Monitoring was enabled while the job was already on its third delivery.
    event(new JobProcessing('redis', BrokerJobs::redis('first-seen-late', 3)));

    $row = JobMonitor::query()->sole();

    expect($row->attempt)->toBe(3)
        ->and($row->status)->toBe(JobStatus::PROCESSING)
        ->and($row->job_class)->toBe(ExampleJob::class);

    event(new JobProcessed('redis', BrokerJobs::redis('first-seen-late', 3)));

    expect($row->refresh()->status)->toBe(JobStatus::COMPLETED);
});

test('a driver that cannot report attempts after deletion falls back to the attempt this worker started', function () {
    // Beanstalkd's attempts() asks the server for the job's stats, which fails
    // once CallQueuedHandler has deleted the finished job.
    $deleted = false;
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn('beanstalk-41');
    $job->shouldReceive('uuid')->andReturn('beanstalk-uuid');
    $job->shouldReceive('isReleased')->andReturn(false);
    $job->shouldReceive('attempts')->andReturnUsing(function () use (&$deleted): int {
        if ($deleted) {
            throw new RuntimeException('NOT_FOUND: job 41 does not exist');
        }

        return 1;
    });

    $first = JobMonitor::factory()->create([
        'job_id' => 'beanstalk-41',
        'status' => JobStatus::TIMEOUT,
        'attempt' => 1,
        'started_at' => now()->subMinutes(3),
        'completed_at' => now()->subMinute(),
        'duration_ms' => null,
        'exception_class' => null,
    ]);
    $second = JobMonitor::factory()->processing()->create([
        'job_id' => 'beanstalk-41',
        'attempt' => 2,
        'retried_from_id' => $first->id,
    ]);

    // This worker started attempt 1 (recorded as it began), then deleted it.
    app(InFlightAttempts::class)->remember('beanstalk-41', 1);
    $deleted = true;

    app(RecordJobCompletedAction::class)->execute(new JobProcessed('beanstalkd', $job));

    expect($first->refresh()->status)->toBe(JobStatus::COMPLETED)
        ->and($second->refresh()->status)->toBe(JobStatus::PROCESSING);
});

test('with neither attempts nor a recorded start the latest attempt is used', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn('no-attempt-info');
    $job->shouldReceive('isReleased')->andReturn(false);
    $job->shouldReceive('attempts')->andThrow(new RuntimeException('unavailable'));

    $older = JobMonitor::factory()->failed()->create(['job_id' => 'no-attempt-info', 'attempt' => 1]);
    $latest = JobMonitor::factory()->processing()->create(['job_id' => 'no-attempt-info', 'attempt' => 2]);

    app(RecordJobCompletedAction::class)->execute(new JobProcessed('beanstalkd', $job));

    expect($latest->refresh()->status)->toBe(JobStatus::COMPLETED)
        ->and($older->refresh()->status)->toBe(JobStatus::FAILED);
});

test('metrics with no recorded attempt fall back to the attempt still processing', function () {
    $done = JobMonitor::factory()->failed()->create(['job_id' => 'metrics-fallback', 'attempt' => 1, 'cpu_time_ms' => null]);
    $running = JobMonitor::factory()->processing()->create(['job_id' => 'metrics-fallback', 'attempt' => 2, 'cpu_time_ms' => null]);

    app(QueueMetricsSubscriber::class)->handleJobMetricsCompleted(new JobMetricsCompleted(
        jobId: 'metrics-fallback',
        jobClass: ExampleJob::class,
        connection: 'redis',
        queue: 'default',
        durationMs: 10.0,
        memoryMb: 32.0,
        cpuTimeMs: 7.0,
    ));

    expect($running->refresh()->cpu_time_ms)->toEqual(7.0)
        ->and($done->refresh()->cpu_time_ms)->toBeNull();
});

test('metrics for a recorded attempt whose row is gone fall back to the key-only lookup', function () {
    $running = JobMonitor::factory()->processing()->create(['job_id' => 'metrics-pruned', 'attempt' => 2, 'cpu_time_ms' => null]);

    // Attempt 1's row was pruned while the worker was still running it.
    app(InFlightAttempts::class)->remember('metrics-pruned', 1);

    app(QueueMetricsSubscriber::class)->handleJobMetricsCompleted(new JobMetricsCompleted(
        jobId: 'metrics-pruned',
        jobClass: ExampleJob::class,
        connection: 'redis',
        queue: 'default',
        durationMs: 10.0,
        memoryMb: 32.0,
        cpuTimeMs: 7.0,
    ));

    expect($running->refresh()->cpu_time_ms)->toEqual(7.0);
});

/**
 * A host repository written against JobMonitorRepositoryContract only, before
 * JobAttemptRepositoryContract existed. It must keep working unchanged.
 */
function legacyRepository(): JobMonitorRepositoryContract
{
    $inner = app(EloquentJobMonitorRepository::class);

    return new class($inner) implements JobMonitorRepositoryContract
    {
        public function __construct(private EloquentJobMonitorRepository $inner) {}

        public function create(JobMonitorData $data): JobMonitor
        {
            return $this->inner->create($data);
        }

        public function update(string $uuid, array $data): JobMonitor
        {
            return $this->inner->update($uuid, $data);
        }

        public function findByUuid(string $uuid): ?JobMonitor
        {
            return $this->inner->findByUuid($uuid);
        }

        public function findByJobId(int|string $jobId): ?JobMonitor
        {
            return $this->inner->findByJobId($jobId);
        }

        public function findLatestAttemptByJobId(int|string $jobId): ?JobMonitor
        {
            return $this->inner->findLatestAttemptByJobId($jobId);
        }

        public function query(JobFilterData $filters): Collection
        {
            return $this->inner->query($filters);
        }

        public function count(JobFilterData $filters): int
        {
            return $this->inner->count($filters);
        }

        public function getRetryChain(string $uuid): Collection
        {
            return $this->inner->getRetryChain($uuid);
        }

        public function prune(int $days, array $statuses = []): int
        {
            return $this->inner->prune($days, $statuses);
        }

        public function pruneByMaxRows(int $maxRows, array $statuses = []): int
        {
            return $this->inner->pruneByMaxRows($maxRows, $statuses);
        }

        public function delete(string $uuid): bool
        {
            return $this->inner->delete($uuid);
        }

        public function getByQueue(string $queue, ?string $connection = null, int $limit = 100): Collection
        {
            return $this->inner->getByQueue($queue, $connection, $limit);
        }

        public function getByServer(string $serverName, int $limit = 100): Collection
        {
            return $this->inner->getByServer($serverName, $limit);
        }

        public function getFailedJobs(int $limit = 100): Collection
        {
            return $this->inner->getFailedJobs($limit);
        }

        public function getRecentJobs(int $limit = 100): Collection
        {
            return $this->inner->getRecentJobs($limit);
        }
    };
}

test('a custom repository without the attempt contract keeps the latest-attempt behaviour', function () {
    app()->instance(JobMonitorRepositoryContract::class, legacyRepository());
    foreach ([RecordJobStartedAction::class, RecordJobCompletedAction::class, RecordJobFailedAction::class, RecordJobTimeoutAction::class, JobExceptionOccurredListener::class] as $class) {
        app()->forgetInstance($class);
    }

    event(new JobProcessing('redis', BrokerJobs::redis('legacy-repo', 1)));
    event(new JobProcessing('redis', BrokerJobs::redis('legacy-repo', 2)));

    // Attempt 1 reports late. Without per-attempt lookup the outcome goes to
    // the latest attempt — exactly what every release before this one did.
    event(new JobProcessed('redis', BrokerJobs::redis('legacy-repo', 1)));

    $rows = JobMonitor::where('job_id', 'legacy-repo')->orderBy('attempt')->get();

    expect($rows->pluck('status')->all())->toBe([JobStatus::TIMEOUT, JobStatus::COMPLETED]);
});
