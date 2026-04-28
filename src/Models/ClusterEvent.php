<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $cluster_id
 * @property string|null $manager_id
 * @property string $event_type
 * @property string|null $host
 * @property string|null $leader_id
 * @property string|null $previous_leader_id
 * @property int|null $current_hosts
 * @property int|null $recommended_hosts
 * @property int|null $current_capacity
 * @property int|null $required_workers
 * @property string|null $action
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 */
class ClusterEvent extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'cluster_id',
        'manager_id',
        'event_type',
        'host',
        'leader_id',
        'previous_leader_id',
        'current_hosts',
        'recommended_hosts',
        'current_capacity',
        'required_workers',
        'action',
        'reason',
        'meta',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'current_hosts' => 'integer',
            'recommended_hosts' => 'integer',
            'current_capacity' => 'integer',
            'required_workers' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $prefix.'cluster_events';
    }

    public function getConnectionName(): ?string
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');

        if ($connection !== null) {
            return $connection;
        }

        return parent::getConnectionName();
    }

    /** @param Builder<self> $query */
    public function scopeForCluster(Builder $query, string $clusterId): void
    {
        $query->where('cluster_id', $clusterId);
    }

    /** @param Builder<self> $query */
    public function scopeOfType(Builder $query, string $eventType): void
    {
        $query->where('event_type', $eventType);
    }

    /** @param Builder<self> $query */
    public function scopeRecent(Builder $query, int $hours = 1): void
    {
        $query->where('created_at', '>=', now()->subHours($hours));
    }
}
