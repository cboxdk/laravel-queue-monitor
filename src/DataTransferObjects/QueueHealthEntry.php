<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Health snapshot for a single queue over the last hour.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class QueueHealthEntry implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $queue = '',
        public int $totalLastHour = 0,
        public int $processing = 0,
        public int $failed = 0,
        public ?float $avgDurationMs = null,
        public float $healthScore = 0.0,
        public string $status = 'healthy',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            queue: self::stringFrom($data, 'queue', ''),
            totalLastHour: self::intFrom($data, 'total_last_hour'),
            processing: self::intFrom($data, 'processing'),
            failed: self::intFrom($data, 'failed'),
            avgDurationMs: self::floatOrNull($data, 'avg_duration_ms'),
            healthScore: self::floatFrom($data, 'health_score'),
            status: self::stringFrom($data, 'status', 'healthy'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue,
            'total_last_hour' => $this->totalLastHour,
            'processing' => $this->processing,
            'failed' => $this->failed,
            'avg_duration_ms' => $this->avgDurationMs,
            'health_score' => $this->healthScore,
            'status' => $this->status,
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
    private static function stringFrom(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : $default);
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
    private static function floatFrom(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
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
