<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One queue's scaling decisions over a window, in chronological order, plus the
 * worker range observed across them — the data behind the autoscale timeline
 * chart.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ScalingTimeline implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `events` stays a list of scaling-decision rows drawn from ScalingEvent
     * records.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @param  array{min: int|null, max: int|null}  $workerRange
     */
    public function __construct(
        public array $events = [],
        public array $workerRange = ['min' => null, 'max' => null],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $range = $data['worker_range'] ?? [];
        $range = is_array($range) ? $range : [];

        return new self(
            events: self::rows($data['events'] ?? null),
            workerRange: [
                'min' => self::intOrNull($range, 'min'),
                'max' => self::intOrNull($range, 'max'),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'events' => $this->events,
            'worker_range' => $this->workerRange,
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
                $normalized = [];
                foreach ($row as $key => $item) {
                    $normalized[(string) $key] = $item;
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function intOrNull(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
