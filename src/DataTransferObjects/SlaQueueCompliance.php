<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One queue's SLA compliance over the window: its pickup-time target and how
 * many jobs met it. A fixed, domain-owned shape, so it is a value object rather
 * than a bare array row.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class SlaQueueCompliance implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $queue,
        public int $targetSeconds,
        public float $compliance,
        public int $total,
        public int $within,
        public int $breached,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $queue = $data['queue'] ?? '';
        $compliance = $data['compliance'] ?? 0;

        return new self(
            queue: is_scalar($queue) ? (string) $queue : '',
            targetSeconds: self::intFrom($data, 'target_seconds'),
            compliance: is_numeric($compliance) ? (float) $compliance : 0.0,
            total: self::intFrom($data, 'total'),
            within: self::intFrom($data, 'within'),
            breached: self::intFrom($data, 'breached'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue,
            'target_seconds' => $this->targetSeconds,
            'compliance' => $this->compliance,
            'total' => $this->total,
            'within' => $this->within,
            'breached' => $this->breached,
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
