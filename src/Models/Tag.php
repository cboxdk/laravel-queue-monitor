<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_id
 * @property string $tag
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_id',
        'tag',
    ];

    /**
     * Get the table name with prefix from config
     */
    public function getTable(): string
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $prefix.'tags';
    }

    /**
     * Get the database connection for the model
     */
    public function getConnectionName(): string
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');

        return $connection ?? parent::getConnectionName() ?? 'default';
    }

    /**
     * Get the job this tag belongs to
     *
     * @return BelongsTo<JobMonitor, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobMonitor::class, 'job_id');
    }

    /**
     * Scope to filter by tag name
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForTag(Builder $query, string $tag): Builder
    {
        return $query->where('tag', $tag);
    }
}
