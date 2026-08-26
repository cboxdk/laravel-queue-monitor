<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Global, cross-queue job statistics for the overview and the statistics API.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class GlobalStatistics implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public int $total = 0,
        public int $completed = 0,
        public int $failed = 0,
        public int $timeout = 0,
        public int $processing = 0,
        public int $queueBacklog = 0,
        public int|float $successRate = 0,
        public int|float $failureRate = 0,
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
            total: self::intFrom($data, 'total'),
            completed: self::intFrom($data, 'completed'),
            failed: self::intFrom($data, 'failed'),
            timeout: self::intFrom($data, 'timeout'),
            processing: self::intFrom($data, 'processing'),
            queueBacklog: self::intFrom($data, 'queue_backlog'),
            successRate: self::rateFrom($data, 'success_rate'),
            failureRate: self::rateFrom($data, 'failure_rate'),
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
            'total' => $this->total,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'timeout' => $this->timeout,
            'processing' => $this->processing,
            'queue_backlog' => $this->queueBacklog,
            'success_rate' => $this->successRate,
            'failure_rate' => $this->failureRate,
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
    private static function intFrom(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Rates are int when there is nothing to divide (a bare 0) and float once
     * computed, so both cases are preserved verbatim for byte-identical output.
     *
     * @param  array<string, mixed>  $data
     */
    private static function rateFrom(array $data, string $key): int|float
    {
        $value = $data[$key] ?? 0;

        return is_int($value) || is_float($value) ? $value : 0;
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
