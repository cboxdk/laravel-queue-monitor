<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('queue-monitor.database.connection');
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        if (! Schema::connection($connection)->hasTable($prefix.'jobs')) {
            Schema::connection($connection)->create($prefix.'jobs', function (Blueprint $table) use ($prefix): void {
                $table->id();

                // Job Identification
                $table->string('uuid', 36)->index();
                $table->string('job_id')->nullable()->index();
                $table->string('job_class')->index();
                $table->string('display_name')->nullable();

                // Queue Configuration
                $table->string('connection')->index();
                $table->string('queue')->index();

                // Payload for replay
                $table->longText('payload')->nullable();

                // Status and Attempts
                $table->enum('status', JobStatus::values())->index();
                $table->unsignedInteger('attempt')->default(1);
                $table->unsignedInteger('max_attempts')->default(1);

                // Retry Chain
                $table->unsignedBigInteger('retried_from_id')->nullable();
                $table->foreign('retried_from_id')
                    ->references('id')
                    ->on($prefix.'jobs')
                    ->nullOnDelete();

                // Worker and Server Information
                $table->string('server_name')->index();
                $table->string('worker_id')->index();
                $table->enum('worker_type', WorkerType::values());

                // Metrics from laravel-queue-metrics
                $table->decimal('cpu_time_ms', 10, 2)->nullable();
                $table->decimal('memory_peak_mb', 10, 2)->nullable();
                $table->unsignedInteger('file_descriptors')->nullable();

                // Performance
                $table->unsignedInteger('duration_ms')->nullable()->index();

                // Exception Information
                $table->string('exception_class')->nullable()->index();
                $table->text('exception_message')->nullable();
                $table->longText('exception_trace')->nullable();

                // Tags
                $table->json('tags')->nullable();

                // Timestamps
                $table->timestamp('queued_at')->index();
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable()->index();
                $table->timestamps();

                // Composite indexes for common queries
                $table->index(['status', 'created_at']);
                $table->index(['queue', 'status', 'created_at']);
                $table->index(['job_class', 'status']);
                $table->index(['server_name', 'worker_id', 'status']);

                // Additional indexes for analytics and health check queries
                $table->index(['status', 'completed_at']); // Error rate trends
                $table->index(['queue', 'created_at']);    // Queue health metrics
                $table->index(['job_class', 'completed_at', 'duration_ms']); // Performance regression detection
            });
        }

        // Create tags table for normalized tag storage and analytics
        if (! Schema::connection($connection)->hasTable($prefix.'tags')) {
            Schema::connection($connection)->create($prefix.'tags', function (Blueprint $table) use ($prefix): void {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->string('tag')->index();

                $table->foreign('job_id')
                    ->references('id')
                    ->on($prefix.'jobs')
                    ->cascadeOnDelete();

                $table->unique(['job_id', 'tag']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $connection = config('queue-monitor.database.connection');
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        Schema::connection($connection)->dropIfExists($prefix.'tags');
        Schema::connection($connection)->dropIfExists($prefix.'jobs');
    }
};
