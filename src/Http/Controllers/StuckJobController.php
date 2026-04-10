<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Actions\ResolveStuckJobAction;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StuckJobController extends Controller
{
    public function __construct(
        private readonly ResolveStuckJobAction $resolveAction,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,retry',
            'uuids' => 'required|array|min:1',
            'uuids.*' => 'required|string',
        ]);

        /** @var list<string> $uuids */
        $uuids = $validated['uuids'];
        /** @var string $action */
        $action = $validated['action'];

        $result = $this->resolveAction->execute($uuids, $action);

        return response()->json([
            'message' => match ($action) {
                'delete' => "{$result['resolved']} stuck job(s) deleted",
                'retry' => "{$result['resolved']} stuck job(s) resolved, {$result['replayed']} retried",
                default => "{$result['resolved']} stuck job(s) resolved",
            },
            ...$result,
        ]);
    }

    public function resolveAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,retry',
        ]);

        /** @var list<string> $stuckJobs */
        $stuckJobs = QueryBuilderHelper::stuck(30)->pluck('uuid')->toArray();

        if (empty($stuckJobs)) {
            return response()->json(['message' => 'No stuck jobs found', 'resolved' => 0, 'replayed' => 0, 'errors' => []]);
        }

        /** @var string $action */
        $action = $validated['action'];

        $result = $this->resolveAction->execute($stuckJobs, $action);

        return response()->json([
            'message' => match ($action) {
                'delete' => "{$result['resolved']} stuck job(s) deleted",
                'retry' => "{$result['resolved']} stuck job(s) resolved, {$result['replayed']} retried",
                default => "{$result['resolved']} stuck job(s) resolved",
            },
            ...$result,
        ]);
    }
}
