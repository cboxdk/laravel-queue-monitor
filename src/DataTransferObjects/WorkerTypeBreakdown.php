<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Job processing broken down by worker type (Horizon, autoscale, queue worker):
 * the per-type aggregate rows and the per-queue rows behind them.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class WorkerTypeBreakdown implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * Both collections stay arrays: their rows are aggregation output whose
     * columns vary with the grouped worker types present, not a fixed shape.
     *
     * @param  array<int, array<string, mixed>>  $byType
     * @param  array<int, array<string, mixed>>  $perQueue
     */
    public function __construct(
        public array $byType = [],
        public array $perQueue = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            byType: self::rows($data['by_type'] ?? null),
            perQueue: self::rows($data['per_queue'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'by_type' => $this->byType,
            'per_queue' => $this->perQueue,
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
     * Normalise a raw list of associative rows into a typed list, dropping any
     * non-array entries so the reconstructed shape matches what is emitted.
     *
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
