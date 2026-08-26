<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Aggregated job statistics for a single job class.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class JobClassStatistics implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $jobClass = '',
        public int $totalJobs = 0,
        public int $completed = 0,
        public int $failed = 0,
        public int|float $successRate = 0,
        public ?float $avgDurationMs = null,
        public ?int $maxDurationMs = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            jobClass: self::stringFrom($data, 'job_class'),
            totalJobs: self::intFrom($data, 'total_jobs'),
            completed: self::intFrom($data, 'completed'),
            failed: self::intFrom($data, 'failed'),
            successRate: self::rateFrom($data, 'success_rate'),
            avgDurationMs: self::floatOrNull($data, 'avg_duration_ms'),
            maxDurationMs: self::intOrNull($data, 'max_duration_ms'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job_class' => $this->jobClass,
            'total_jobs' => $this->totalJobs,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'success_rate' => $this->successRate,
            'avg_duration_ms' => $this->avgDurationMs,
            'max_duration_ms' => $this->maxDurationMs,
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

    /**
     * @param  array<string, mixed>  $data
     */
    private static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
