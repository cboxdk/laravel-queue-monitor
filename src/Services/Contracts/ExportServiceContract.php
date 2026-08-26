<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;

interface ExportServiceContract
{
    /**
     * Export jobs to CSV format
     */
    public function toCsv(JobFilterData $filters): string;

    /**
     * Export jobs to JSON format
     *
     * @return array<int, array<string, mixed>>
     */
    public function toJson(JobFilterData $filters): array;

    /**
     * Export statistics report
     *
     * @return array<string, mixed>
     */
    public function statisticsReport(): array;

    /**
     * Export failed jobs report
     *
     * @return array<string, mixed>
     */
    public function failedJobsReport(int $limit = 100): array;
}
