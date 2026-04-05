<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use Cbox\LaravelQueueMonitor\Enums\WorkerType;

final readonly class WorkerContextData
{
    public function __construct(
        public string $serverName,
        public string $workerId,
        public WorkerType $workerType,
    ) {}

    /**
     * Create from array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $serverNameRaw = $data['server_name'] ?? '';
        $workerIdRaw = $data['worker_id'] ?? '';
        $workerTypeRaw = $data['worker_type'] ?? 'queue_work';

        $serverName = is_string($serverNameRaw) ? $serverNameRaw : (is_scalar($serverNameRaw) ? (string) $serverNameRaw : '');
        $workerId = is_string($workerIdRaw) ? $workerIdRaw : (is_scalar($workerIdRaw) ? (string) $workerIdRaw : '');
        $workerType = is_string($workerTypeRaw) ? $workerTypeRaw : (is_scalar($workerTypeRaw) ? (string) $workerTypeRaw : 'queue_work');

        return new self(
            serverName: $serverName,
            workerId: $workerId,
            workerType: WorkerType::from($workerType),
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'server_name' => $this->serverName,
            'worker_id' => $this->workerId,
            'worker_type' => $this->workerType->value,
        ];
    }

    /**
     * Check if this is a Horizon worker
     */
    public function isHorizon(): bool
    {
        return $this->workerType->isHorizon();
    }

    /**
     * Get a unique identifier for this worker
     */
    public function uniqueIdentifier(): string
    {
        return "{$this->serverName}:{$this->workerId}";
    }
}
