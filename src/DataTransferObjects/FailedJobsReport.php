<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Exported failed-jobs report: the total, the failures grouped by exception and
 * by queue, and a sample of the most recent failures.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class FailedJobsReport implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * The groupings stay arrays: `byException` and `byQueue` are keyed by
     * arbitrary exception classes and queue names discovered at runtime, and
     * `recentFailures` is a sampled row list.
     *
     * @param  array<string, array<string, mixed>>  $byException
     * @param  array<string, int>  $byQueue
     * @param  array<int, array<string, mixed>>  $recentFailures
     */
    public function __construct(
        public string $generatedAt,
        public int $totalFailed,
        public array $byException = [],
        public array $byQueue = [],
        public array $recentFailures = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $generatedAt = $data['generated_at'] ?? '';
        $totalFailed = $data['total_failed'] ?? 0;

        return new self(
            generatedAt: is_scalar($generatedAt) ? (string) $generatedAt : '',
            totalFailed: is_numeric($totalFailed) ? (int) $totalFailed : 0,
            byException: self::map($data['by_exception'] ?? null),
            byQueue: self::intMap($data['by_queue'] ?? null),
            recentFailures: self::rows($data['recent_failures'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'total_failed' => $this->totalFailed,
            'by_exception' => $this->byException,
            'by_queue' => $this->byQueue,
            'recent_failures' => $this->recentFailures,
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
                $out[(string) $key] = self::assoc($row);
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private static function intMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_numeric($item)) {
                $out[(string) $key] = (int) $item;
            }
        }

        return $out;
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
