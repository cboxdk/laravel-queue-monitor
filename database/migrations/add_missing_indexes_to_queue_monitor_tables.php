<?php

declare(strict_types=1);

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

        Schema::connection($connection)->table($prefix.'jobs', function (Blueprint $table): void {
            // Stuck job detection: AlertingService + HealthCheckService query
            // WHERE status = 'processing' AND started_at < ?
            $table->index(['status', 'started_at']);

            // Worker type breakdown: InfrastructureService::getWorkerTypeBreakdown
            // WHERE created_at >= ? GROUP BY worker_type, queue
            $table->index(['worker_type', 'created_at']);

            // General time filtering: prune without status filter, SLA queries
            // WHERE created_at < ? (no status prefix)
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        /** @var string|null $connection */
        $connection = config('queue-monitor.database.connection');
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        Schema::connection($connection)->table($prefix.'jobs', function (Blueprint $table) use ($prefix): void {
            $table->dropIndex($prefix.'jobs_status_started_at_index');
            $table->dropIndex($prefix.'jobs_worker_type_created_at_index');
            $table->dropIndex($prefix.'jobs_created_at_index');
        });
    }
};
