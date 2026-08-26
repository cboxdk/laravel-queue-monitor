<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Failure-pattern analysis: the most frequent exception classes and their reach.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class FailurePatterns implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * @param  array<int, array<string, mixed>>  $topExceptions
     */
    public function __construct(
        public array $topExceptions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $top = $data['top_exceptions'] ?? [];

        $rows = [];
        if (is_array($top)) {
            foreach ($top as $row) {
                if (is_array($row)) {
                    $normalized = [];
                    foreach ($row as $key => $value) {
                        $normalized[(string) $key] = $value;
                    }
                    $rows[] = $normalized;
                }
            }
        }

        return new self($rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'top_exceptions' => $this->topExceptions,
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
