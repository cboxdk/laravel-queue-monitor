<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Cbox\LaravelQueueMonitor\Services\InfrastructureService;

test('getClusterData returns null when no cluster events exist', function () {
    $service = app(InfrastructureService::class);
    $data = $service->getClusterData();

    expect($data)->toBeNull();
});

test('getClusterData returns topology from manager events', function () {
    ClusterEvent::create([
        'cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'manager_started',
        'host' => 'web-01', 'meta' => ['started_at' => time()], 'created_at' => now(),
    ]);
    ClusterEvent::create([
        'cluster_id' => 'c1', 'manager_id' => 'mgr-2', 'event_type' => 'manager_started',
        'host' => 'web-02', 'meta' => ['started_at' => time()], 'created_at' => now(),
    ]);

    $service = app(InfrastructureService::class);
    $data = $service->getClusterData();

    expect($data)->not->toBeNull();
    expect($data['has_cluster'])->toBeTrue();
    expect($data['topology']['cluster_id'])->toBe('c1');
});

test('getClusterData returns latest scaling signal', function () {
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'scaling_signal',
        'leader_id' => 'mgr-1', 'current_hosts' => 3, 'recommended_hosts' => 5,
        'current_capacity' => 15, 'required_workers' => 20,
        'action' => 'scale_up', 'reason' => 'Need more hosts',
        'created_at' => now(),
    ]);

    $service = app(InfrastructureService::class);
    $data = $service->getClusterData();

    expect($data['scaling_signal']['current_hosts'])->toBe(3);
    expect($data['scaling_signal']['recommended_hosts'])->toBe(5);
    expect($data['scaling_signal']['action'])->toBe('scale_up');
});

test('getClusterData returns signal history for sparkline', function () {
    for ($i = 0; $i < 5; $i++) {
        ClusterEvent::create([
            'cluster_id' => 'c1', 'event_type' => 'scaling_signal',
            'current_hosts' => $i + 1, 'recommended_hosts' => 5,
            'created_at' => now()->subMinutes(5 * $i),
        ]);
    }

    $service = app(InfrastructureService::class);
    $data = $service->getClusterData();

    expect(count($data['signal_history']))->toBe(5);
});

test('getClusterData returns leader history', function () {
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'leader_changed',
        'leader_id' => 'mgr-2', 'previous_leader_id' => 'mgr-1',
        'created_at' => now(),
    ]);

    $service = app(InfrastructureService::class);
    $data = $service->getClusterData();

    expect(count($data['leader_history']))->toBe(1);
    expect($data['leader_history'][0]['leader_id'])->toBe('mgr-2');
});

test('getScalingData includes sla_breach_predicted in summary', function () {
    ScalingEvent::create([
        'connection' => 'redis', 'queue' => 'default', 'action' => 'sla_breach_predicted',
        'current_workers' => 2, 'target_workers' => 5, 'reason' => 'predicted',
        'sla_breach_risk' => true, 'created_at' => now(),
    ]);
    ScalingEvent::create([
        'connection' => 'redis', 'queue' => 'default', 'action' => 'scale_up',
        'current_workers' => 2, 'target_workers' => 5, 'reason' => 'test',
        'created_at' => now(),
    ]);

    $service = app(InfrastructureService::class);
    $data = $service->getScalingData();

    expect($data['summary']['sla_breach_predictions'])->toBe(1);
    expect($data['summary']['total_decisions'])->toBe(2);
});

test('getScalingData includes breach severity data', function () {
    ScalingEvent::create([
        'connection' => 'redis', 'queue' => 'default', 'action' => 'sla_breach',
        'current_workers' => 3, 'target_workers' => 3, 'reason' => 'breached',
        'breach_seconds' => 15, 'breach_percentage' => 50.0,
        'sla_breach_risk' => true, 'created_at' => now(),
    ]);

    $service = app(InfrastructureService::class);
    $data = $service->getScalingData();

    expect($data['breach_severity'])->not->toBeNull();
    expect($data['breach_severity']['avg_breach_seconds'])->toBe(15.0);
});

// ── Failure fuse state ───────────────────────────────────────────────────────
//
// The timeline shows the moment a fuse tripped; open_fuses shows that it is
// still holding. A fuse that tripped an hour ago has scrolled off the timeline
// while the queue it holds is still at zero workers.

function fuseEvent(string $queue, string $action, int $workers, string $ago = '-5 minutes'): ScalingEvent
{
    return ScalingEvent::create([
        'connection' => 'redis',
        'queue' => $queue,
        'action' => $action,
        'current_workers' => $workers,
        'target_workers' => $workers,
        'reason' => 'test',
        'created_at' => now()->parse($ago),
    ]);
}

test('a tripped fuse is reported as holding the queue', function () {
    fuseEvent('webhooks', 'fuse_tripped', 0);

    $fuses = app(InfrastructureService::class)->getScalingData()['open_fuses'];

    expect($fuses)->toHaveCount(1)
        ->and($fuses[0]['queue'])->toBe('webhooks')
        ->and($fuses[0]['state'])->toBe('tripped')
        ->and($fuses[0]['held_at_workers'])->toBe(0)
        ->and($fuses[0]['connection'])->toBe('redis');
});

test('a probing fuse is still holding, at its probe count', function () {
    fuseEvent('webhooks', 'fuse_probing', 1);

    $fuses = app(InfrastructureService::class)->getScalingData()['open_fuses'];

    expect($fuses)->toHaveCount(1)
        ->and($fuses[0]['state'])->toBe('probing')
        ->and($fuses[0]['held_at_workers'])->toBe(1);
});

test('a recovered fuse is no longer holding', function () {
    fuseEvent('webhooks', 'fuse_tripped', 0, '-10 minutes');
    fuseEvent('webhooks', 'fuse_probing', 1, '-5 minutes');
    fuseEvent('webhooks', 'fuse_recovered', 0, '-1 minute');

    expect(app(InfrastructureService::class)->getScalingData()['open_fuses'])->toBeEmpty();
});

test('only the latest event decides a queue state', function () {
    // A fuse that recovered and tripped again is tripped, not both.
    fuseEvent('webhooks', 'fuse_recovered', 0, '-10 minutes');
    fuseEvent('webhooks', 'fuse_tripped', 0, '-1 minute');

    $fuses = app(InfrastructureService::class)->getScalingData()['open_fuses'];

    expect($fuses)->toHaveCount(1)
        ->and($fuses[0]['state'])->toBe('tripped');
});

test('queues are tracked independently', function () {
    fuseEvent('webhooks', 'fuse_tripped', 0, '-2 minutes');
    fuseEvent('invoices', 'fuse_recovered', 0, '-2 minutes');
    fuseEvent('reports', 'fuse_probing', 1, '-1 minute');

    $fuses = app(InfrastructureService::class)->getScalingData()['open_fuses'];

    expect(array_column($fuses, 'queue'))->toEqualCanonicalizing(['webhooks', 'reports']);
});

test('the same queue name on two connections is not conflated', function () {
    ScalingEvent::create(['connection' => 'redis', 'queue' => 'default', 'action' => 'fuse_tripped', 'current_workers' => 0, 'target_workers' => 0, 'reason' => 'test', 'created_at' => now()]);
    ScalingEvent::create(['connection' => 'sqs', 'queue' => 'default', 'action' => 'fuse_recovered', 'current_workers' => 0, 'target_workers' => 0, 'reason' => 'test', 'created_at' => now()]);

    $fuses = app(InfrastructureService::class)->getScalingData()['open_fuses'];

    expect($fuses)->toHaveCount(1)
        ->and($fuses[0]['connection'])->toBe('redis');
});

test('a fuse trip is counted but is not a scaling decision', function () {
    fuseEvent('webhooks', 'fuse_tripped', 0, '-2 minutes');
    ScalingEvent::create(['connection' => 'redis', 'queue' => 'emails', 'action' => 'scale_up', 'current_workers' => 1, 'target_workers' => 3, 'reason' => 'test', 'created_at' => now()]);

    $summary = app(InfrastructureService::class)->getScalingData()['summary'];

    expect($summary['fuse_trips'])->toBe(1)
        ->and($summary['total_decisions'])->toBe(1);
});

// ── Leadership stability ─────────────────────────────────────────────────────

test('leadership is reported stable when nothing flagged it', function () {
    ClusterEvent::create(['cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'leader_changed', 'leader_id' => 'mgr-1', 'created_at' => now()]);

    $leadership = app(InfrastructureService::class)->getClusterData()['leadership'];

    expect($leadership['unstable'])->toBeFalse()
        ->and($leadership['changes_in_window'])->toBe(1)
        ->and($leadership['window_seconds'])->toBe(60);
});

test('an unstable flag inside the window surfaces with its reason', function () {
    ClusterEvent::create(['cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'leader_changed', 'leader_id' => 'mgr-1', 'created_at' => now()]);
    ClusterEvent::create(['cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'leadership_unstable', 'leader_id' => 'mgr-1', 'reason' => '5 leadership changes in 60s', 'meta' => ['changes_observed' => 5], 'created_at' => now()]);

    $leadership = app(InfrastructureService::class)->getClusterData()['leadership'];

    expect($leadership['unstable'])->toBeTrue()
        ->and($leadership['reason'])->toBe('5 leadership changes in 60s')
        ->and($leadership['flagged_at'])->not->toBeNull();
});

test('a flag that has aged out of the window no longer shows as unstable', function () {
    // Otherwise a cluster that settled hours ago stays painted amber forever.
    ClusterEvent::create(['cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'leader_changed', 'leader_id' => 'mgr-1', 'created_at' => now()->subHour()]);
    ClusterEvent::create(['cluster_id' => 'c1', 'manager_id' => 'mgr-1', 'event_type' => 'leadership_unstable', 'leader_id' => 'mgr-1', 'reason' => 'old', 'created_at' => now()->subHour()]);

    $leadership = app(InfrastructureService::class)->getClusterData()['leadership'];

    expect($leadership['unstable'])->toBeFalse()
        ->and($leadership['changes_in_window'])->toBe(0);
});
