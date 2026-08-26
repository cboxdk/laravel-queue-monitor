<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Cluster orchestration picture for the autoscale v3 tab: topology, the latest
 * scaling signal, leadership stability, and the recent event histories. Built
 * only when cluster events exist; the service returns null otherwise.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ClusterData implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `topology`, `scalingSignal` and `leadership` are single derived summaries
     * and the three histories are cluster-event dumps, all kept as arrays: they
     * are assembled from raw ClusterEvent rows whose columns vary by event type.
     *
     * @param  array<string, mixed>  $topology
     * @param  array<string, mixed>|null  $scalingSignal
     * @param  array<string, mixed>  $leadership
     * @param  array<int, array<string, mixed>>  $signalHistory
     * @param  array<int, array<string, mixed>>  $leaderHistory
     * @param  array<int, array<string, mixed>>  $managerEvents
     */
    public function __construct(
        public bool $hasCluster,
        public ?int $autoscaleVersion,
        public array $topology,
        public ?array $scalingSignal,
        public array $leadership,
        public array $signalHistory = [],
        public array $leaderHistory = [],
        public array $managerEvents = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $version = $data['autoscale_version'] ?? null;

        return new self(
            hasCluster: (bool) ($data['has_cluster'] ?? false),
            autoscaleVersion: is_numeric($version) ? (int) $version : null,
            topology: self::assoc($data['topology'] ?? null),
            scalingSignal: is_array($data['scaling_signal'] ?? null) ? self::assoc($data['scaling_signal']) : null,
            leadership: self::assoc($data['leadership'] ?? null),
            signalHistory: self::rows($data['signal_history'] ?? null),
            leaderHistory: self::rows($data['leader_history'] ?? null),
            managerEvents: self::rows($data['manager_events'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'has_cluster' => $this->hasCluster,
            'autoscale_version' => $this->autoscaleVersion,
            'topology' => $this->topology,
            'scaling_signal' => $this->scalingSignal,
            'leadership' => $this->leadership,
            'signal_history' => $this->signalHistory,
            'leader_history' => $this->leaderHistory,
            'manager_events' => $this->managerEvents,
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
                $rows[] = self::assoc($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function assoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }
}
