<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\CapacityData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ClusterData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\LiveClusterState;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueInfraData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ScalingData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ScalingTimeline;
use Cbox\LaravelQueueMonitor\DataTransferObjects\SlaData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerTypeBreakdown;

interface InfrastructureServiceContract
{
    public function getWorkerData(): WorkerData;

    /**
     * Breakdown of job processing by worker type (horizon, autoscale, queue_work) per queue.
     * Shows which manager handles which queue and relative workload distribution.
     */
    public function getWorkerTypeBreakdown(): WorkerTypeBreakdown;

    public function getQueueInfraData(): QueueInfraData;

    /**
     * SLA compliance per queue, using autoscale config targets when available.
     * Falls back to a default 30s target if autoscale is not configured.
     */
    public function getSlaData(): SlaData;

    public function getScalingData(): ScalingData;

    public function getCapacityData(): CapacityData;

    /**
     * Cluster orchestration data for v3 autoscale. Returns null when not available.
     */
    public function getClusterData(): ?ClusterData;

    /**
     * Live cluster state from autoscale v3 Redis heartbeats.
     * Returns real-time per-host resource data when available.
     */
    public function getLiveClusterState(): ?LiveClusterState;

    /**
     * Scaling decisions for one queue in chronological order, for the
     * dashboard's scaling timeline chart, plus the worker range observed
     * in the window.
     */
    public function getScalingTimeline(string $queue, int $minutes = 60): ScalingTimeline;
}
