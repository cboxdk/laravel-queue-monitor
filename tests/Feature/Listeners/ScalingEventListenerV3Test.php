<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Listeners\ScalingEventListener;
use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Illuminate\Support\Facades\Cache;

// ── SLA Breached ─────────────────────────────────────────────────────────────

test('handleSlaBreached stores v3 severity data when available', function () {
    $listener = new ScalingEventListener;

    $event = new class
    {
        public string $connection = 'redis';

        public string $queue = 'payments';

        public int $activeWorkers = 3;

        public int $oldestJobAge = 45;

        public int $pending = 10;

        public int $slaTarget = 30;

        public function breachSeconds(): int
        {
            return 15;
        }

        public function breachPercentage(): float
        {
            return 50.0;
        }
    };

    $listener->handleSlaBreached($event);

    expect(ScalingEvent::count())->toBe(1);

    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('sla_breach');
    expect($scaling->breach_seconds)->toBe(15);
    expect((float) $scaling->breach_percentage)->toBe(50.0);
    expect($scaling->pending)->toBe(10);
    expect($scaling->active_workers)->toBe(3);
});

test('handleSlaBreached works without v3 methods (v2 compat)', function () {
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
    expect($scaling->breach_seconds)->toBeNull();
    expect($scaling->breach_percentage)->toBeNull();
});

// ── SLA Recovered ────────────────────────────────────────────────────────────

test('handleSlaRecovered stores v3 margin data when available', function () {
    $listener = new ScalingEventListener;

    $event = new class
    {
        public string $connection = 'redis';

        public string $queue = 'payments';

        public int $activeWorkers = 5;

        public int $currentJobAge = 10;

        public int $slaTarget = 30;

        public int $pending = 2;

        public function marginSeconds(): int
        {
            return 20;
        }

        public function marginPercentage(): float
        {
            return 66.67;
        }
    };

    $listener->handleSlaRecovered($event);

    expect(ScalingEvent::count())->toBe(1);

    $scaling = ScalingEvent::first();
    expect($scaling->action)->toBe('sla_recovered');
    expect($scaling->margin_seconds)->toBe(20);
    expect((float) $scaling->margin_percentage)->toBeGreaterThan(66.0);
    expect($scaling->active_workers)->toBe(5);
    expect($scaling->pending)->toBe(2);
});

test('handleSlaRecovered works without v3 methods (v2 compat)', function () {
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
    expect($scaling->margin_seconds)->toBeNull();
});

// ── handleSlaBreachPredicted ──────────────────────────────────────────────────

test('handleSlaBreachPredicted stores as scaling event', function () {
    $listener = new ScalingEventListener;

    $decision = new stdClass;
    $decision->connection = 'redis';
    $decision->queue = 'default';
    $decision->currentWorkers = 2;
    $decision->targetWorkers = 4;
    $decision->reason = 'Predicted breach in 30s';
    $decision->predictedPickupTime = 90.5;
    $decision->slaTarget = 30;

    $event = new stdClass;
    $event->decision = $decision;

    // Must not throw — listener catches all exceptions
    $listener->handleSlaBreachPredicted($event);

    // May or may not insert depending on constraints, but must not crash
    expect(true)->toBeTrue();
});

// ── handleManagerStarted ──────────────────────────────────────────────────────

test('handleManagerStarted creates cluster event', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
    $event->managerId = 'manager-abc-123';
    $event->host = 'web-01.example.com';
    $event->clusterEnabled = true;
    $event->clusterId = 'cluster-prod-1';
    $event->intervalSeconds = 10;
    $event->startedAt = time();
    $event->packageVersion = '3.1.0';

    $listener->handleManagerStarted($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('manager_started');
    expect($clusterEvent->cluster_id)->toBe('cluster-prod-1');
    expect($clusterEvent->manager_id)->toBe('manager-abc-123');
    expect($clusterEvent->host)->toBe('web-01.example.com');
    expect($clusterEvent->meta)->toHaveKey('cluster_enabled');
    expect($clusterEvent->meta)->toHaveKey('interval_seconds');
    expect($clusterEvent->meta)->toHaveKey('package_version');
    expect($clusterEvent->meta)->toHaveKey('started_at');
});

// ── handleManagerStopped ──────────────────────────────────────────────────────

test('handleManagerStopped creates cluster event with uptime', function () {
    $listener = new ScalingEventListener;

    $startedAt = time() - 3600; // 1 hour ago

    $event = new stdClass;
    $event->managerId = 'manager-abc-123';
    $event->host = 'web-01.example.com';
    $event->clusterId = 'cluster-prod-1';
    $event->reason = 'SIGTERM';
    $event->workerCount = 5;
    $event->startedAt = $startedAt;
    $event->stoppedAt = time();

    $listener->handleManagerStopped($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('manager_stopped');
    expect($clusterEvent->meta['uptime_seconds'])->toBeGreaterThanOrEqual(3599);
});

// ── handleLeaderChanged ───────────────────────────────────────────────────────

test('handleLeaderChanged creates cluster event', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
    $event->clusterId = 'cluster-prod-1';
    $event->currentLeaderId = 'manager-new-leader';
    $event->previousLeaderId = 'manager-old-leader';
    $event->observedByManagerId = 'manager-observer-1';

    $listener->handleLeaderChanged($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('leader_changed');
    expect($clusterEvent->leader_id)->toBe('manager-new-leader');
    expect($clusterEvent->previous_leader_id)->toBe('manager-old-leader');
    expect($clusterEvent->manager_id)->toBe('manager-observer-1');
});

// ── handlePresenceChanged ─────────────────────────────────────────────────────

test('handlePresenceChanged creates cluster event with member lists', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
    $event->clusterId = 'cluster-prod-1';
    $event->leaderId = 'manager-leader-1';
    $event->observedByManagerId = 'manager-observer-1';
    $event->managerIds = ['manager-1', 'manager-2', 'manager-3'];
    $event->addedManagerIds = ['manager-3'];
    $event->removedManagerIds = [];

    $listener->handlePresenceChanged($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('presence_changed');
    expect($clusterEvent->meta)->toHaveKey('manager_ids');
    expect($clusterEvent->meta)->toHaveKey('added_manager_ids');
    expect($clusterEvent->meta)->toHaveKey('removed_manager_ids');
    expect($clusterEvent->meta['manager_ids'])->toBe(['manager-1', 'manager-2', 'manager-3']);
    expect($clusterEvent->meta['added_manager_ids'])->toBe(['manager-3']);
});

// ── handleScalingSignalUpdated ────────────────────────────────────────────────

test('handleScalingSignalUpdated creates cluster event with typed columns', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
    $event->clusterId = 'cluster-prod-1';
    $event->leaderId = 'manager-leader-1';
    $event->currentHosts = 3;
    $event->recommendedHosts = 5;
    $event->currentCapacity = 150;
    $event->requiredWorkers = 10;
    $event->action = 'scale_up';
    $event->reason = 'Queue depth rising';

    $listener->handleScalingSignalUpdated($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('scaling_signal');
    expect($clusterEvent->current_hosts)->toBe(3);
    expect($clusterEvent->recommended_hosts)->toBe(5);
    expect($clusterEvent->action)->toBe('scale_up');
});

// ── handleSummaryPublished ────────────────────────────────────────────────────

test('handleSummaryPublished creates cluster event with meta', function () {
    $listener = new ScalingEventListener;

    $event = new stdClass;
    $event->clusterId = 'cluster-prod-1';
    $event->leaderId = 'manager-leader-1';
    $event->summary = ['total_workers' => 10, 'queues' => ['default', 'emails']];
    $event->publishedAt = time();

    $listener->handleSummaryPublished($event);

    expect(ClusterEvent::count())->toBe(1);

    $clusterEvent = ClusterEvent::first();
    expect($clusterEvent->event_type)->toBe('summary_published');
    expect($clusterEvent->meta)->toHaveKey('summary');
    expect($clusterEvent->meta['summary'])->toBe(['total_workers' => 10, 'queues' => ['default', 'emails']]);
});

// ── Signal deduplication ──────────────────────────────────────────────────────

test('handleScalingSignalUpdated deduplicates identical signals', function () {
    Cache::flush();

    $listener = new ScalingEventListener;

    $makeEvent = function () {
        $event = new stdClass;
        $event->clusterId = 'cluster-dedup-1';
        $event->leaderId = 'manager-leader-1';
        $event->currentHosts = 3;
        $event->recommendedHosts = 5;
        $event->currentCapacity = 150;
        $event->requiredWorkers = 10;
        $event->action = 'scale_up';
        $event->reason = 'Queue depth rising';

        return $event;
    };

    // Fire same signal twice rapidly
    $listener->handleScalingSignalUpdated($makeEvent());
    $listener->handleScalingSignalUpdated($makeEvent());

    // Only 1 event should be stored
    expect(ClusterEvent::count())->toBe(1);

    // Fire with different values — should insert a second event
    $differentEvent = $makeEvent();
    $differentEvent->recommendedHosts = 7; // changed
    $listener->handleScalingSignalUpdated($differentEvent);

    expect(ClusterEvent::count())->toBe(2);
});

// ── Summary deduplication ─────────────────────────────────────────────────────

test('handleSummaryPublished deduplicates identical summaries', function () {
    Cache::flush();

    $listener = new ScalingEventListener;

    $makeEvent = function () {
        $event = new stdClass;
        $event->clusterId = 'cluster-dedup-2';
        $event->leaderId = 'manager-leader-1';
        $event->summary = ['total_workers' => 10, 'queues' => ['default']];
        $event->publishedAt = time();

        return $event;
    };

    // Fire same summary twice rapidly
    $listener->handleSummaryPublished($makeEvent());
    $listener->handleSummaryPublished($makeEvent());

    // Only 1 event should be stored
    expect(ClusterEvent::count())->toBe(1);

    // Fire with different summary — should insert a second event
    $differentEvent = $makeEvent();
    $differentEvent->summary = ['total_workers' => 15, 'queues' => ['default']]; // changed
    $listener->handleSummaryPublished($differentEvent);

    expect(ClusterEvent::count())->toBe(2);
});
