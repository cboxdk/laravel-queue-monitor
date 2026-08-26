<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Illuminate\Database\Connection;

interface HealthCheckServiceContract
{
    /**
     * Perform comprehensive health check
     *
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function check(): array;

    /**
     * Check production readiness configuration.
     *
     * @return array{status: string, checks: array<string, array<string, mixed>>, timestamp: string}
     */
    public function readiness(): array;

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
     * Check if system is healthy
     */
    public function isHealthy(): bool;
}
