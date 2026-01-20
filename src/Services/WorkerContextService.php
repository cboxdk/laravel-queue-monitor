<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMetrics\DataTransferObjects\HorizonContext;
use Cbox\LaravelQueueMetrics\Utilities\HorizonDetector;
use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerContextData;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;

final readonly class WorkerContextService
{
    private ?HorizonContext $horizonContext;

    public function __construct()
    {
        // Detect Horizon context on construction
        $this->horizonContext = config('queue-monitor.horizon_detection', true)
            ? HorizonDetector::detect()
            : null;
    }

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
        /** @var callable|null $callable */
        $callable = config('queue-monitor.server_name');

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
        if ($this->isHorizon()) {
            return WorkerType::HORIZON;
        }

        if ($this->isAutoscale()) {
            return WorkerType::AUTOSCALE;
        }

        return WorkerType::QUEUE_WORK;
    }

    /**
     * Check if running under Autoscale
     */
    private function isAutoscale(): bool
    {
        return env('LARAVEL_AUTOSCALE_WORKER', false) !== false;
    }

    /**
     * Check if running under Horizon
     */
    private function isHorizon(): bool
    {
        if ($this->horizonContext === null) {
            return false;
        }

        return $this->horizonContext->isHorizon;
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

        // Get from detected context
        if ($this->horizonContext?->supervisorName !== null) {
            return $this->horizonContext->supervisorName;
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
