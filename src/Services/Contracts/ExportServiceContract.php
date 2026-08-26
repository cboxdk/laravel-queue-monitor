<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\FailedJobsReport;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\StatisticsReport;

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
     */
    public function statisticsReport(): StatisticsReport;

    /**
     * Export failed jobs report
     */
    public function failedJobsReport(int $limit = 100): FailedJobsReport;
}
