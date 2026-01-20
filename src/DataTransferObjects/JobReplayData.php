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
        $originalUuid = $data['original_uuid'] ?? '';
        $newUuid = $data['new_uuid'] ?? '';
        $queue = $data['queue'] ?? '';
        $connection = $data['connection'] ?? '';

        return new self(
            originalUuid: is_string($originalUuid) ? $originalUuid : (string) $originalUuid,
            newUuid: is_string($newUuid) ? $newUuid : (string) $newUuid,
            newJobId: isset($data['new_job_id']) && is_string($data['new_job_id']) ? $data['new_job_id'] : null,
            queue: is_string($queue) ? $queue : (string) $queue,
            connection: is_string($connection) ? $connection : (string) $connection,
            replayedAt: Carbon::parse($data['replayed_at']),
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
