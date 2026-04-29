<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PruneEventsAction
{
    /**
     * Prune event tables: null payloads past soft TTL, delete rows past hard TTL.
     *
     * @return array{scaling_events_deleted: int, cluster_events_deleted: int, payloads_pruned: int}
     */
    public function execute(?int $retentionDays = null, ?int $payloadDays = null): array
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        /** @var int $configRetention */
        $configRetention = config('queue-monitor.retention.days', 7);
        $retention = $retentionDays ?? $configRetention;

        /** @var int|null $configPayload */
        $configPayload = config('queue-monitor.retention.payload_days', 2);
        $payload = $payloadDays ?? $configPayload;

        $cutoff = now()->subDays($retention);

        $scalingDeleted = 0;
        $clusterDeleted = 0;
        $payloadsPruned = 0;

        // Stage 1: Payload pruning (soft TTL) — null out meta on old cluster_events
        // When payload_days is null, skip payload pruning (keep payloads for full retention period)
        if ($payload !== null && Schema::connection($connection)->hasTable($prefix.'cluster_events')) {
            $payloadCutoff = now()->subDays($payload);
            $payloadsPruned = DB::connection($connection)
                ->table($prefix.'cluster_events')
                ->where('created_at', '<', $payloadCutoff)
                ->whereNotNull('meta')
                ->update(['meta' => null]);
        }

        // Stage 2: Row pruning (hard TTL) — delete old rows
        if (Schema::connection($connection)->hasTable($prefix.'scaling_events')) {
            $scalingDeleted = DB::connection($connection)
                ->table($prefix.'scaling_events')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        if (Schema::connection($connection)->hasTable($prefix.'cluster_events')) {
            $clusterDeleted = DB::connection($connection)
                ->table($prefix.'cluster_events')
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        return [
            'scaling_events_deleted' => $scalingDeleted,
            'cluster_events_deleted' => $clusterDeleted,
            'payloads_pruned' => $payloadsPruned,
        ];
    }
}
