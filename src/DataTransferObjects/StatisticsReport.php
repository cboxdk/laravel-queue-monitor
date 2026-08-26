<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Exported statistics report: a timestamp plus the global, per-server, and
 * per-queue-health sections as computed by the analytics actions.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class StatisticsReport implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * The three sections stay arrays: they are the analytics actions' own
     * already-serialized output (each already a value object flattened to an
     * array), forwarded verbatim into the report.
     *
     * @param  array<string, mixed>  $global
     * @param  array<int, array<string, mixed>>  $servers
     * @param  array<int, array<string, mixed>>  $queueHealth
     */
    public function __construct(
        public string $generatedAt,
        public array $global = [],
        public array $servers = [],
        public array $queueHealth = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $generatedAt = $data['generated_at'] ?? '';

        return new self(
            generatedAt: is_scalar($generatedAt) ? (string) $generatedAt : '',
            global: self::assoc($data['global'] ?? null),
            servers: self::rows($data['servers'] ?? null),
            queueHealth: self::rows($data['queue_health'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'global' => $this->global,
            'servers' => $this->servers,
            'queue_health' => $this->queueHealth,
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
     * @return array<int, array<string, mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = self::assoc($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function assoc(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }
}
