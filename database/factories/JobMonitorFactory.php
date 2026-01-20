<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Database\Factories;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobMonitor>
 */
class JobMonitorFactory extends Factory
{
    protected $model = JobMonitor::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'job_id' => (string) $this->faker->randomNumber(5),
            'job_class' => ExampleJob::class,
            'display_name' => $this->faker->sentence(3),
            'connection' => 'redis',
            'queue' => $this->faker->randomElement(['default', 'emails', 'notifications']),
            'payload' => [
                'displayName' => $this->faker->sentence(3),
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [
                    'command' => serialize(new \stdClass),
                ],
            ],
            'status' => JobStatus::COMPLETED,
            'attempt' => 1,
            'max_attempts' => 3,
            'retried_from_id' => null,
            'server_name' => 'web-'.$this->faker->numberBetween(1, 5),
            'worker_id' => 'worker-'.$this->faker->randomNumber(5),
            'worker_type' => WorkerType::QUEUE_WORK,
            'cpu_time_ms' => $this->faker->randomFloat(2, 10, 1000),
            'memory_peak_mb' => $this->faker->randomFloat(2, 10, 100),
            'file_descriptors' => $this->faker->numberBetween(10, 100),
            'duration_ms' => $this->faker->numberBetween(100, 5000),
            'exception_class' => null,
            'exception_message' => null,
            'exception_trace' => null,
            'tags' => null,
            'queued_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::FAILED,
            'exception_class' => 'RuntimeException',
            'exception_message' => 'Job failed for testing',
            'exception_trace' => $this->faker->text(500),
        ]);
    }

    public function timeout(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::TIMEOUT,
            'exception_class' => 'JobTimeout',
            'exception_message' => 'Job exceeded maximum execution time',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::PROCESSING,
            'started_at' => now(),
            'completed_at' => null,
            'duration_ms' => null,
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::QUEUED,
            'job_id' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_ms' => null,
        ]);
    }

    public function horizon(): static
    {
        return $this->state(fn (array $attributes) => [
            'worker_type' => WorkerType::HORIZON,
            'worker_id' => 'horizon-supervisor-'.$this->faker->randomNumber(3),
        ]);
    }

    /**
     * @param  array<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    public function slow(int $durationMs = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_ms' => $durationMs,
        ]);
    }
}
