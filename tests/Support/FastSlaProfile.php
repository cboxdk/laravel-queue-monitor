<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

/**
 * Stands in for a laravel-queue-autoscale profile (ProfileContract), which
 * this package reads by shape only: a class with resolve(): array.
 */
class FastSlaProfile
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        return [
            'sla' => ['target_seconds' => 5, 'percentile' => 95, 'window_seconds' => 300, 'min_samples' => 20],
            'workers' => ['min' => 1, 'max' => 10],
        ];
    }
}
