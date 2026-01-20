<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Exceptions;

use RuntimeException;

final class JobReplayException extends RuntimeException
{
    public static function payloadNotStored(string $uuid): self
    {
        return new self("Job {$uuid} payload not stored, cannot replay");
    }

    public static function jobClassNotFound(string $jobClass): self
    {
        return new self("Job class {$jobClass} no longer exists");
    }

    public static function jobProcessing(string $uuid): self
    {
        return new self("Cannot replay job {$uuid} that is currently processing");
    }

    public static function storageDisabled(): self
    {
        return new self('Payload storage is disabled, cannot replay jobs');
    }

    public static function invalidPayload(string $uuid): self
    {
        return new self("Job {$uuid} has invalid payload format");
    }
}
