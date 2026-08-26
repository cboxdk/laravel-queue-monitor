<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

interface AlertingServiceContract
{
    /**
     * Check for alert conditions
     *
     * @return array<string, array{severity: string, message: string, count: int}>
     */
    public function checkAlertConditions(): array;

    /**
     * Get critical alerts only
     *
     * @return array<string, array{severity: string, message: string, count: int}>
     */
    public function getCriticalAlerts(): array;

    /**
     * Check if system requires immediate attention
     */
    public function requiresAttention(): bool;
}
