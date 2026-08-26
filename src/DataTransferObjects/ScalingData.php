<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Worker-utilization and autoscaling picture for the infrastructure and
 * autoscale tabs: the utilization summary, the recent scaling-decision history
 * and its aggregate summary, the fuses currently holding queues, and breach
 * severity.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
final readonly class ScalingData implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * `history` and `openFuses` are scaling-event dumps and `summary`/
     * `utilization`/`breachSeverity` are aggregates whose keys depend on which
     * actions occurred in the window, so they stay arrays rather than forcing a
     * fixed nested shape onto variable telemetry.
     *
     * @param  array<string, mixed>  $utilization
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $openFuses
     * @param  array<string, mixed>|null  $breachSeverity
     */
    public function __construct(
        public array $utilization,
        public array $history = [],
        public array $summary = [],
        public array $openFuses = [],
        public bool $hasAutoscale = false,
        public ?int $autoscaleVersion = null,
        public ?array $breachSeverity = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $version = $data['autoscale_version'] ?? null;

        return new self(
            utilization: self::assoc($data['utilization'] ?? null),
            history: self::rows($data['history'] ?? null),
            summary: self::assoc($data['summary'] ?? null),
            openFuses: self::rows($data['open_fuses'] ?? null),
            hasAutoscale: (bool) ($data['has_autoscale'] ?? false),
            autoscaleVersion: is_numeric($version) ? (int) $version : null,
            breachSeverity: is_array($data['breach_severity'] ?? null) ? self::assoc($data['breach_severity']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'utilization' => $this->utilization,
            'history' => $this->history,
            'summary' => $this->summary,
            'open_fuses' => $this->openFuses,
            'has_autoscale' => $this->hasAutoscale,
            'autoscale_version' => $this->autoscaleVersion,
            'breach_severity' => $this->breachSeverity,
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
