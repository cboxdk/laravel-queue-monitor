<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Enums;

enum WorkerType: string
{
    case QUEUE_WORK = 'queue_work';
    case HORIZON = 'horizon';

    /**
     * Get all worker type values as an array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this is a Horizon worker
     */
    public function isHorizon(): bool
    {
        return $this === self::HORIZON;
    }

    /**
     * Check if this is a standard queue:work worker
     */
    public function isQueueWork(): bool
    {
        return $this === self::QUEUE_WORK;
    }

    /**
     * Get human-readable label for the worker type
     */
    public function label(): string
    {
        return match ($this) {
            self::QUEUE_WORK => 'Queue Worker',
            self::HORIZON => 'Horizon',
        };
    }

    /**
     * Get icon identifier for UI display
     */
    public function icon(): string
    {
        return match ($this) {
            self::QUEUE_WORK => 'terminal',
            self::HORIZON => 'dashboard',
        };
    }
}
