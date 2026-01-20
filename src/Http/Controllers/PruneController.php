<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

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
        $daysValue = $request->input('days');
        $statusesValue = $request->input('statuses');

        $days = is_numeric($daysValue) ? (int) $daysValue : null;
        $statuses = null;

        if ($statusesValue !== null && is_array($statusesValue)) {
            $statuses = array_map(
                fn (mixed $status): JobStatus => JobStatus::from(is_string($status) ? $status : (string) $status),
                $statusesValue
            );
        }

        $deleted = $this->pruneAction->execute($days, $statuses);

        return response()->json([
            'message' => 'Jobs pruned successfully',
            'deleted' => $deleted,
        ]);
    }
}
