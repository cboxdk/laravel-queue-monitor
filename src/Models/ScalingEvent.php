<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $connection
 * @property string $queue
 * @property string $action
 * @property int $current_workers
 * @property int $target_workers
 * @property string $reason
 * @property string|null $predicted_pickup_time
 * @property int $sla_target
 * @property bool $sla_breach_risk
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ScalingEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'connection',
        'queue',
        'action',
        'current_workers',
        'target_workers',
        'reason',
        'predicted_pickup_time',
        'sla_target',
        'sla_breach_risk',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_workers' => 'integer',
            'target_workers' => 'integer',
            'predicted_pickup_time' => 'decimal:2',
            'sla_target' => 'integer',
            'sla_breach_risk' => 'boolean',
        ];
    }

    /**
     * Get the table name with prefix from config
     */
    public function getTable(): string
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $prefix.'scaling_events';
    }

    /**
     * Get the database connection for the model
     */
    public function getConnectionName(): ?string
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');

        if ($connection !== null) {
            return $connection;
        }

        return parent::getConnectionName();
    }
}
