<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\HealthCheckResult;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ReadinessResult;
use Illuminate\Database\Connection;

interface HealthCheckServiceContract
{
    /**
     * Perform comprehensive health check
     */
    public function check(): HealthCheckResult;

    /**
     * Check production readiness configuration.
     */
    public function readiness(): ReadinessResult;

    /**
     * Resolve the physical name of the monitor jobs table on a connection.
     *
     * Schema and query builders apply the connection's own table prefix
     * (database.connections.*.prefix) automatically, but raw lookups against
     * information_schema do not — so it must be prepended to the package's
     * configured table prefix here.
     */
    public function monitorJobsTable(Connection $database): string;

    /**
     * Get overall health score (0-100)
     */
    public function getHealthScore(): int;

    /**
     * Compute the health score (0-100) from an already-run set of checks.
     *
     * @param  array<string, array<string, mixed>>  $checks
     */
    public function scoreFromChecks(array $checks): int;

    /**
     * Check if system is healthy
     */
    public function isHealthy(): bool;
}
