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
        $classRaw = $data['class'] ?? '';
        $messageRaw = $data['message'] ?? '';
        $traceRaw = $data['trace'] ?? '';

        $class = is_string($classRaw) ? $classRaw : (is_scalar($classRaw) ? (string) $classRaw : '');
        $message = is_string($messageRaw) ? $messageRaw : (is_scalar($messageRaw) ? (string) $messageRaw : '');
        $trace = is_string($traceRaw) ? $traceRaw : (is_scalar($traceRaw) ? (string) $traceRaw : '');

        return new self(
            class: $class,
            message: $message,
            trace: $trace,
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
