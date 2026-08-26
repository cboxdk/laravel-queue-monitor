<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A single alert row: its severity, a human-readable message, and the count of
 * affected jobs. Alerts are returned keyed by alert name (stuck_jobs,
 * high_error_rate, and so on).
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class AlertEntry implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(
        public string $severity,
        public string $message,
        public int $count,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $severity = $data['severity'] ?? '';
        $message = $data['message'] ?? '';
        $count = $data['count'] ?? 0;

        return new self(
            severity: is_scalar($severity) ? (string) $severity : '',
            message: is_scalar($message) ? (string) $message : '',
            count: is_numeric($count) ? (int) $count : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'message' => $this->message,
            'count' => $this->count,
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
