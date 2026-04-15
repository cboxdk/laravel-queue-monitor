<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PruneController extends Controller
{
    public function __construct(
        private readonly PruneJobsAction $pruneAction,
    ) {}

    /**
     * Prune old job records
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1',
            'max_rows' => 'sometimes|integer|min:1',
            'statuses' => 'sometimes|array',
            'statuses.*' => 'string',
        ]);

        $daysValue = $validated['days'] ?? null;
        $maxRowsValue = $validated['max_rows'] ?? null;
        $statusesValue = $validated['statuses'] ?? null;

        $days = $daysValue !== null ? (int) $daysValue : null;
        $maxRows = $maxRowsValue !== null ? (int) $maxRowsValue : null;
        /** @var array<JobStatus>|null $statuses */
        $statuses = null;

        if (is_array($statusesValue)) {
            /** @var array<JobStatus> $statuses */
            $statuses = array_map(
                fn (mixed $status): JobStatus => JobStatus::from(is_string($status) ? $status : (is_scalar($status) ? (string) $status : '')),
                $statusesValue
            );
        }

        $deleted = $this->pruneAction->execute($days, $statuses, $maxRows);

        return response()->json([
            'message' => 'Jobs pruned successfully',
            'deleted' => $deleted,
        ]);
    }
}
