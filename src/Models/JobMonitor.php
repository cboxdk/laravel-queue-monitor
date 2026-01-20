<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Models;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $job_id
 * @property string $job_class
 * @property string|null $display_name
 * @property string $connection
 * @property string $queue
 * @property array|null $payload
 * @property JobStatus $status
 * @property int $attempt
 * @property int $max_attempts
 * @property int|null $retried_from_id
 * @property string $server_name
 * @property string $worker_id
 * @property WorkerType $worker_type
 * @property float|null $cpu_time_ms
 * @property float|null $memory_peak_mb
 * @property int|null $file_descriptors
 * @property int|null $duration_ms
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $exception_trace
 * @property array|null $tags
 * @property Carbon $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class JobMonitor extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Cbox\LaravelQueueMonitor\Database\Factories\JobMonitorFactory
    {
        return \Cbox\LaravelQueueMonitor\Database\Factories\JobMonitorFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'job_id',
        'job_class',
        'display_name',
        'connection',
        'queue',
        'payload',
        'status',
        'attempt',
        'max_attempts',
        'retried_from_id',
        'server_name',
        'worker_id',
        'worker_type',
        'cpu_time_ms',
        'memory_peak_mb',
        'file_descriptors',
        'duration_ms',
        'exception_class',
        'exception_message',
        'exception_trace',
        'tags',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the table name with prefix from config
     */
    public function getTable(): string
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return $prefix.'jobs';
    }

    /**
     * Get the database connection for the model
     */
    public function getConnectionName(): ?string
    {
        $connection = config('queue-monitor.database.connection');

        if ($connection !== null) {
            return $connection;
        }

        return parent::getConnectionName();
    }

    /**
     * The attributes that should be cast
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'tags' => 'array',
            'status' => JobStatus::class,
            'worker_type' => WorkerType::class,
            'attempt' => 'integer',
            'max_attempts' => 'integer',
            'cpu_time_ms' => 'decimal:2',
            'memory_peak_mb' => 'decimal:2',
            'file_descriptors' => 'integer',
            'duration_ms' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent job that this job was retried from
     */
    public function retriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retried_from_id');
    }

    /**
     * Get all retry attempts for this job
     */
    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retried_from_id');
    }

    /**
     * Get the normalized tags relationship
     */
    public function tagRecords(): HasMany
    {
        return $this->hasMany(Tag::class, 'job_id');
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, JobStatus|array $status)
    {
        $statuses = is_array($status) ? $status : [$status];

        return $query->whereIn('status', array_map(fn ($s) => $s instanceof JobStatus ? $s->value : $s, $statuses));
    }

    /**
     * Scope to filter by queue
     */
    public function scopeOnQueue($query, string|array $queue)
    {
        $queues = is_array($queue) ? $queue : [$queue];

        return $query->whereIn('queue', $queues);
    }

    /**
     * Scope to filter by connection
     */
    public function scopeOnConnection($query, string $connection)
    {
        return $query->where('connection', $connection);
    }

    /**
     * Scope to filter by job class
     */
    public function scopeForJobClass($query, string|array $jobClass)
    {
        $classes = is_array($jobClass) ? $jobClass : [$jobClass];

        return $query->whereIn('job_class', $classes);
    }

    /**
     * Scope to filter by server
     */
    public function scopeOnServer($query, string|array $serverName)
    {
        $servers = is_array($serverName) ? $serverName : [$serverName];

        return $query->whereIn('server_name', $servers);
    }

    /**
     * Scope to filter by worker type
     */
    public function scopeByWorkerType($query, WorkerType $workerType)
    {
        return $query->where('worker_type', $workerType);
    }

    /**
     * Scope to get finished jobs
     */
    public function scopeFinished($query)
    {
        return $query->whereIn('status', [
            JobStatus::COMPLETED->value,
            JobStatus::FAILED->value,
            JobStatus::TIMEOUT->value,
            JobStatus::CANCELLED->value,
        ]);
    }

    /**
     * Scope to get failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [
            JobStatus::FAILED->value,
            JobStatus::TIMEOUT->value,
        ]);
    }

    /**
     * Scope to get successful jobs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', JobStatus::COMPLETED);
    }

    /**
     * Scope to filter by tags
     */
    public function scopeWithTag($query, string|array $tag)
    {
        $tags = is_array($tag) ? $tag : [$tag];

        return $query->whereJsonContains('tags', $tags);
    }

    /**
     * Scope to get jobs queued within a time range
     */
    public function scopeQueuedBetween($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('queued_at', [$start, $end]);
    }

    /**
     * Scope to get jobs with duration exceeding threshold
     */
    public function scopeSlowJobs($query, int $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    /**
     * Check if the job is finished
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Check if the job was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Check if the job failed
     */
    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    /**
     * Check if the job is retryable
     */
    public function isRetryable(): bool
    {
        return $this->isFailed() && $this->attempt < $this->max_attempts;
    }

    /**
     * Check if this is a retry
     */
    public function isRetry(): bool
    {
        return $this->retried_from_id !== null;
    }

    /**
     * Get duration in seconds
     */
    public function getDurationInSeconds(): ?float
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return $this->duration_ms / 1000;
    }

    /**
     * Get short job class name
     */
    public function getShortJobClass(): string
    {
        $parts = explode('\\', $this->job_class);

        return end($parts);
    }

    /**
     * Get short exception class name
     */
    public function getShortExceptionClass(): ?string
    {
        if ($this->exception_class === null) {
            return null;
        }

        $parts = explode('\\', $this->exception_class);

        return end($parts);
    }
}
