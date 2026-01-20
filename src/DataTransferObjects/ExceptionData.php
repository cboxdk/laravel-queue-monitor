<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use Throwable;

final readonly class ExceptionData
{
    public function __construct(
        public string $class,
        public string $message,
        public string $trace,
        public ?string $file = null,
        public ?int $line = null,
    ) {}

    /**
     * Create from Throwable
     */
    public static function fromThrowable(Throwable $exception): self
    {
        return new self(
            class: $exception::class,
            message: $exception->getMessage(),
            trace: $exception->getTraceAsString(),
            file: $exception->getFile(),
            line: $exception->getLine(),
        );
    }

    /**
     * Create from array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $class = $data['class'] ?? '';
        $message = $data['message'] ?? '';
        $trace = $data['trace'] ?? '';

        return new self(
            class: is_string($class) ? $class : (string) $class,
            message: is_string($message) ? $message : (string) $message,
            trace: is_string($trace) ? $trace : (string) $trace,
            file: isset($data['file']) && is_string($data['file']) ? $data['file'] : null,
            line: isset($data['line']) && is_int($data['line']) ? $data['line'] : null,
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'trace' => $this->trace,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }

    /**
     * Get short exception class name (without namespace)
     */
    public function shortClass(): string
    {
        $parts = explode('\\', $this->class);

        return end($parts);
    }

    /**
     * Get first line of exception message
     */
    public function shortMessage(int $maxLength = 100): string
    {
        $firstLine = explode("\n", $this->message)[0];

        if (strlen($firstLine) > $maxLength) {
            return substr($firstLine, 0, $maxLength).'...';
        }

        return $firstLine;
    }
}
