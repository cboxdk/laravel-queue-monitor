<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

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
                $this->escapeCsvValue($job->uuid),
                $this->escapeCsvValue($job->job_class),
                $this->escapeCsvValue($job->queue),
                $this->escapeCsvValue($job->status->value),
                $job->attempt,
                $job->duration_ms ?? '',
                $job->memory_peak_mb ?? '',
                $this->escapeCsvValue($job->queued_at->toIso8601String()),
                $this->escapeCsvValue($job->started_at?->toIso8601String() ?? ''),
                $this->escapeCsvValue($job->completed_at?->toIso8601String() ?? ''),
                $this->escapeCsvValue($job->exception_class ?? ''),
            ])."\n";
        }

        return $csv;
    }

    /**
     * Escape a value for safe CSV output
     *
     * Prevents CSV injection attacks by:
     * 1. Quoting values that contain special characters
     * 2. Escaping embedded quotes
     * 3. Prefixing formula characters with a single quote to prevent execution
     */
    private function escapeCsvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Characters that could trigger formula execution in spreadsheet software
        $formulaChars = ['=', '+', '-', '@', "\t", "\r", "\n", '|'];

        // Prefix formula characters with a single quote to prevent execution
        $firstChar = mb_substr($value, 0, 1);
        if (in_array($firstChar, $formulaChars, true)) {
            $value = "'".$value;
        }

        // Escape double quotes by doubling them
        $value = str_replace('"', '""', $value);

        // Wrap in quotes if contains comma, quote, newline, or starts with formula char
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "'")) {
            return '"'.$value.'"';
        }

        return $value;
    }

    /**
     * Export jobs to JSON format
     *
     * @return array<int, array<string, mixed>>
     */
    public function toJson(JobFilterData $filters): array
    {
        $jobs = $this->repository->query($filters);

        /** @var array<int, array<string, mixed>> $result */
        $result = $jobs->map(function ($job) {
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
        })->values()->all();

        return $result;
    }

    /**
     * Export statistics report
     *
     * @return array<string, mixed>
     */
    public function statisticsReport(): array
    {
        $stats = app(\Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateJobStatisticsAction::class)
            ->execute();

        $serverStats = app(\Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateServerStatisticsAction::class)
            ->execute();

        $queueHealth = app(\Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateQueueHealthAction::class)
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
