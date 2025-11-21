<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Repositories\Eloquent;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final class EloquentJobMonitorRepository implements JobMonitorRepositoryContract
{
    public function create(JobMonitorData $data): JobMonitor
    {
        return JobMonitor::create([
            'uuid' => $data->uuid,
            'job_id' => $data->jobId,
            'job_class' => $data->jobClass,
            'display_name' => $data->displayName,
            'connection' => $data->connection,
            'queue' => $data->queue,
            'payload' => $data->payload,
            'status' => $data->status,
            'attempt' => $data->attempt,
            'max_attempts' => $data->maxAttempts,
            'retried_from_id' => $data->retriedFromId,
            'server_name' => $data->serverName,
            'worker_id' => $data->workerId,
            'worker_type' => $data->workerType,
            'cpu_time_ms' => $data->cpuTimeMs,
            'memory_peak_mb' => $data->memoryPeakMb,
            'file_descriptors' => $data->fileDescriptors,
            'duration_ms' => $data->durationMs,
            'exception_class' => $data->exception?->class,
            'exception_message' => $data->exception?->message,
            'exception_trace' => $data->exception?->trace,
            'tags' => $data->tags,
            'queued_at' => $data->queuedAt,
            'started_at' => $data->startedAt,
            'completed_at' => $data->completedAt,
        ]);
    }

    public function update(string $uuid, array $data): JobMonitor
    {
        $job = $this->findByUuid($uuid);

        if ($job === null) {
            throw new \RuntimeException("Job with UUID {$uuid} not found");
        }

        $job->update($data);

        return $job->fresh();
    }

    public function findByUuid(string $uuid): ?JobMonitor
    {
        return JobMonitor::where('uuid', $uuid)->first();
    }

    public function findByJobId(string $jobId): ?JobMonitor
    {
        return JobMonitor::where('job_id', $jobId)->first();
    }

    public function query(JobFilterData $filters): Collection
    {
        $query = JobMonitor::query();

        $this->applyFilters($query, $filters);

        return $query
            ->orderBy($filters->sortBy, $filters->sortDirection)
            ->limit($filters->limit)
            ->offset($filters->offset)
            ->get();
    }

    public function count(JobFilterData $filters): int
    {
        $query = JobMonitor::query();

        $this->applyFilters($query, $filters);

        return $query->count();
    }

    public function getRetryChain(string $uuid): Collection
    {
        $job = $this->findByUuid($uuid);

        if ($job === null) {
            return collect();
        }

        // Get all jobs with the same UUID (across retries)
        $chain = JobMonitor::where('uuid', $uuid)
            ->orWhere('retried_from_id', $job->id)
            ->orderBy('attempt')
            ->get();

        // If this job is a retry, also get the parent chain
        if ($job->retried_from_id !== null) {
            $parent = JobMonitor::find($job->retried_from_id);
            if ($parent !== null) {
                $parentChain = $this->getRetryChain($parent->uuid);
                $chain = $parentChain->merge($chain)->unique('id')->sortBy('attempt');
            }
        }

        return $chain->values();
    }

    public function prune(int $days, array $statuses = []): int
    {
        $query = JobMonitor::where('created_at', '<', Carbon::now()->subDays($days));

        if (! empty($statuses)) {
            $statusValues = array_map(
                fn ($status) => $status instanceof JobStatus ? $status->value : $status,
                $statuses
            );
            $query->whereIn('status', $statusValues);
        }

        return $query->delete();
    }

    public function delete(string $uuid): bool
    {
        $job = $this->findByUuid($uuid);

        if ($job === null) {
            return false;
        }

        return (bool) $job->delete();
    }

    public function getByQueue(string $queue, ?string $connection = null, int $limit = 100): Collection
    {
        $query = JobMonitor::where('queue', $queue);

        if ($connection !== null) {
            $query->where('connection', $connection);
        }

        return $query->orderByDesc('queued_at')
            ->limit($limit)
            ->get();
    }

    public function getByServer(string $serverName, int $limit = 100): Collection
    {
        return JobMonitor::where('server_name', $serverName)
            ->orderByDesc('queued_at')
            ->limit($limit)
            ->get();
    }

    public function getFailedJobs(int $limit = 100): Collection
    {
        return JobMonitor::whereIn('status', [
            JobStatus::FAILED->value,
            JobStatus::TIMEOUT->value,
        ])
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get();
    }

    public function getRecentJobs(int $limit = 100): Collection
    {
        return JobMonitor::orderByDesc('queued_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, JobFilterData $filters): void
    {
        if ($filters->statuses !== null && count($filters->statuses) > 0) {
            $query->whereIn('status', array_map(fn ($s) => $s->value, $filters->statuses));
        }

        if ($filters->queues !== null && count($filters->queues) > 0) {
            $query->whereIn('queue', $filters->queues);
        }

        if ($filters->connection !== null) {
            $query->where('connection', $filters->connection);
        }

        if ($filters->jobClasses !== null && count($filters->jobClasses) > 0) {
            $query->whereIn('job_class', $filters->jobClasses);
        }

        if ($filters->serverNames !== null && count($filters->serverNames) > 0) {
            $query->whereIn('server_name', $filters->serverNames);
        }

        if ($filters->workerId !== null) {
            $query->where('worker_id', $filters->workerId);
        }

        if ($filters->workerType !== null) {
            $query->where('worker_type', $filters->workerType);
        }

        if ($filters->tags !== null && count($filters->tags) > 0) {
            foreach ($filters->tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if ($filters->queuedAfter !== null) {
            $query->where('queued_at', '>=', $filters->queuedAfter);
        }

        if ($filters->queuedBefore !== null) {
            $query->where('queued_at', '<=', $filters->queuedBefore);
        }

        if ($filters->startedAfter !== null) {
            $query->where('started_at', '>=', $filters->startedAfter);
        }

        if ($filters->startedBefore !== null) {
            $query->where('started_at', '<=', $filters->startedBefore);
        }

        if ($filters->completedAfter !== null) {
            $query->where('completed_at', '>=', $filters->completedAfter);
        }

        if ($filters->completedBefore !== null) {
            $query->where('completed_at', '<=', $filters->completedBefore);
        }

        if ($filters->minDurationMs !== null) {
            $query->where('duration_ms', '>=', $filters->minDurationMs);
        }

        if ($filters->maxDurationMs !== null) {
            $query->where('duration_ms', '<=', $filters->maxDurationMs);
        }

        if ($filters->search !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('job_class', 'like', "%{$filters->search}%")
                    ->orWhere('display_name', 'like', "%{$filters->search}%")
                    ->orWhere('exception_message', 'like', "%{$filters->search}%")
                    ->orWhere('uuid', 'like', "%{$filters->search}%");
            });
        }
    }
}
