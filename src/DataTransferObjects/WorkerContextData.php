<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\DataTransferObjects;

use PHPeek\LaravelQueueMonitor\Enums\WorkerType;

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
        $serverName = $data['server_name'] ?? '';
        $workerId = $data['worker_id'] ?? '';
        $workerType = $data['worker_type'] ?? 'queue_work';

        return new self(
            serverName: is_string($serverName) ? $serverName : (string) $serverName,
            workerId: is_string($workerId) ? $workerId : (string) $workerId,
            workerType: WorkerType::from(is_string($workerType) ? $workerType : (string) $workerType),
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
