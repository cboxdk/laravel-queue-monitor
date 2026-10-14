<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;

test('records the queue from the JobQueued event for a batched job', function () {
    // Jobs dispatched inside Bus::batch(...)->onQueue(...) keep a null instance
    // $queue; the real destination lives on the event.
    $job = new ExampleJob;
    expect($job->queue)->toBeNull();

    $event = new JobQueued('redis', 'reports', '12345', $job, '{}', null);
    app(RecordJobQueuedAction::class)->execute($event);

    expect(JobMonitor::first()?->queue)->toBe('reports');
});

test('prefers an explicit instance queue when the event carries none', function () {
    $job = new ExampleJob;
    $job->queue = 'emails';

    $event = new JobQueued('redis', '', '12345', $job, '{}', null);
    app(RecordJobQueuedAction::class)->execute($event);

    expect(JobMonitor::first()?->queue)->toBe('emails');
});

test('falls back to the connection default queue when nothing carries a queue', function () {
    config()->set('queue.connections.redis.queue', 'redis-default');

    $job = new ExampleJob;

    $event = new JobQueued('redis', '', '12345', $job, '{}', null);
    app(RecordJobQueuedAction::class)->execute($event);

    expect(JobMonitor::first()?->queue)->toBe('redis-default');
});

test('records the queue name when an SQS worker job reports its queue URL', function () {
    // JobProcessing for a job that was never recorded at dispatch goes through
    // RecordJobQueuedAction with the worker's SqsJob, whose getQueue() is the URL.
    config()->set('queue.connections.sqs.suffix', '-production');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn('sqs-message-id');
    $job->shouldReceive('getQueue')->andReturn('https://sqs.eu-west-1.amazonaws.com/123456789012/orders-production.fifo');
    $job->shouldReceive('payload')->andReturn([]);
    $job->shouldReceive('resolveName')->andReturn(ExampleJob::class);
    $job->shouldReceive('maxTries')->andReturn(3);

    app(RecordJobQueuedAction::class)->execute(new JobProcessing('sqs', $job));

    expect(JobMonitor::first()?->queue)->toBe('orders.fifo');
});

test('records the queue name when the SQS connection default queue is a URL', function () {
    config()->set('queue.connections.sqs.queue', 'https://sqs.eu-west-1.amazonaws.com/123456789012/emails');

    $event = new JobQueued('sqs', '', 'sqs-message-id', new ExampleJob, '{}', null);
    app(RecordJobQueuedAction::class)->execute($event);

    expect(JobMonitor::first()?->queue)->toBe('emails');
});
