<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Transformers;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;

final class JobMonitorTransformer
{
    /**
     * Transform a job for list views (overview, jobs tab).
     *
     * @return array<string, mixed>
     */
    public static function toListArray(JobMonitor $job): array
    {
        return [
            'uuid' => $job->uuid,
            'job_class' => $job->getShortJobClass(),
            'full_job_class' => $job->job_class,
            'queue' => $job->queue,
            'connection' => $job->connection,
            'status' => [
                'value' => $job->status->value,
                'label' => $job->status->label(),
                'color' => $job->status->color(),
            ],
            'worker_type' => [
                'value' => $job->worker_type->value,
                'label' => $job->worker_type->label(),
                'icon' => $job->worker_type->icon(),
            ],
            'attempt' => $job->attempt,
            'max_attempts' => $job->max_attempts,
            'server' => $job->server_name,
            'duration_ms' => $job->duration_ms,
            'duration' => $job->duration_ms ? number_format($job->duration_ms).'ms' : '-',
            'cpu_time_ms' => $job->cpu_time_ms,
            'memory_peak_mb' => $job->memory_peak_mb,
            'worker_memory_limit_mb' => $job->worker_memory_limit_mb,
            'queued_at' => $job->queued_at->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'error' => $job->exception_message,
            'is_failed' => $job->isFailed(),
        ];
    }

    /**
     * Transform a job for the detail slide-over view.
     *
     * @param  array<string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    public static function toDetailArray(JobMonitor $job, array $sensitiveKeys = []): array
    {
        return [
            'uuid' => $job->uuid,
            'job_class' => $job->job_class,
            'short_job_class' => $job->getShortJobClass(),
            'display_name' => $job->display_name,
            'queue' => $job->queue,
            'connection' => $job->connection,
            'status' => [
                'value' => $job->status->value,
                'label' => $job->status->label(),
                'color' => $job->status->color(),
            ],
            'worker_type' => [
                'value' => $job->worker_type->value,
                'label' => $job->worker_type->label(),
                'icon' => $job->worker_type->icon(),
            ],
            'attempt' => $job->attempt,
            'max_attempts' => $job->max_attempts,
            'server' => $job->server_name,
            'worker_id' => $job->worker_id,
            'metrics' => [
                'duration_ms' => $job->duration_ms,
                'cpu_time_ms' => $job->cpu_time_ms,
                'memory_peak_mb' => $job->memory_peak_mb,
                'worker_memory_limit_mb' => $job->worker_memory_limit_mb,
                'file_descriptors' => $job->file_descriptors,
                'wait_time_ms' => $job->started_at !== null
                    ? (int) $job->queued_at->diffInMilliseconds($job->started_at)
                    : null,
                'total_time_ms' => $job->completed_at !== null
                    ? (int) $job->queued_at->diffInMilliseconds($job->completed_at)
                    : null,
                'delay_ms' => $job->available_at !== null
                    ? (int) $job->queued_at->diffInMilliseconds($job->available_at)
                    : null,
                'pickup_latency_ms' => $job->started_at !== null && $job->available_at !== null
                    ? (int) $job->available_at->diffInMilliseconds($job->started_at)
                    : null,
            ],
            'timestamps' => [
                'queued_at' => $job->queued_at->toIso8601String(),
                'available_at' => $job->available_at?->toIso8601String(),
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
            ],
            'tags' => $job->tags,
            'is_failed' => $job->isFailed(),
            'is_retryable' => $job->isRetryable(),
        ];
    }

    /**
     * Transform a job for the retry chain list.
     *
     * @return array<string, mixed>
     */
    public static function toRetryChainArray(JobMonitor $job, string $currentUuid): array
    {
        return [
            'uuid' => $job->uuid,
            'attempt' => $job->attempt,
            'status' => [
                'value' => $job->status->value,
                'label' => $job->status->label(),
                'color' => $job->status->color(),
            ],
            'duration_ms' => $job->duration_ms,
            'memory_peak_mb' => $job->memory_peak_mb,
            'server_name' => $job->server_name,
            'worker_id' => $job->worker_id,
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'exception_class' => $job->exception_class,
            'exception_message' => $job->exception_message,
            'exception_trace' => PayloadRedactor::redactTrace($job->exception_trace),
            'wait_time_ms' => $job->started_at !== null
                ? (int) $job->queued_at->diffInMilliseconds($job->started_at)
                : null,
            'is_current' => $job->uuid === $currentUuid,
        ];
    }
}
