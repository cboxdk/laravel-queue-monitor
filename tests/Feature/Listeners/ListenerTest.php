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
    $mockEvent = new stdClass;

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
        expect(fn () => $listener->handle($mockEvent))->not->toThrow(Throwable::class);
    }

    expect(JobMonitor::count())->toBe(0);
});

test('job exception occurred listener skips when disabled', function () {
    config()->set('queue-monitor.enabled', false);

    $listener = app(JobExceptionOccurredListener::class);

    // Should return early without error
    $listener->handle(new JobExceptionOccurred('redis', new class
    {
        public function getJobId(): string
        {
            return 'test-123';
        }
    }, new RuntimeException('test')));

    expect(JobMonitor::count())->toBe(0);
});

test('job exception occurred listener captures exception on processing job', function () {
    $job = JobMonitor::factory()->processing()->create(['job_id' => 'test-job-123']);

    $listener = app(JobExceptionOccurredListener::class);

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
        new RuntimeException('Something broke'),
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

    $listener = app(JobExceptionOccurredListener::class);

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
        new RuntimeException('New exception'),
    ));

    $job->refresh();
    expect($job->exception_class)->toBe('OriginalException');
    expect($job->exception_message)->toBe('Original message');
});

test('scaling event listener persists scaling decision with action and breach risk', function () {
    $listener = new ScalingEventListener;

    // Mimic the public surface of Cbox\LaravelQueueAutoscale\Scaling\ScalingDecision:
    // - readonly properties for connection/queue/workers/reason/slaTarget
    // - action() method returning 'scale_up' | 'scale_down' | 'hold'
    // - isSlaBreachRisk() method returning bool
    $decision = new class
    {
        public string $connection = 'redis';

        public string $queue = 'default';

        public int $currentWorkers = 2;

        public int $targetWorkers = 5;

        public string $reason = 'High load';

        public ?float $predictedPickupTime = 8.5;

        public int $slaTarget = 10;

        public function action(): string
        {
            return 'scale_up';
        }

        public function isSlaBreachRisk(): bool
        {
            return false;
        }
    };

    $event = new class($decision)
    {
        public function __construct(public readonly object $decision) {}
    };

    $listener->handleScalingDecision($event);

    $row = ScalingEvent::query()->latest('id')->first();
    expect($row)->not->toBeNull()
        ->and($row->connection)->toBe('redis')
        ->and($row->queue)->toBe('default')
        ->and($row->action)->toBe('scale_up')
        ->and($row->current_workers)->toBe(2)
        ->and($row->target_workers)->toBe(5)
        ->and($row->reason)->toBe('High load')
        ->and((float) $row->predicted_pickup_time)->toBe(8.5)
        ->and($row->sla_target)->toBe(10)
        ->and((bool) $row->sla_breach_risk)->toBeFalse();
});

test('scaling event listener tolerates malformed decision objects without crashing', function () {
    $listener = new ScalingEventListener;

    // No action() method, no expected properties — listener must not throw.
    // Insert may silently fail on NOT NULL columns; the point is the autoscale
    // process must keep running.
    $event = new stdClass;
    $event->decision = new stdClass;

    $listener->handleScalingDecision($event);

    expect(true)->toBeTrue();
});

test('scaling event listener handles workers scaled', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
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

    $event = new stdClass;
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

    $event = new stdClass;
    $event->connection = 'redis';
    $event->queue = 'payments';
    $event->workersScaled = 5;
    $event->recoveryTime = 120;

    $listener->handleSlaRecovered($event);

    expect(ScalingEvent::count())->toBe(1);
    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('sla_recovered');
});
