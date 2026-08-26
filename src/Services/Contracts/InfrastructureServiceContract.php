<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

interface InfrastructureServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function getWorkerData(): array;

    /**
     * Breakdown of job processing by worker type (horizon, autoscale, queue_work) per queue.
     * Shows which manager handles which queue and relative workload distribution.
     *
     * @return array<string, mixed>
     */
    public function getWorkerTypeBreakdown(): array;

    /**
     * @return array<string, mixed>
     */
    public function getQueueInfraData(): array;

    /**
     * SLA compliance per queue, using autoscale config targets when available.
     * Falls back to a default 30s target if autoscale is not configured.
     *
     * @return array<string, mixed>
     */
    public function getSlaData(): array;

    /**
     * @return array<string, mixed>
     */
    public function getScalingData(): array;

    /**
     * @return array<string, mixed>
     */
    public function getCapacityData(): array;

    /**
     * Cluster orchestration data for v3 autoscale. Returns null when not available.
     *
     * @return array<string, mixed>|null
     */
    public function getClusterData(): ?array;

    /**
     * Live cluster state from autoscale v3 Redis heartbeats.
     * Returns real-time per-host resource data when available.
     *
     * @return array<string, mixed>|null
     */
    public function getLiveClusterState(): ?array;

    /**
     * Scaling decisions for one queue in chronological order, for the
     * dashboard's scaling timeline chart, plus the worker range observed
     * in the window.
     *
     * @return array{
     *     events: array<int, array{t: string, current_workers: int, target_workers: int, action: string, reason: string}>,
     *     worker_range: array{min: int|null, max: int|null}
     * }
     */
    public function getScalingTimeline(string $queue, int $minutes = 60): array;
}
