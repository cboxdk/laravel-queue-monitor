<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Live cluster state read from the autoscale package's Redis heartbeat summary:
 * fleet totals plus the per-host and per-workload rows. Built only when the
 * autoscale facade is present and reports a cluster; the service returns null
 * otherwise.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class LiveClusterState implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * The scalar totals are `mixed` and `hosts`/`workloads` stay arrays: every
     * value here is copied straight from the autoscale package's heartbeat
     * summary, an external blob the monitor only reshapes and forwards.
     *
     * @param  array<int, array<string, mixed>>  $hosts
     * @param  array<int, array<string, mixed>>  $workloads
     */
    public function __construct(
        public mixed $clusterId,
        public mixed $leaderId,
        public mixed $managerCount,
        public mixed $totalWorkers,
        public mixed $requiredWorkers,
        public mixed $totalWorkerCapacity,
        public mixed $utilizationPercent,
        public mixed $scaleSignal,
        public mixed $generatedAt,
        public array $hosts = [],
        public array $workloads = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clusterId: $data['cluster_id'] ?? null,
            leaderId: $data['leader_id'] ?? null,
            managerCount: $data['manager_count'] ?? null,
            totalWorkers: $data['total_workers'] ?? null,
            requiredWorkers: $data['required_workers'] ?? null,
            totalWorkerCapacity: $data['total_worker_capacity'] ?? null,
            utilizationPercent: $data['utilization_percent'] ?? null,
            scaleSignal: $data['scale_signal'] ?? null,
            generatedAt: $data['generated_at'] ?? null,
            hosts: self::rows($data['hosts'] ?? null),
            workloads: self::rows($data['workloads'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cluster_id' => $this->clusterId,
            'leader_id' => $this->leaderId,
            'manager_count' => $this->managerCount,
            'total_workers' => $this->totalWorkers,
            'required_workers' => $this->requiredWorkers,
            'total_worker_capacity' => $this->totalWorkerCapacity,
            'utilization_percent' => $this->utilizationPercent,
            'scale_signal' => $this->scaleSignal,
            'generated_at' => $this->generatedAt,
            'hosts' => $this->hosts,
            'workloads' => $this->workloads,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException(self::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException(self::class.' is immutable.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $normalized = [];
                foreach ($row as $key => $item) {
                    $normalized[(string) $key] = $item;
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }
}
