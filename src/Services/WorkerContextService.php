<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Services;

use PHPeek\LaravelQueueMetrics\Utilities\HorizonDetector;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\WorkerContextData;
use PHPeek\LaravelQueueMonitor\Enums\WorkerType;

final class WorkerContextService
{
    public function __construct(
        private readonly ?HorizonDetector $horizonDetector = null
    ) {}

    /**
     * Capture current worker context
     */
    public function capture(): WorkerContextData
    {
        return new WorkerContextData(
            serverName: $this->getServerName(),
            workerId: $this->getWorkerId(),
            workerType: $this->detectWorkerType(),
        );
    }

    /**
     * Get server name
     */
    private function getServerName(): string
    {
        $callable = config('queue-monitor.worker_detection.server_name_callable');

        if (is_callable($callable)) {
            return (string) $callable();
        }

        return gethostname() ?: 'unknown';
    }

    /**
     * Get worker ID
     */
    private function getWorkerId(): string
    {
        if ($this->isHorizon()) {
            return $this->getHorizonWorkerId();
        }

        return $this->getQueueWorkWorkerId();
    }

    /**
     * Detect worker type
     */
    private function detectWorkerType(): WorkerType
    {
        if (! config('queue-monitor.worker_detection.horizon_detection', true)) {
            return WorkerType::QUEUE_WORK;
        }

        return $this->isHorizon() ? WorkerType::HORIZON : WorkerType::QUEUE_WORK;
    }

    /**
     * Check if running under Horizon
     */
    private function isHorizon(): bool
    {
        if ($this->horizonDetector === null) {
            return false;
        }

        return $this->horizonDetector->isHorizon();
    }

    /**
     * Get Horizon worker ID from supervisor
     */
    private function getHorizonWorkerId(): string
    {
        // Try to get supervisor name from environment
        if (isset($_SERVER['HORIZON_SUPERVISOR'])) {
            return (string) $_SERVER['HORIZON_SUPERVISOR'];
        }

        // Fallback to detecting from running process
        if ($this->horizonDetector !== null) {
            $supervisorName = $this->horizonDetector->getSupervisorName();
            if ($supervisorName !== null) {
                return $supervisorName;
            }
        }

        // Ultimate fallback to PID
        return 'horizon-'.getmypid();
    }

    /**
     * Get queue:work worker ID (typically PID)
     */
    private function getQueueWorkWorkerId(): string
    {
        return 'worker-'.getmypid();
    }

    /**
     * Get a unique identifier for the current worker
     */
    public function getUniqueIdentifier(): string
    {
        $context = $this->capture();

        return $context->uniqueIdentifier();
    }
}
