<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Services\Contracts\ExportServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportServiceContract $exportService,
    ) {}

    /**
     * Export jobs to CSV
     */
    public function csv(Request $request): Response
    {
        $filters = $this->filtersFromRequest($request);

        $csv = $this->exportService->toCsv($filters);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="queue-monitor-jobs-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Export jobs to JSON
     */
    public function json(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        $data = $this->exportService->toJson($filters);

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Export statistics report
     */
    public function statistics(): JsonResponse
    {
        $report = $this->exportService->statisticsReport();

        return response()->json($report);
    }

    /**
     * Export failed jobs report
     */
    public function failedJobs(): JsonResponse
    {
        $report = $this->exportService->failedJobsReport();

        return response()->json($report);
    }

    private function filtersFromRequest(Request $request): JobFilterData
    {
        $maxRows = $this->intConfig('queue-monitor.export.max_rows', 5000);
        $defaultLimit = $this->intConfig('queue-monitor.export.default_limit', 1000);

        return JobFilterData::fromRequest($request->all(), $maxRows, $defaultLimit);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
