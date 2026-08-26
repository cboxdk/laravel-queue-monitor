<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\AlertEntry;

interface AlertingServiceContract
{
    /**
     * Check for alert conditions
     *
     * @return array<string, AlertEntry>
     */
    public function checkAlertConditions(): array;

    /**
     * Get critical alerts only
     *
     * @return array<string, AlertEntry>
     */
    public function getCriticalAlerts(): array;

    /**
     * Check if system requires immediate attention
     */
    public function requiresAttention(): bool;
}
