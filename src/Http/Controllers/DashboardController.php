<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Support\DashboardAssets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Serves the queue monitor dashboard view.
 *
 * All data-fetching endpoints have been extracted to dedicated controllers:
 *
 * @see DashboardMetricsController  Overview, jobs, job detail, analytics, payload
 * @see DashboardHealthController   Health checks, infrastructure monitoring
 * @see DashboardDrillDownController Drill-down panels for queues, servers, job classes
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard view
     */
    public function index(): View
    {
        return $this->render();
    }

    /**
     * Display the dashboard with a specific job pre-selected (deep-link)
     */
    public function show(string $uuid): View
    {
        return $this->render(jobUuid: $uuid);
    }

    /**
     * Display the dashboard with a drill-down pre-selected (deep-link)
     */
    public function drillDownView(Request $request): View
    {
        $routeName = $request->route()?->getName() ?? '';
        $type = match (true) {
            str_contains($routeName, 'queue.view') => 'queue',
            str_contains($routeName, 'server.view') => 'server',
            str_contains($routeName, 'class.view') => 'job_class',
            default => 'queue',
        };

        $raw = $request->route()?->parameter('queue')
            ?? $request->route()?->parameter('server')
            ?? $request->route()?->parameter('jobClass');

        $value = is_string($raw) ? $raw : '';

        return $this->render(drillDownType: $type, drillDownValue: $value);
    }

    /**
     * Stream a bundled dashboard asset (CSS/JS) with a long, immutable cache so
     * the ~1 MB chart library is fetched once and reused, rather than re-inlined
     * into every dashboard response. The file name is whitelisted against the
     * known bundle so this cannot read arbitrary paths.
     */
    public function asset(string $file, DashboardAssets $assets): Response
    {
        $resolved = $assets->bundledAsset($file);

        if ($resolved === null) {
            abort(404);
        }

        [$contents, $contentType] = $resolved;

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"'.substr(md5($contents), 0, 16).'"',
        ]);
    }

    private function render(
        ?string $jobUuid = null,
        ?string $drillDownType = null,
        ?string $drillDownValue = null,
    ): View {
        /** @var view-string $view */
        $view = 'queue-monitor::web.dashboard';

        return view($view, [
            'jobUuid' => $jobUuid,
            'drillDownType' => $drillDownType,
            'drillDownValue' => $drillDownValue,
        ]);
    }
}
