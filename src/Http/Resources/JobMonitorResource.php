<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Cbox\LaravelQueueMonitor\Models\JobMonitor
 */
class JobMonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'job_id' => $this->job_id,
            'job_class' => $this->job_class,
            'display_name' => $this->display_name,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'attempt' => $this->attempt,
            'max_attempts' => $this->max_attempts,
            'retried_from_id' => $this->retried_from_id,
            'server_name' => $this->server_name,
            'worker_id' => $this->worker_id,
            'worker_type' => [
                'value' => $this->worker_type->value,
                'label' => $this->worker_type->label(),
            ],
            'metrics' => [
                'cpu_time_ms' => $this->cpu_time_ms,
                'memory_peak_mb' => $this->memory_peak_mb,
                'file_descriptors' => $this->file_descriptors,
                'duration_ms' => $this->duration_ms,
                'duration_seconds' => $this->getDurationInSeconds(),
            ],
            'exception' => $this->exception_class ? [
                'class' => $this->exception_class,
                'short_class' => $this->getShortExceptionClass(),
                'message' => $this->exception_message,
            ] : null,
            'tags' => $this->tags,
            'timestamps' => [
                'queued_at' => $this->queued_at?->toIso8601String(),
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'flags' => [
                'is_finished' => $this->isFinished(),
                'is_successful' => $this->isSuccessful(),
                'is_failed' => $this->isFailed(),
                'is_retryable' => $this->isRetryable(),
                'is_retry' => $this->isRetry(),
            ],
        ];
    }
}
