<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * An attempt that throws while it has retries left is released by the worker
 * right after JobExceptionOccurred. Its row is closed as FAILED at that moment,
 * with the duration of the attempt itself: closing it when the next attempt
 * starts would add the backoff and the wait for redelivery, and a job that is
 * never delivered again would stay `processing` and be counted as stuck.
 *
 * The final attempt goes the other way round: the worker raises JobFailed
 * before JobExceptionOccurred, so the failed action records it and the
 * exception listener leaves the row alone.
 */
beforeEach(function () {
    $this->freezeSecond();
});

dataset('intermediate brokers', [
    'redis' => ['redis'],
    'sqs' => ['sqs'],
]);

function intermediateJob(string $broker, int $attempt): Job
{
    return match ($broker) {
        'redis' => BrokerJobs::redis('redis-intermediate', $attempt),
        'sqs' => BrokerJobs::sqs('sqs-intermediate', $attempt),
    };
}

function intermediateRow(int $attempt): JobMonitor
{
    return JobMonitor::where('attempt', $attempt)->orderByDesc('id')->firstOrFail();
}

test('an intermediate failure records the attempt duration, not the backoff until the next attempt', function (string $broker) {
    event(new JobProcessing($broker, intermediateJob($broker, 1)));
    $this->travel(1)->seconds();
    event(new JobExceptionOccurred($broker, intermediateJob($broker, 1), new RuntimeException('boom')));

    // Backoff, or no worker running: the next attempt starts a minute later.
    $this->travel(60)->seconds();
    event(new JobProcessing($broker, intermediateJob($broker, 2)));

    $first = intermediateRow(1);
    $second = intermediateRow(2);

    expect($first->status)->toBe(JobStatus::FAILED)
        ->and($first->duration_ms)->toBe(1_000)
        ->and($first->exception_message)->toBe('boom')
        ->and($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->retried_from_id)->toBe($first->id);
})->with('intermediate brokers');

test('an intermediate failure that is never redelivered is not left processing', function (string $broker) {
    event(new JobProcessing($broker, intermediateJob($broker, 1)));
    $this->travel(2)->seconds();
    event(new JobExceptionOccurred($broker, intermediateJob($broker, 1), new RuntimeException('boom')));

    $row = intermediateRow(1);

    expect($row->status)->toBe(JobStatus::FAILED)
        ->and($row->completed_at)->not->toBeNull()
        ->and($row->duration_ms)->toBe(2_000)
        ->and(JobMonitor::where('status', JobStatus::PROCESSING)->count())->toBe(0);
})->with('intermediate brokers');

test('the final failure lands on the final attempt in the worker\'s event order', function (string $broker) {
    event(new JobProcessing($broker, intermediateJob($broker, 1)));
    $this->travel(1)->seconds();
    event(new JobExceptionOccurred($broker, intermediateJob($broker, 1), new RuntimeException('first')));

    $this->travel(1)->seconds();
    event(new JobProcessing($broker, intermediateJob($broker, 2)));
    $this->travel(1)->seconds();
    // Worker::handleJobException fails the job before raising JobExceptionOccurred.
    event(new JobFailed($broker, intermediateJob($broker, 2), new RuntimeException('final')));
    event(new JobExceptionOccurred($broker, intermediateJob($broker, 2), new RuntimeException('final')));

    $rows = JobMonitor::orderBy('attempt')->get();

    expect($rows->pluck('status')->all())->toBe([JobStatus::FAILED, JobStatus::FAILED])
        ->and($rows[0]->exception_message)->toBe('first')
        ->and($rows[0]->duration_ms)->toBe(1_000)
        ->and($rows[1]->exception_message)->toBe('final')
        ->and($rows[1]->duration_ms)->toBe(1_000);
})->with('intermediate brokers');

test('a second exception on an already failed attempt does not overwrite the first', function () {
    event(new JobProcessing('sqs', intermediateJob('sqs', 1)));
    $this->travel(1)->seconds();
    event(new JobExceptionOccurred('sqs', intermediateJob('sqs', 1), new RuntimeException('first')));
    $this->travel(5)->seconds();
    event(new JobExceptionOccurred('sqs', intermediateJob('sqs', 1), new RuntimeException('second')));

    $row = intermediateRow(1);

    expect($row->exception_message)->toBe('first')
        ->and($row->duration_ms)->toBe(1_000);
});

test('a row an earlier version left processing with an exception is closed as failed with no duration', function () {
    event(new JobProcessing('sqs', intermediateJob('sqs', 1)));
    // What versions before 1.12.1 recorded on JobExceptionOccurred.
    intermediateRow(1)->update([
        'exception_class' => RuntimeException::class,
        'exception_message' => 'legacy',
    ]);

    $this->travel(60)->seconds();
    event(new JobProcessing('sqs', intermediateJob('sqs', 2)));

    $first = intermediateRow(1);

    expect($first->status)->toBe(JobStatus::FAILED)
        ->and($first->completed_at)->not->toBeNull()
        ->and($first->duration_ms)->toBeNull();
});

test('a slow sqs attempt whose DeleteMessage fails after handle() is recorded as failed', function () {
    event(new JobProcessing('sqs', intermediateJob('sqs', 1)));
    $this->travel(7)->seconds();
    // The visibility timeout lapsed, so SQS delivered the message again.
    event(new JobProcessing('sqs', intermediateJob('sqs', 2)));
    $this->travel(1)->seconds();
    // handle() finished, then deleting with the stale receipt handle threw.
    event(new JobExceptionOccurred('sqs', intermediateJob('sqs', 1), new RuntimeException('ReceiptHandleIsInvalid')));

    $first = intermediateRow(1);
    $second = intermediateRow(2);

    expect($first->status)->toBe(JobStatus::FAILED)
        ->and($first->exception_message)->toBe('ReceiptHandleIsInvalid')
        ->and($first->duration_ms)->toBe(8_000)
        ->and($second->status)->toBe(JobStatus::PROCESSING)
        ->and($second->exception_class)->toBeNull();
});
