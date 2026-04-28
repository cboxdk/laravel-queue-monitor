<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        // Add 'debounced' to the status enum on the jobs table
        if (Schema::connection($connection)->hasTable($prefix.'jobs')
            && Schema::connection($connection)->hasColumn($prefix.'jobs', 'status')) {
            Schema::connection($connection)->table($prefix.'jobs', function (Blueprint $table): void {
                $table->enum('status', JobStatus::values())->change();
            });
        }

        // Extend scaling_events with v3 SLA severity data
        if (Schema::connection($connection)->hasTable($prefix.'scaling_events')
            && ! Schema::connection($connection)->hasColumn($prefix.'scaling_events', 'breach_seconds')) {
            Schema::connection($connection)->table($prefix.'scaling_events', function (Blueprint $table): void {
                $table->integer('breach_seconds')->nullable();
                $table->decimal('breach_percentage', 8, 2)->nullable();
                $table->integer('margin_seconds')->nullable();
                $table->decimal('margin_percentage', 8, 2)->nullable();
                $table->integer('pending')->nullable();
                $table->integer('active_workers')->nullable();
            });
        }

        // Create cluster_events table for v3 orchestration
        if (! Schema::connection($connection)->hasTable($prefix.'cluster_events')) {
            Schema::connection($connection)->create($prefix.'cluster_events', function (Blueprint $table): void {
                $table->id();
                $table->string('cluster_id');
                $table->string('manager_id')->nullable();
                $table->string('event_type');
                $table->string('host')->nullable();
                $table->string('leader_id')->nullable();
                $table->string('previous_leader_id')->nullable();
                $table->integer('current_hosts')->nullable();
                $table->integer('recommended_hosts')->nullable();
                $table->integer('current_capacity')->nullable();
                $table->integer('required_workers')->nullable();
                $table->string('action')->nullable();
                $table->text('reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['cluster_id', 'event_type', 'created_at'], 'ce_cluster_type_time');
                $table->index(['event_type', 'created_at'], 'ce_type_time');
                $table->index(['manager_id', 'created_at'], 'ce_manager_time');
            });
        }
    }

    public function down(): void
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        Schema::connection($connection)->dropIfExists($prefix.'cluster_events');

        if (Schema::connection($connection)->hasTable($prefix.'scaling_events')) {
            Schema::connection($connection)->table($prefix.'scaling_events', function (Blueprint $table): void {
                $table->dropColumn(['breach_seconds', 'breach_percentage', 'margin_seconds', 'margin_percentage', 'pending', 'active_workers']);
            });
        }
    }
};
