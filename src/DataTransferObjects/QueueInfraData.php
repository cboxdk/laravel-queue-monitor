<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Per-queue infrastructure metrics sourced from the queue-metrics package.
 * Collapses to a single `available => false` key when that package is absent
 * or its query fails, exactly as the raw array did.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class QueueInfraData implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `queues` stays an array: each row is external queue-metrics telemetry
     * assembled from that package's own variable response shape.
     *
     * @param  array<int, array<string, mixed>>  $queues
     */
    public function __construct(
        public bool $available,
        public array $queues = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! (bool) ($data['available'] ?? false)) {
            return new self(false);
        }

        return new self(
            available: true,
            queues: self::rows($data['queues'] ?? null),
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
            'queues' => $this->queues,
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
