<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Exceptions;

use RuntimeException;

final class JobNotFoundException extends RuntimeException
{
    public static function withUuid(string $uuid): self
    {
        return new self("Job with UUID {$uuid} not found");
    }

    public static function withJobId(string $jobId): self
    {
        return new self("Job with ID {$jobId} not found");
    }
}
