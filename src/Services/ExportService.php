<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Services;

use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class ExportService
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Export jobs to CSV format
     */
    public function toCsv(JobFilterData $filters): string
    {
        $jobs = $this->repository->query($filters);

        $csv = "UUID,Job Class,Queue,Status,Attempt,Duration (ms),Memory (MB),Queued At,Started At,Completed At,Exception\n";

        foreach ($jobs as $job) {
            $csv .= implode(',', [
                $job->uuid,
                '"'.$job->job_class.'"',
                $job->queue,
                $job->status->value,
                $job->attempt,
                $job->duration_ms ?? '',
                $job->memory_peak_mb ?? '',
                $job->queued_at->toIso8601String(),
                $job->started_at?->toIso8601String() ?? '',
                $job->completed_at?->toIso8601String() ?? '',
                $job->exception_class ? '"'.$job->exception_class.'"' : '',
            ])."\n";
        }

        return $csv;
    }

    /**
     * Export jobs to JSON format
     *
     * @return array<int, array<string, mixed>>
     */
    public function toJson(JobFilterData $filters): array
    {
        $jobs = $this->repository->query($filters);

        return $jobs->map(function ($job) {
            return [
                'uuid' => $job->uuid,
                'job_class' => $job->job_class,
                'display_name' => $job->display_name,
                'queue' => $job->queue,
                'connection' => $job->connection,
                'status' => $job->status->value,
                'attempt' => $job->attempt,
                'max_attempts' => $job->max_attempts,
                'server_name' => $job->server_name,
                'worker_id' => $job->worker_id,
                'worker_type' => $job->worker_type->value,
                'metrics' => [
                    'cpu_time_ms' => $job->cpu_time_ms,
                    'memory_peak_mb' => $job->memory_peak_mb,
                    'file_descriptors' => $job->file_descriptors,
                    'duration_ms' => $job->duration_ms,
                ],
                'exception' => $job->exception_class ? [
                    'class' => $job->exception_class,
                    'message' => $job->exception_message,
                ] : null,
                'tags' => $job->tags,
                'timestamps' => [
                    'queued_at' => $job->queued_at->toIso8601String(),
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                ],
            ];
        })->toArray();
    }

    /**
     * Export statistics report
     *
     * @return array<string, mixed>
     */
    public function statisticsReport(): array
    {
        $stats = app(\PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateJobStatisticsAction::class)
            ->execute();

        $serverStats = app(\PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateServerStatisticsAction::class)
            ->execute();

        $queueHealth = app(\PHPeek\LaravelQueueMonitor\Actions\Analytics\CalculateQueueHealthAction::class)
            ->execute();

        return [
            'generated_at' => now()->toIso8601String(),
            'global' => $stats,
            'servers' => $serverStats,
            'queue_health' => $queueHealth,
        ];
    }

    /**
     * Export failed jobs report
     *
     * @return array<string, mixed>
     */
    public function failedJobsReport(int $limit = 100): array
    {
        $failed = $this->repository->getFailedJobs($limit);

        $byException = $failed->groupBy('exception_class')
            ->map(fn ($jobs) => [
                'count' => $jobs->count(),
                'jobs' => $jobs->pluck('uuid')->toArray(),
            ])
            ->toArray();

        $byQueue = $failed->groupBy('queue')
            ->map(fn ($jobs) => $jobs->count())
            ->toArray();

        return [
            'generated_at' => now()->toIso8601String(),
            'total_failed' => $failed->count(),
            'by_exception' => $byException,
            'by_queue' => $byQueue,
            'recent_failures' => $failed->take(10)->map(fn ($job) => [
                'uuid' => $job->uuid,
                'job_class' => $job->job_class,
                'exception' => $job->exception_class,
                'failed_at' => $job->completed_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
