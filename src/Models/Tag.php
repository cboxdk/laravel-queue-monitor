<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Models;

use Carbon\Carbon;
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
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $prefix.'tags';
    }

    /**
     * Get the database connection for the model
     */
    public function getConnectionName(): string
    {
        return config('queue-monitor.database.connection') ?? parent::getConnectionName();
    }

    /**
     * Get the job this tag belongs to
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobMonitor::class, 'job_id');
    }

    /**
     * Scope to filter by tag name
     */
    public function scopeForTag($query, string $tag)
    {
        return $query->where('tag', $tag);
    }
}
