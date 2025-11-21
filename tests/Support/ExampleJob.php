<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $data = 'test data'
    ) {}

    public function handle(): void
    {
        // Example job execution
    }

    /**
     * @return array<string>
     */
    public function tags(): array
    {
        return ['example', 'test'];
    }

    public function displayName(): string
    {
        return 'Example Job for Testing';
    }
}
