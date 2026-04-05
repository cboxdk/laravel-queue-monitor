<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use Carbon\Carbon;

final readonly class JobReplayData
{
    public function __construct(
        public string $originalUuid,
        public string $newUuid,
        public ?string $newJobId,
        public string $queue,
        public string $connection,
        public Carbon $replayedAt,
    ) {}

    /**
     * Create from array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $originalUuidRaw = $data['original_uuid'] ?? '';
        $newUuidRaw = $data['new_uuid'] ?? '';
        $queueRaw = $data['queue'] ?? '';
        $connectionRaw = $data['connection'] ?? '';
        $replayedAtRaw = $data['replayed_at'] ?? null;

        return new self(
            originalUuid: is_string($originalUuidRaw) ? $originalUuidRaw : (is_scalar($originalUuidRaw) ? (string) $originalUuidRaw : ''),
            newUuid: is_string($newUuidRaw) ? $newUuidRaw : (is_scalar($newUuidRaw) ? (string) $newUuidRaw : ''),
            newJobId: isset($data['new_job_id']) && is_string($data['new_job_id']) ? $data['new_job_id'] : null,
            queue: is_string($queueRaw) ? $queueRaw : (is_scalar($queueRaw) ? (string) $queueRaw : ''),
            connection: is_string($connectionRaw) ? $connectionRaw : (is_scalar($connectionRaw) ? (string) $connectionRaw : ''),
            replayedAt: Carbon::parse(is_string($replayedAtRaw) || is_numeric($replayedAtRaw) || $replayedAtRaw instanceof \DateTimeInterface ? $replayedAtRaw : null),
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'original_uuid' => $this->originalUuid,
            'new_uuid' => $this->newUuid,
            'new_job_id' => $this->newJobId,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'replayed_at' => $this->replayedAt->toIso8601String(),
        ];
    }
}
