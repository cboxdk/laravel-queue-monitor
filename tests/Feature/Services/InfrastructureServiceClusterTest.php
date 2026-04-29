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
