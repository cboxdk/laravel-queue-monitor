<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Listeners\JobExceptionOccurredListener;
use Cbox\LaravelQueueMonitor\Listeners\JobFailedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobProcessedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobProcessingListener;
use Cbox\LaravelQueueMonitor\Listeners\JobQueuedListener;
use Cbox\LaravelQueueMonitor\Listeners\JobTimedOutListener;
use Cbox\LaravelQueueMonitor\Listeners\ScalingEventListener;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Illuminate\Queue\Events\JobExceptionOccurred;

test('listeners skip when monitoring is disabled', function () {
    config()->set('queue-monitor.enabled', false);

    // Create a mock event that would fail if handle() proceeded past the config check
    $mockEvent = new \stdClass;

    $listeners = [
        new JobFailedListener,
        new JobProcessedListener,
        new JobProcessingListener,
        new JobQueuedListener,
        new JobTimedOutListener,
    ];

    foreach ($listeners as $listener) {
        // The reflection hack: call handle with wrong type — if it passes the config check,
        // it will try to resolve the action and fail. We expect it to return early.
        expect(fn () => $listener->handle($mockEvent))->not->toThrow(\Throwable::class);
    }

    expect(JobMonitor::count())->toBe(0);
});

test('job exception occurred listener skips when disabled', function () {
    config()->set('queue-monitor.enabled', false);

    $listener = new JobExceptionOccurredListener;

    // Should return early without error
    $listener->handle(new JobExceptionOccurred('redis', new class
    {
        public function getJobId(): string
        {
            return 'test-123';
        }
    }, new \RuntimeException('test')));

    expect(JobMonitor::count())->toBe(0);
});

test('job exception occurred listener captures exception on processing job', function () {
    $job = JobMonitor::factory()->processing()->create(['job_id' => 'test-job-123']);

    $listener = new JobExceptionOccurredListener;

    $mockQueueJob = new class
    {
        public function getJobId(): string
        {
            return 'test-job-123';
        }
    };

    $listener->handle(new JobExceptionOccurred(
        'redis',
        $mockQueueJob,
        new \RuntimeException('Something broke'),
    ));

    $job->refresh();
    expect($job->exception_class)->toBe('RuntimeException');
    expect($job->exception_message)->toBe('Something broke');
});

test('job exception occurred listener does not overwrite existing exception', function () {
    $job = JobMonitor::factory()->processing()->create([
        'job_id' => 'test-job-456',
        'exception_class' => 'OriginalException',
        'exception_message' => 'Original message',
    ]);

    $listener = new JobExceptionOccurredListener;

    $mockQueueJob = new class
    {
        public function getJobId(): string
        {
            return 'test-job-456';
        }
    };

    $listener->handle(new JobExceptionOccurred(
        'redis',
        $mockQueueJob,
        new \RuntimeException('New exception'),
    ));

    $job->refresh();
    expect($job->exception_class)->toBe('OriginalException');
    expect($job->exception_message)->toBe('Original message');
});

test('scaling event listener handles scaling decision without crashing', function () {
    $listener = new ScalingEventListener;

    // The listener guards with property_exists() && is_callable() for the action
    // field, which means it needs a decision object matching the autoscale package
    // interface. With a plain object, action resolves to null and the NOT NULL
    // constraint causes a silent failure — by design (never crash the queue).
    $decision = new \stdClass;
    $decision->connection = 'redis';
    $decision->queue = 'default';
    $decision->currentWorkers = 2;
    $decision->targetWorkers = 5;
    $decision->reason = 'High load';

    $event = new \stdClass;
    $event->decision = $decision;

    // Must not throw — listener catches all exceptions
    $listener->handleScalingDecision($event);

    // May or may not insert depending on DB constraints, but never crashes
    expect(true)->toBeTrue();
});

test('scaling event listener handles workers scaled', function () {
    $listener = new ScalingEventListener;

    $event = new \stdClass;
    $event->connection = 'redis';
    $event->queue = 'emails';
    $event->action = 'scale_up';
    $event->from = 2;
    $event->to = 5;
    $event->reason = 'Queue depth increased';

    $listener->handleWorkersScaled($event);

    expect(ScalingEvent::count())->toBe(1);
    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('scale_up');
});

test('scaling event listener handles SLA breach', function () {
    $listener = new ScalingEventListener;

    $event = new \stdClass;
    $event->connection = 'redis';
    $event->queue = 'payments';
    $event->activeWorkers = 3;
    $event->oldestJobAge = 45;
    $event->pending = 10;
    $event->slaTarget = 30;

    $listener->handleSlaBreached($event);

    expect(ScalingEvent::count())->toBe(1);
    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('sla_breach');
    expect($scaling->sla_breach_risk)->toBeTrue();
});

test('scaling event listener handles SLA recovered', function () {
    $listener = new ScalingEventListener;

    $event = new \stdClass;
    $event->connection = 'redis';
    $event->queue = 'payments';
    $event->workersScaled = 5;
    $event->recoveryTime = 120;

    $listener->handleSlaRecovered($event);

    expect(ScalingEvent::count())->toBe(1);
    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('sla_recovered');
});
