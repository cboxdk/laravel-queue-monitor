<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Middleware;

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQueueMonitorEnabled
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string $context = 'api'): Response
    {
        if (! config('queue-monitor.enabled', true)) {
            abort(503, 'Queue Monitor is currently disabled');
        }

        if ($context === 'api' && ! config('queue-monitor.api.enabled', true)) {
            abort(503, 'Queue Monitor API is currently disabled');
        }

        if ($context === 'ui' && ! config('queue-monitor.ui.enabled', true)) {
            abort(503, 'Queue Monitor Dashboard is currently disabled');
        }

        if (! LaravelQueueMonitor::check($request)) {
            abort(403);
        }

        return $next($request);
    }
}
