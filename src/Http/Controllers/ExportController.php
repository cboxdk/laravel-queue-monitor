<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\Services\ExportService;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Export jobs to CSV
     */
    public function csv(Request $request): Response
    {
        $filters = JobFilterData::fromRequest($request->all());

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
        $filters = JobFilterData::fromRequest($request->all());

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
}
