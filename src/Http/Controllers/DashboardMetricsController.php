<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Http\Transformers\JobMonitorTransformer;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\AlertingServiceContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\DashboardCacheServiceContract;
use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Handles overview metrics, job listing, job detail, analytics, and legacy payload endpoints.
 *
 * Provides all data-fetching endpoints for the jobs and analytics tabs of the
 * queue monitor dashboard.
 */
class DashboardMetricsController extends Controller
{
    public function __construct(
        private readonly JobMonitorRepositoryContract $jobRepository,
        private readonly StatisticsRepositoryContract $statsRepository,
        private readonly TagRepositoryContract $tagRepository,
        private readonly DashboardCacheServiceContract $dashboardCache,
        private readonly AlertingServiceContract $alertingService,
    ) {}

    /** Time ranges (in minutes) the overview throughput chart may request. */
    private const THROUGHPUT_RANGES = [15, 60, 360, 1440];

    /**
     * Overview tab: stats, queues, alerts, recent jobs, charts
     */
    public function overview(Request $request): JsonResponse
    {
        $minutes = (int) $request->query('minutes', '60');
        if (! in_array($minutes, self::THROUGHPUT_RANGES, true)) {
            $minutes = 60;
        }

        $globalStats = $this->statsRepository->getGlobalStatistics();
        $queueHealth = $this->statsRepository->getQueueHealth();

        /** @var int $perPage */
        $perPage = config('queue-monitor.ui.per_page', 35);

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        $recentJobs = $this->jobRepository->getRecentJobs($perPage)
            ->map(fn ($job) => JobMonitorTransformer::toListArray($job, $sensitiveKeys));

        $chartData = $this->statsRepository->getJobClassStatistics();

        $alerts = $this->alertingService->checkAlertConditions();

        return response()->json([
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'alerts' => $alerts,
            'recent_jobs' => $recentJobs,
            'charts' => [
                'distribution' => $chartData,
                'throughput' => $this->statsRepository->getThroughputByMinute($minutes),
            ],
            'horizon_available' => class_exists('Laravel\Horizon\Horizon'),
        ]);
    }

    /**
     * Jobs tab: paginated jobs with filters
     */
    public function jobs(Request $request): JsonResponse
    {
        $filters = JobFilterData::fromRequest($request->all());

        $jobs = $this->jobRepository->query($filters);
        $total = $this->jobRepository->count($filters);

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        $data = $jobs->map(fn ($job) => JobMonitorTransformer::toListArray($job, $sensitiveKeys));

        // Provide distinct queue names for the filter dropdown (cached to avoid full table scan)
        $availableQueues = Cache::remember($this->dashboardCache->scopedKey('available_queues'), 60, fn () => JobMonitor::query()
            ->distinct()
            ->whereNotNull('queue')
            ->pluck('queue')
            ->sort()
            ->values()
            ->all()
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'limit' => $filters->limit,
                'offset' => $filters->offset,
                'available_queues' => $availableQueues,
            ],
        ]);
    }

    /**
     * Job slide-over: full detail with redacted payload, exception, retry chain
     */
    public function jobDetail(string $uuid): JsonResponse
    {
        $job = $this->jobRepository->findByUuid($uuid);

        if ($job === null) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        $payload = $job->payload !== null
            ? PayloadRedactor::redact($job->payload, $sensitiveKeys)
            : null;

        $retryChain = $this->jobRepository->getRetryChain($uuid)
            ->map(fn ($retryJob) => JobMonitorTransformer::toRetryChainArray($retryJob, $uuid, $sensitiveKeys));

        return response()->json([
            'job' => JobMonitorTransformer::toDetailArray($job, $sensitiveKeys),
            'payload' => $payload,
            'exception' => $job->isFailed() ? [
                'class' => $job->exception_class,
                'short_class' => $job->getShortExceptionClass(),
                'message' => PayloadRedactor::redactString($job->exception_message, $sensitiveKeys),
                'trace' => PayloadRedactor::redactTrace($job->exception_trace, $sensitiveKeys),
            ] : null,
            'retry_chain' => $retryChain,
        ]);
    }

    /**
     * Analytics tab: job class stats, queue stats, server stats, failure patterns, tag stats
     */
    public function analytics(): JsonResponse
    {
        $jobClasses = $this->statsRepository->getJobClassStatistics();
        $queues = $this->statsRepository->getQueueStatistics();
        $servers = $this->statsRepository->getServerStatistics();
        $failurePatterns = $this->statsRepository->getFailurePatterns();
        $tags = $this->tagRepository->getTagStatistics();

        return response()->json([
            'job_classes' => $jobClasses,
            'queues' => $queues,
            'servers' => $servers,
            'failure_patterns' => $failurePatterns,
            'tags' => $tags,
        ]);
    }

    /**
     * Get redacted payload for a specific job (legacy endpoint)
     */
    public function payload(string $uuid): JsonResponse
    {
        $job = $this->jobRepository->findByUuid($uuid);

        if ($job === null || empty($job->payload)) {
            return response()->json(['payload' => []]);
        }

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        return response()->json([
            'payload' => PayloadRedactor::redact($job->payload, $sensitiveKeys),
            'exception' => PayloadRedactor::redactTrace($job->exception_trace, $sensitiveKeys),
        ]);
    }
}
