<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Listeners;

use Illuminate\Events\Dispatcher;
use PHPeek\LaravelQueueMetrics\Events\MetricsRecorded;
use PHPeek\LaravelQueueMonitor\Actions\Core\UpdateJobMetricsAction;

final readonly class QueueMetricsSubscriber
{
    public function __construct(
        private UpdateJobMetricsAction $action,
    ) {}

    /**
     * Handle metrics recorded events
     *
     * Reserved for future enhanced integration with laravel-queue-metrics
     */
    public function handleMetricsRecorded(MetricsRecorded $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        // Future: Enhanced metrics integration
        // For now, metrics are captured directly in job completion listeners
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe(Dispatcher $events): void
    {
        // Future: Subscribe to queue-metrics events for enhanced data
        // $events->listen(MetricsRecorded::class, [self::class, 'handleMetricsRecorded']);
    }
}
