<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

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
        /** @var view-string $view */
        $view = 'queue-monitor::web.dashboard';

        return view($view);
    }
}
