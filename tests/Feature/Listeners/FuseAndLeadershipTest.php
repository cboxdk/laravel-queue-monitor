<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Listeners\ScalingEventListener;
use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;

// ── Failure fuse ─────────────────────────────────────────────────────────────
//
// From the dashboard's point of view a fuse trip IS a scaling event: the fleet
// stops growing, or shrinks, while the backlog climbs. Without these the
// autoscaler simply looks broken — the queue is deep, the workers are going
// away, and nothing on screen says why.

test('handleFuseTripped records the trip and what it is holding at', function () {
    $listener = new ScalingEventListener;

    $event = new class
    {
        public string $connection = 'redis';

        public string $queue = 'webhooks';

        public float $failureRate = 100.0;

        public int $samples = 31;

        public int $failures = 31;

        public float $thresholdPercent = 50.0;

        public int $heldAtWorkers = 0;
    };

    $listener->handleFuseTripped($event);

    $scaling = ScalingEvent::first();

    expect(ScalingEvent::count())->toBe(1)
        ->and($scaling->action)->toBe('fuse_tripped')
        ->and($scaling->queue)->toBe('webhooks')
        ->and($scaling->target_workers)->toBe(0)
        ->and($scaling->reason)->toContain('100.0% of 31 jobs failing');
});

test('handleFuseProbing records the single worker let through', function () {
    $listener = new ScalingEventListener;

    $event = new class
    {
        public string $connection = 'redis';

        public string $queue = 'webhooks';

        public int $probeWorkers = 1;

        public int $cooldownSeconds = 60;
    };

    $listener->handleFuseProbing($event);

    $scaling = ScalingEvent::first();

    expect($scaling->action)->toBe('fuse_probing')
        ->and($scaling->target_workers)->toBe(1)
        ->and($scaling->reason)->toContain('probing recovery');
});

test('handleFuseRecovered records that normal scaling resumes', function () {
    $listener = new ScalingEventListener;

    $event = new class
    {
        public string $connection = 'redis';

        public string $queue = 'webhooks';

        public float $failureRate = 0.0;

        public int $samples = 50;
    };

    $listener->handleFuseRecovered($event);

    expect(ScalingEvent::first()->action)->toBe('fuse_recovered')
        ->and(ScalingEvent::first()->reason)->toContain('normal scaling resumes');
});

test('a fuse event missing every optional field still records', function () {
    // The autoscale package is optional and its events are duck-typed here, so
    // an older or newer payload must never take the monitor down with it.
    $listener = new ScalingEventListener;

    $listener->handleFuseTripped(new stdClass);

    expect(ScalingEvent::first()->action)->toBe('fuse_tripped')
        ->and(ScalingEvent::first()->queue)->toBe('unknown');
});

// ── Leadership stability ─────────────────────────────────────────────────────
//
// Taking the cluster lease discards worker placement, the anti-flapping window
// and the fair-share ledger's position. One failover costs a cycle; leadership
// that keeps moving means none of them ever completes.

function leaderChange(string $to, string $from = 'mgr-0'): object
{
    return new class($to, $from)
    {
        public function __construct(
            public string $currentLeaderId,
            public string $previousLeaderId,
            public string $clusterId = 'cluster-a',
            public string $observedByManagerId = 'mgr-observer',
        ) {}
    };
}

test('a single failover is not flagged as unstable', function () {
    $listener = new ScalingEventListener;

    $listener->handleLeaderChanged(leaderChange('mgr-1'));

    expect(ClusterEvent::where('event_type', 'leader_changed')->count())->toBe(1)
        ->and(ClusterEvent::where('event_type', 'leadership_unstable')->count())->toBe(0);
});

test('a failover and the handover after it are still tolerated', function () {
    // Warning here would fire on every deploy, and a warning that fires on
    // every deploy is ignored.
    $listener = new ScalingEventListener;

    $listener->handleLeaderChanged(leaderChange('mgr-1'));
    $listener->handleLeaderChanged(leaderChange('mgr-2'));

    expect(ClusterEvent::where('event_type', 'leadership_unstable')->count())->toBe(0);
});

test('a third change inside the window is flagged', function () {
    $listener = new ScalingEventListener;

    $listener->handleLeaderChanged(leaderChange('mgr-1'));
    $listener->handleLeaderChanged(leaderChange('mgr-2'));
    $listener->handleLeaderChanged(leaderChange('mgr-3'));

    $flag = ClusterEvent::where('event_type', 'leadership_unstable')->first();

    expect($flag)->not->toBeNull()
        ->and($flag->cluster_id)->toBe('cluster-a')
        ->and($flag->meta['changes_observed'])->toBe(3)
        ->and($flag->reason)->toContain('anti-flapping');
});

test('an unstable cluster is flagged once per window, not once per change', function () {
    // Otherwise the warning buries the timeline it is warning about.
    $listener = new ScalingEventListener;

    foreach (range(1, 12) as $n) {
        $listener->handleLeaderChanged(leaderChange("mgr-{$n}"));
    }

    expect(ClusterEvent::where('event_type', 'leadership_unstable')->count())->toBe(1)
        ->and(ClusterEvent::where('event_type', 'leader_changed')->count())->toBe(12);
});

test('changes in a different cluster do not flag this one', function () {
    $listener = new ScalingEventListener;

    foreach (range(1, 5) as $n) {
        $other = leaderChange("mgr-{$n}");
        $other->clusterId = 'cluster-b';
        $listener->handleLeaderChanged($other);
    }

    $listener->handleLeaderChanged(leaderChange('mgr-9'));

    expect(ClusterEvent::where('event_type', 'leadership_unstable')->where('cluster_id', 'cluster-a')->count())->toBe(0);
});

test('the threshold can be turned off', function () {
    config()->set('queue-monitor.cluster.leadership_change_threshold', 1);

    $listener = new ScalingEventListener;
    $listener->handleLeaderChanged(leaderChange('mgr-1'));

    // Below two the check declines to run rather than flagging every failover.
    expect(ClusterEvent::where('event_type', 'leadership_unstable')->count())->toBe(0);
});
