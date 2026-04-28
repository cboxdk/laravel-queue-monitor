<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\ClusterEvent;

test('cluster event uses configured table prefix', function () {
    $event = new ClusterEvent;
    expect($event->getTable())->toBe('queue_monitor_cluster_events');
});

test('cluster event uses configured database connection', function () {
    config(['queue-monitor.database.connection' => null]);
    $event = new ClusterEvent;
    expect($event->getConnectionName())->toBeNull();
});

test('cluster event casts meta as array', function () {
    $event = ClusterEvent::create([
        'cluster_id' => 'cluster-1',
        'event_type' => 'scaling_signal',
        'meta' => ['key' => 'value'],
        'created_at' => now(),
    ]);

    $event->refresh();
    expect($event->meta)->toBe(['key' => 'value']);
});

test('cluster event casts integer columns', function () {
    $event = ClusterEvent::create([
        'cluster_id' => 'cluster-1',
        'event_type' => 'scaling_signal',
        'current_hosts' => '3',
        'recommended_hosts' => '5',
        'current_capacity' => '15',
        'required_workers' => '20',
        'created_at' => now(),
    ]);

    $event->refresh();
    expect($event->current_hosts)->toBe(3);
    expect($event->recommended_hosts)->toBe(5);
    expect($event->current_capacity)->toBe(15);
    expect($event->required_workers)->toBe(20);
});

test('cluster event scope forCluster filters by cluster_id', function () {
    ClusterEvent::create(['cluster_id' => 'c1', 'event_type' => 'scaling_signal', 'created_at' => now()]);
    ClusterEvent::create(['cluster_id' => 'c2', 'event_type' => 'scaling_signal', 'created_at' => now()]);

    expect(ClusterEvent::forCluster('c1')->count())->toBe(1);
});

test('cluster event scope ofType filters by event_type', function () {
    ClusterEvent::create(['cluster_id' => 'c1', 'event_type' => 'scaling_signal', 'created_at' => now()]);
    ClusterEvent::create(['cluster_id' => 'c1', 'event_type' => 'leader_changed', 'created_at' => now()]);

    expect(ClusterEvent::ofType('scaling_signal')->count())->toBe(1);
});

test('cluster event scope recent filters by time window', function () {
    ClusterEvent::create(['cluster_id' => 'c1', 'event_type' => 'scaling_signal', 'created_at' => now()]);
    ClusterEvent::create(['cluster_id' => 'c1', 'event_type' => 'scaling_signal', 'created_at' => now()->subHours(2)]);

    expect(ClusterEvent::recent(1)->count())->toBe(1);
});
