<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * SLA compliance across queues: the per-queue rows plus where the targets came
 * from (autoscale config or the built-in default).
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class SlaData implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * @param  array<int, SlaQueueCompliance>  $perQueue
     */
    public function __construct(
        public bool $available,
        public array $perQueue = [],
        public string $source = 'default',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rows = [];
        $rawRows = $data['per_queue'] ?? null;
        if (is_array($rawRows)) {
            foreach ($rawRows as $row) {
                if (is_array($row)) {
                    $normalized = [];
                    foreach ($row as $key => $value) {
                        $normalized[(string) $key] = $value;
                    }
                    $rows[] = SlaQueueCompliance::fromArray($normalized);
                }
            }
        }

        $source = $data['source'] ?? 'default';

        return new self(
            available: (bool) ($data['available'] ?? false),
            perQueue: $rows,
            source: is_scalar($source) ? (string) $source : 'default',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'per_queue' => array_map(
                static fn (SlaQueueCompliance $row): array => $row->toArray(),
                $this->perQueue,
            ),
            'source' => $this->source,
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
}
