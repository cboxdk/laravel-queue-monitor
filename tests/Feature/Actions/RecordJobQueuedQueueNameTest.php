<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
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
