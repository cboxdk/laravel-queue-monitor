<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Enums;

enum JobStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case CANCELLED = 'cancelled';

    /**
     * Get all status values as an array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if the status represents a finished job
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::TIMEOUT, self::CANCELLED => true,
            self::QUEUED, self::PROCESSING => false,
        };
    }

    /**
     * Check if the status represents a successful outcome
     */
    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if the status represents a failure
     */
    public function isFailed(): bool
    {
        return match ($this) {
            self::FAILED, self::TIMEOUT => true,
            default => false,
        };
    }

    /**
     * Check if the job is currently being processed
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Queued',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::TIMEOUT => 'Timeout',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get color indicator for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::QUEUED => 'gray',
            self::PROCESSING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::TIMEOUT => 'orange',
            self::CANCELLED => 'yellow',
        };
    }
}
