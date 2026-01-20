<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Enums;

enum WorkerType: string
{
    case QUEUE_WORK = 'queue_work';
    case HORIZON = 'horizon';
    case AUTOSCALE = 'autoscale';

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
     * Check if this is an Autoscale worker
     */
    public function isAutoscale(): bool
    {
        return $this === self::AUTOSCALE;
    }

    /**
     * Get human-readable label for the worker type
     */
    public function label(): string
    {
        return match ($this) {
            self::QUEUE_WORK => 'Queue Worker',
            self::HORIZON => 'Horizon',
            self::AUTOSCALE => 'Autoscale',
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
            self::AUTOSCALE => 'chip',
        };
    }
}
