<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Per-minute scaling timeline for one queue: the filled per-minute buckets,
 * the live processing/waiting/delayed counts, and the worker memory limit.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class QueueTimeline implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * @param  array<int, TimelineBucket>  $buckets
     * @param  array{processing: int, waiting: int, delayed: int}  $live
     */
    public function __construct(
        public array $buckets = [],
        public array $live = ['processing' => 0, 'waiting' => 0, 'delayed' => 0],
        public ?float $memoryLimitMb = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $bucketsRaw = $data['buckets'] ?? [];
        $buckets = [];
        if (is_array($bucketsRaw)) {
            foreach ($bucketsRaw as $bucket) {
                if (is_array($bucket)) {
                    $normalized = [];
                    foreach ($bucket as $key => $value) {
                        $normalized[(string) $key] = $value;
                    }
                    $buckets[] = TimelineBucket::fromArray($normalized);
                }
            }
        }

        $liveRaw = $data['live'] ?? [];
        $live = [
            'processing' => self::intFrom($liveRaw, 'processing'),
            'waiting' => self::intFrom($liveRaw, 'waiting'),
            'delayed' => self::intFrom($liveRaw, 'delayed'),
        ];

        $memoryLimit = $data['memory_limit_mb'] ?? null;

        return new self(
            buckets: $buckets,
            live: $live,
            memoryLimitMb: is_numeric($memoryLimit) ? (float) $memoryLimit : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'buckets' => array_map(
                static fn (TimelineBucket $bucket): array => $bucket->toArray(),
                $this->buckets,
            ),
            'live' => $this->live,
            'memory_limit_mb' => $this->memoryLimitMb,
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

    private static function intFrom(mixed $data, string $key): int
    {
        $value = is_array($data) ? ($data[$key] ?? null) : null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
