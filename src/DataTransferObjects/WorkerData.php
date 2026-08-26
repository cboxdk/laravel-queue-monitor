<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Horizon worker utilization: process totals and the live supervisor/workload
 * blobs. Collapses to a single `available => false` key when Horizon is absent
 * or unreadable, exactly as the raw array did.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class WorkerData implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `supervisors` and `workload` stay arrays: they are Horizon telemetry blobs
     * whose shape the monitor does not own, and `jobsPerMinute` is whatever the
     * metrics repository returns for the current driver.
     *
     * @param  array<int, array<string, mixed>>  $supervisors
     * @param  array<int, array<string, mixed>>  $workload
     */
    public function __construct(
        public bool $available,
        public ?int $totalProcesses = null,
        public array $supervisors = [],
        public array $workload = [],
        public mixed $jobsPerMinute = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! (bool) ($data['available'] ?? false)) {
            return new self(false);
        }

        $totalProcesses = $data['total_processes'] ?? null;

        return new self(
            available: true,
            totalProcesses: is_numeric($totalProcesses) ? (int) $totalProcesses : null,
            supervisors: self::rows($data['supervisors'] ?? null),
            workload: self::rows($data['workload'] ?? null),
            jobsPerMinute: $data['jobs_per_minute'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! $this->available) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'total_processes' => $this->totalProcesses,
            'supervisors' => $this->supervisors,
            'workload' => $this->workload,
            'jobs_per_minute' => $this->jobsPerMinute,
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
