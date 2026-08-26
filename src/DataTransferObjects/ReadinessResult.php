<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Outcome of the production-readiness check: the overall status, the individual
 * readiness checks keyed by name, and when it ran.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ReadinessResult implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `checks` stays a keyed map of check rows: each row carries a healthy flag
     * and a message, plus an optional `details` payload whose contents differ
     * per check (middleware lists, retention limits, timeout pairs).
     *
     * @param  array<string, array<string, mixed>>  $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
        public string $timestamp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? '';
        $timestamp = $data['timestamp'] ?? '';

        return new self(
            status: is_scalar($status) ? (string) $status : '',
            checks: self::map($data['checks'] ?? null),
            timestamp: is_scalar($timestamp) ? (string) $timestamp : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checks' => $this->checks,
            'timestamp' => $this->timestamp,
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
     * @return array<string, array<string, mixed>>
     */
    private static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $row) {
            if (is_array($row)) {
                $normalized = [];
                foreach ($row as $rowKey => $item) {
                    $normalized[(string) $rowKey] = $item;
                }
                $out[(string) $key] = $normalized;
            }
        }

        return $out;
    }
}
