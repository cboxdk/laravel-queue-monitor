<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureQueueMonitorEnabled
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('queue-monitor.enabled', true)) {
            abort(503, 'Queue Monitor is currently disabled');
        }

        if (! config('queue-monitor.api.enabled', true)) {
            abort(503, 'Queue Monitor API is currently disabled');
        }

        return $next($request);
    }
}
