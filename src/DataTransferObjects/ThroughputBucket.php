<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One per-minute throughput bucket: job counts for a single minute.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ThroughputBucket implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $minute = '',
        public int $total = 0,
        public int $completed = 0,
        public int $failed = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $minute = $data['minute'] ?? '';

        return new self(
            minute: is_string($minute) ? $minute : (is_scalar($minute) ? (string) $minute : ''),
            total: self::intFrom($data, 'total'),
            completed: self::intFrom($data, 'completed'),
            failed: self::intFrom($data, 'failed'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'minute' => $this->minute,
            'total' => $this->total,
            'completed' => $this->completed,
            'failed' => $this->failed,
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
     * @param  array<string, mixed>  $data
     */
    private static function intFrom(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
