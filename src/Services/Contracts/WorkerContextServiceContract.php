<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerContextData;

interface WorkerContextServiceContract
{
    /**
     * Capture current worker context
     */
    public function capture(): WorkerContextData;

    /**
     * Get a unique identifier for the current worker
     */
    public function getUniqueIdentifier(): string;
}
