<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Traits;

/**
 * Trait for jobs that want custom monitoring behavior
 */
trait MonitorsJobs
{
    /**
     * Get custom display name for monitoring
     */
    public function displayName(): string
    {
        return class_basename($this);
    }

    /**
     * Get tags for job categorization
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * Determine if this job should be monitored
     */
    public function shouldBeMonitored(): bool
    {
        return true;
    }

    /**
     * Determine if payload should be stored for this job
     */
    public function shouldStorePayload(): bool
    {
        return config('queue-monitor.storage.store_payload', true);
    }
}
