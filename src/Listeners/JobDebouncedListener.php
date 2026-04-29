<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

final class JobDebouncedListener
{
    public function handle(object $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $jobId = property_exists($event, 'id') ? (string) $event->id : null;

            if ($jobId === null) {
                return;
            }

            $monitor = JobMonitor::where('job_id', $jobId)
                ->where('status', JobStatus::QUEUED->value)
                ->orderByDesc('attempt')
                ->first();

            if ($monitor === null) {
                return;
            }

            $monitor->update([
                'status' => JobStatus::DEBOUNCED->value,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
