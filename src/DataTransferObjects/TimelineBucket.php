<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One per-minute bucket of a queue scaling timeline: job counts plus duration
 * and memory aggregates for a single minute.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class TimelineBucket implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $minute = '',
        public string $ts = '',
        public int $total = 0,
        public int $completed = 0,
        public int $failed = 0,
        public ?float $avgDurationMs = null,
        public ?int $maxDurationMs = null,
        public ?float $avgMemoryMb = null,
        public ?float $maxMemoryMb = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minute: self::stringFrom($data, 'minute'),
            ts: self::stringFrom($data, 'ts'),
            total: self::intFrom($data, 'total'),
            completed: self::intFrom($data, 'completed'),
            failed: self::intFrom($data, 'failed'),
            avgDurationMs: self::floatOrNull($data, 'avg_duration_ms'),
            maxDurationMs: self::intOrNull($data, 'max_duration_ms'),
            avgMemoryMb: self::floatOrNull($data, 'avg_memory_mb'),
            maxMemoryMb: self::floatOrNull($data, 'max_memory_mb'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'minute' => $this->minute,
            'ts' => $this->ts,
            'total' => $this->total,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'avg_duration_ms' => $this->avgDurationMs,
            'max_duration_ms' => $this->maxDurationMs,
            'avg_memory_mb' => $this->avgMemoryMb,
            'max_memory_mb' => $this->maxMemoryMb,
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
    private static function stringFrom(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function intFrom(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function floatOrNull(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
