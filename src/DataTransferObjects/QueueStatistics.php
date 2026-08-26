<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Aggregated job statistics for a single queue/connection pair.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class QueueStatistics implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $queue = '',
        public string $connection = '',
        public int $totalJobs = 0,
        public int $completed = 0,
        public int $failed = 0,
        public int $processing = 0,
        public int|float $successRate = 0,
        public ?float $avgDurationMs = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            queue: self::stringFrom($data, 'queue'),
            connection: self::stringFrom($data, 'connection'),
            totalJobs: self::intFrom($data, 'total_jobs'),
            completed: self::intFrom($data, 'completed'),
            failed: self::intFrom($data, 'failed'),
            processing: self::intFrom($data, 'processing'),
            successRate: self::rateFrom($data, 'success_rate'),
            avgDurationMs: self::floatOrNull($data, 'avg_duration_ms'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue,
            'connection' => $this->connection,
            'total_jobs' => $this->totalJobs,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'processing' => $this->processing,
            'success_rate' => $this->successRate,
            'avg_duration_ms' => $this->avgDurationMs,
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
}
