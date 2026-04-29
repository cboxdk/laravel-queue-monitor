<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Core\PruneEventsAction;
use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;

test('prune events deletes old scaling events', function () {
    ScalingEvent::create([
        'connection' => 'redis', 'queue' => 'default', 'action' => 'scale_up',
        'current_workers' => 1, 'target_workers' => 3, 'reason' => 'test',
        'created_at' => now()->subDays(10),
    ]);
    ScalingEvent::create([
        'connection' => 'redis', 'queue' => 'default', 'action' => 'scale_up',
        'current_workers' => 1, 'target_workers' => 3, 'reason' => 'test',
        'created_at' => now()->subDay(),
    ]);

    $action = new PruneEventsAction;
    $result = $action->execute(7);

    expect(ScalingEvent::count())->toBe(1);
    expect($result['scaling_events_deleted'])->toBe(1);
});

test('prune events deletes old cluster events', function () {
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'scaling_signal',
        'created_at' => now()->subDays(10),
    ]);
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'scaling_signal',
        'created_at' => now()->subDay(),
    ]);

    $action = new PruneEventsAction;
    $result = $action->execute(7);

    expect(ClusterEvent::count())->toBe(1);
    expect($result['cluster_events_deleted'])->toBe(1);
});

test('prune events nulls meta on old cluster events (payload pruning)', function () {
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'summary_published',
        'meta' => ['summary' => ['big' => 'data']],
        'created_at' => now()->subDays(3),
    ]);
    ClusterEvent::create([
        'cluster_id' => 'c1', 'event_type' => 'summary_published',
        'meta' => ['summary' => ['fresh' => 'data']],
        'created_at' => now()->subHour(),
    ]);

    $action = new PruneEventsAction;
    $result = $action->execute(7, 2);

    // Old one should have meta nulled but row preserved
    expect(ClusterEvent::count())->toBe(2);
    $old = ClusterEvent::orderBy('created_at')->first();
    expect($old->meta)->toBeNull();
    $fresh = ClusterEvent::orderByDesc('created_at')->first();
    expect($fresh->meta)->not->toBeNull();
    expect($result['payloads_pruned'])->toBe(1);
});

test('prune events handles missing tables gracefully', function () {
    // This test just ensures no exception is thrown when tables exist but are empty
    $action = new PruneEventsAction;
    $result = $action->execute(7);

    expect($result)->toHaveKeys(['scaling_events_deleted', 'cluster_events_deleted', 'payloads_pruned']);
});
