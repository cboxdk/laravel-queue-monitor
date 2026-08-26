<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\ResolveStuckJobAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

use function Pest\Laravel\postJson;

test('resolving a stuck job records a completion timestamp and duration', function () {
    $job = JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(45),
        'completed_at' => null,
        'duration_ms' => null,
    ]);

    $result = app(ResolveStuckJobAction::class)->execute([$job->uuid], 'delete');

    // (delete path) — the retry path is what writes the timestamp; assert on it:
    expect($result['resolved'])->toBe(1);

    $retryJob = JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(30),
        'completed_at' => null,
        'duration_ms' => null,
    ]);

    app(ResolveStuckJobAction::class)->execute([$retryJob->uuid], 'retry');

    $retryJob->refresh();
    expect($retryJob->status)->toBe(JobStatus::TIMEOUT);
    expect($retryJob->completed_at)->not->toBeNull();
    expect($retryJob->duration_ms)->toBeGreaterThan(0);
});

test('batch delete rejects an invalid status filter instead of widening the match', function () {
    JobMonitor::factory()->count(3)->create(['status' => 'completed']);
    JobMonitor::factory()->count(2)->failed()->create();

    // A misspelled status must fail validation, not silently drop the filter
    // and delete across every job.
    $response = postJson('/api/queue-monitor/batch/delete', [
        'filters' => ['statuses' => ['faield']],
        'max_jobs' => 1000,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('filters.statuses.0');
    expect(JobMonitor::count())->toBe(5);
});

test('batch delete still works with a valid status filter', function () {
    JobMonitor::factory()->count(3)->create(['status' => 'completed']);
    JobMonitor::factory()->count(2)->failed()->create();

    $response = postJson('/api/queue-monitor/batch/delete', [
        'filters' => ['statuses' => ['failed']],
        'max_jobs' => 1000,
    ]);

    $response->assertOk();
    expect(JobMonitor::where('status', 'completed')->count())->toBe(3);
    expect(JobMonitor::where('status', 'failed')->count())->toBe(0);
});

test('drill-down job summary redacts sensitive constructor properties', function () {
    config()->set('queue-monitor.api.sensitive_keys', ['password', 'token']);

    // A command serialized with a sensitive property; the drill-down builds its
    // summary from this raw blob, so it must apply the redaction policy itself.
    $command = 'O:8:"stdClass":2:{s:8:"password";s:14:"hunter2-secret";s:7:"user_id";i:42;}';

    JobMonitor::factory()->create([
        'queue' => 'payments',
        'payload' => ['data' => ['command' => $command]],
    ]);

    $response = $this->getJson(route('queue-monitor.dashboard.drill-down', ['type' => 'queue', 'value' => 'payments']));

    $response->assertOk();
    $summary = collect($response->json('recent_jobs'))->pluck('summary')->implode(' ');
    expect($summary)->not->toContain('hunter2-secret');
    expect($summary)->toContain('[REDACTED]');
});

test('prune rejects an unknown status with 422 instead of a 500', function () {
    $response = postJson('/api/queue-monitor/prune', [
        'statuses' => ['bogus'],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('statuses.0');
});
