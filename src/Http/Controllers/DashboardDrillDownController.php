<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Eloquent\EloquentStatisticsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Handles drill-down detail panels for queues, servers, and job classes.
 *
 * Provides deep-dive statistics, throughput, recent jobs, and failure patterns
 * for a specific entity in the queue monitor dashboard.
 */
class DashboardDrillDownController extends Controller
{
    public function __construct(
        private readonly StatisticsRepositoryContract $statsRepository,
    ) {}

    /**
     * Drill-down detail for a specific entity (queue, server, job_class)
     * GET /queue-monitor/drill-down?type=queue&value=payments
     * GET /queue-monitor/drill-down?type=server&value=prod-01
     * GET /queue-monitor/drill-down?type=job_class&value=App\Jobs\ProcessPayment
     */
    public function drillDown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:queue,server,job_class',
            'value' => 'required|string',
        ]);

        /** @var string $type */
        $type = $validated['type'];
        /** @var string $value */
        $value = $validated['value'];
        // Cache drill-down for 30s to prevent expensive repeated queries
        $cacheKey = 'queue_monitor:drill_down:'.md5($type.':'.$value);
        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        // Map type to database column
        $column = match ($type) {
            'queue' => 'queue',
            'server' => 'server_name',
            'job_class' => 'job_class',
            default => 'queue',
        };

        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');
        $table = $prefix.'jobs';

        // Stats
        $completedValue = JobStatus::COMPLETED->value;
        $failedValue = JobStatus::FAILED->value;
        $timeoutValue = JobStatus::TIMEOUT->value;

        $statsRow = DB::table($table)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', [$completedValue])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as failed', [$failedValue, $timeoutValue])
            ->selectRaw('AVG(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as avg_duration_ms')
            ->selectRaw('MAX(duration_ms) as max_duration_ms')
            ->selectRaw('MIN(CASE WHEN duration_ms IS NOT NULL THEN duration_ms END) as min_duration_ms')
            ->selectRaw('AVG(CASE WHEN memory_peak_mb IS NOT NULL THEN memory_peak_mb END) as avg_memory_mb')
            ->where($column, $value)
            ->first();

        $total = $statsRow ? (int) $statsRow->total : 0;
        $completed = $statsRow ? (int) $statsRow->completed : 0;
        $failed = $statsRow ? (int) $statsRow->failed : 0;
        $finished = $completed + $failed;

        // Percentiles via sampled duration values (bounded to 1000 rows for memory safety)
        $durations = DB::table($table)
            ->where($column, $value)
            ->whereNotNull('duration_ms')
            ->orderBy('duration_ms')
            ->limit(1000)
            ->pluck('duration_ms')
            ->map(fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)
            ->values()
            ->all();

        $p50 = $this->percentile($durations, 50);
        $p95 = $this->percentile($durations, 95);
        $p99 = $this->percentile($durations, 99);

        $stats = [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $finished > 0 ? round(($completed / $finished) * 100, 2) : 0,
            'avg_duration_ms' => $statsRow && $statsRow->avg_duration_ms !== null ? round((float) $statsRow->avg_duration_ms, 2) : null,
            'max_duration_ms' => $statsRow && $statsRow->max_duration_ms !== null ? (int) $statsRow->max_duration_ms : null,
            'min_duration_ms' => $statsRow && $statsRow->min_duration_ms !== null ? (int) $statsRow->min_duration_ms : null,
            'avg_memory_mb' => $statsRow && $statsRow->avg_memory_mb !== null ? round((float) $statsRow->avg_memory_mb, 2) : null,
            'p50_duration_ms' => $p50,
            'p95_duration_ms' => $p95,
            'p99_duration_ms' => $p99,
        ];

        // Throughput (per-minute for this entity)
        /** @var EloquentStatisticsRepository $statsRepo */
        $statsRepo = $this->statsRepository;
        $throughput = $statsRepo->computeThroughputByMinute(60, [$column => $value]);

        // Recent jobs (last 20) with identifiable summary from payload
        $recentJobs = DB::table($table)
            ->where($column, $value)
            ->orderByDesc('queued_at')
            ->limit(20)
            ->get()
            ->map(function ($job) {
                $summary = $this->extractJobSummary($job->payload, $job->display_name);

                return [
                    'uuid' => $job->uuid,
                    'job_class' => class_basename($job->job_class),
                    'summary' => $summary,
                    'queue' => $job->queue,
                    'status' => $job->status,
                    'attempt' => $job->attempt,
                    'server' => $job->server_name,
                    'duration_ms' => $job->duration_ms !== null ? (int) $job->duration_ms : null,
                    'duration' => $job->duration_ms !== null ? number_format((int) $job->duration_ms).'ms' : '-',
                    'queued_at' => $job->queued_at,
                    'error' => $job->exception_message,
                ];
            })
            ->values()
            ->all();

        // Failure patterns (top exception classes for this entity)
        $failurePatterns = DB::table($table)
            ->select([
                'exception_class',
                DB::raw('COUNT(*) as count'),
            ])
            ->where($column, $value)
            ->whereNotNull('exception_class')
            ->groupBy('exception_class')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'exception_class' => $row->exception_class,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $result = [
            'entity' => [
                'type' => $type,
                'value' => $value,
            ],
            'stats' => $stats,
            'throughput' => $throughput,
            'recent_jobs' => $recentJobs,
            'failure_patterns' => $failurePatterns,
        ];

        cache()->put($cacheKey, $result, 30);

        return response()->json($result);
    }

    /**
     * Calculate percentile from a sorted array of values
     *
     * @param  array<int, int>  $sortedValues
     */
    private function percentile(array $sortedValues, int $percentile): ?int
    {
        if (count($sortedValues) === 0) {
            return null;
        }

        $index = (int) ceil(($percentile / 100) * count($sortedValues)) - 1;
        $index = max(0, min($index, count($sortedValues) - 1));

        return $sortedValues[$index];
    }

    /**
     * Extract a short human-readable summary from job payload.
     * Parses serialized PHP constructor params like Horizon does.
     * Returns something like "reportType: daily" or "user_id: 42, amount: 500"
     */
    private function extractJobSummary(?string $payloadJson, ?string $displayName): ?string
    {
        if ($payloadJson === null) {
            return $displayName;
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return $displayName;
        }

        $data = $payload['data'] ?? null;
        $command = is_array($data) ? ($data['command'] ?? null) : null;
        if (! is_string($command)) {
            return $displayName;
        }

        // Parse PHP serialized string for property names and scalar values
        // Matches: s:N:"name";s:N:"value" or s:N:"name";i:N or s:N:"name";d:N.N or s:N:"name";b:0/1
        $props = [];
        $skip = ['queue', 'connection', 'delay', 'middleware', 'chained', 'afterCommit',
            'chainConnection', 'chainQueue', 'chainCatchCallbacks', 'backoff',
            'maxExceptions', 'failOnTimeout', 'tries', 'timeout', 'uniqueFor',
            'uniqueId', 'uniqueVia'];

        // Match property name followed by its value
        if (preg_match_all('/s:\d+:"([^"]+)";(s:\d+:"([^"]*)"|i:(\d+)|d:([\d.]+)|b:([01])|N;)/', $command, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = $match[1];
                if (in_array($name, $skip, true)) {
                    continue;
                }

                $value = $match[3] ?? $match[4] ?? $match[5] ?? null;
                if ($value === null && isset($match[6])) {
                    $value = $match[6] === '1' ? 'true' : 'false';
                }
                if ($value === null && str_contains($match[2], 'N;')) {
                    $value = 'null';
                }

                if ($value !== null && $value !== '') {
                    // Truncate long values
                    if (strlen($value) > 30) {
                        $value = substr($value, 0, 27).'...';
                    }
                    $props[] = "{$name}: {$value}";
                }

                // Max 3 params for readability
                if (count($props) >= 3) {
                    break;
                }
            }
        }

        return ! empty($props) ? implode(', ', $props) : $displayName;
    }
}
