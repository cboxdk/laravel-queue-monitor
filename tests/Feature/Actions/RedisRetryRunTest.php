<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * `queue:retry` re-pushes a failed Redis job with its original payload, so the
 * same Redis job id comes back with its attempts reset: the retry run starts
 * again at attempt 1 under a key that already has a finished retry chain.
 * The retry run must get rows of its own and leave the failure history alone.
 */
beforeEach(function () {
    $this->freezeSecond();
});

const RETRY_KEY = 'redis-retried-job';

function failRedisJobThreeTimes(): void
{
    foreach ([1, 2] as $attempt) {
        event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, $attempt)));
        test()->travel(1)->seconds();
        event(new JobExceptionOccurred('redis', BrokerJobs::redis(RETRY_KEY, $attempt), new RuntimeException("attempt {$attempt}")));
        test()->travel(10)->seconds();
    }

    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 3)));
    test()->travel(1)->seconds();
    event(new JobFailed('redis', BrokerJobs::redis(RETRY_KEY, 3), new RuntimeException('final')));
    event(new JobExceptionOccurred('redis', BrokerJobs::redis(RETRY_KEY, 3), new RuntimeException('final')));
}

test('a retried redis job keeps the final failure and starts a row of its own', function () {
    failRedisJobThreeTimes();
    $finalFailure = JobMonitor::where('attempt', 3)->firstOrFail();

    $this->travel(1)->hours();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));

    $finalFailure->refresh();
    $retryRow = JobMonitor::orderByDesc('id')->firstOrFail();

    expect(JobMonitor::count())->toBe(4)
        ->and($finalFailure->status)->toBe(JobStatus::FAILED)
        ->and($finalFailure->exception_message)->toBe('final')
        ->and($finalFailure->attempt)->toBe(3)
        ->and($retryRow->status)->toBe(JobStatus::PROCESSING)
        ->and($retryRow->attempt)->toBe(1)
        ->and($retryRow->job_id)->toBe(RETRY_KEY)
        ->and($retryRow->retried_from_id)->toBe($finalFailure->id)
        ->and($retryRow->exception_class)->toBeNull();
});

test('the retry run records its outcome on its own row', function () {
    failRedisJobThreeTimes();

    $this->travel(1)->hours();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));
    $this->travel(2)->seconds();
    event(new JobProcessed('redis', BrokerJobs::redis(RETRY_KEY, 1)));

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows->pluck('status')->all())->toBe([
        JobStatus::FAILED, JobStatus::FAILED, JobStatus::FAILED, JobStatus::COMPLETED,
    ])
        ->and($rows[0]->exception_message)->toBe('attempt 1')
        ->and($rows[0]->duration_ms)->toBe(1_000)
        ->and($rows[3]->duration_ms)->toBe(2_000)
        ->and($rows[3]->exception_class)->toBeNull();
});

test('a retry run that fails again continues its own chain', function () {
    failRedisJobThreeTimes();

    $this->travel(1)->hours();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));
    $this->travel(1)->seconds();
    event(new JobExceptionOccurred('redis', BrokerJobs::redis(RETRY_KEY, 1), new RuntimeException('retry 1')));
    $this->travel(5)->seconds();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 2)));

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows)->toHaveCount(5)
        ->and($rows[3]->attempt)->toBe(1)
        ->and($rows[3]->status)->toBe(JobStatus::FAILED)
        ->and($rows[3]->exception_message)->toBe('retry 1')
        ->and($rows[4]->attempt)->toBe(2)
        ->and($rows[4]->status)->toBe(JobStatus::PROCESSING)
        ->and($rows[4]->retried_from_id)->toBe($rows[3]->id)
        // The old run's attempt 2 is untouched.
        ->and($rows[1]->exception_message)->toBe('attempt 2');
});

test('a released retry run reuses its own row, not the previous run\'s highest attempt', function () {
    failRedisJobThreeTimes();

    $this->travel(1)->hours();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));
    event(new JobProcessed('redis', BrokerJobs::released(BrokerJobs::redis(RETRY_KEY, 1))));

    $retryRow = JobMonitor::orderByDesc('id')->firstOrFail();
    expect($retryRow->status)->toBe(JobStatus::QUEUED);

    $this->travel(30)->seconds();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 2)));

    $retryRow->refresh();

    expect(JobMonitor::count())->toBe(4)
        ->and($retryRow->status)->toBe(JobStatus::PROCESSING)
        ->and($retryRow->attempt)->toBe(2);
});

test('the retry chain lists both runs in the order they ran', function () {
    failRedisJobThreeTimes();

    $this->travel(1)->hours();
    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));

    $chain = QueueMonitor::getRetryChain(JobMonitor::orderBy('id')->firstOrFail()->uuid);

    expect($chain->pluck('attempt')->all())->toBe([1, 2, 3, 1]);
});

test('a first attempt whose row is still queued is updated in place', function () {
    JobMonitor::factory()->create([
        'job_id' => RETRY_KEY,
        'status' => JobStatus::QUEUED,
        'attempt' => 1,
    ]);

    event(new JobProcessing('redis', BrokerJobs::redis(RETRY_KEY, 1)));

    expect(JobMonitor::count())->toBe(1)
        ->and(JobMonitor::firstOrFail()->status)->toBe(JobStatus::PROCESSING);
});
