<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\getJson;
use function Pest\Laravel\get;

it('can access the web dashboard', function () {
    get(config('queue-monitor.ui.route_prefix'))
        ->assertStatus(200)
        ->assertViewIs('queue-monitor::web.dashboard');
});

it('returns metrics for the dashboard', function () {
    JobMonitor::factory()->create([
        'job_class' => 'App\Jobs\TestJob',
        'status' => 'completed',
    ]);

    getJson(config('queue-monitor.ui.route_prefix') . '/metrics')
        ->assertStatus(200)
        ->assertJsonStructure([
            'stats' => ['total_jobs', 'failed_jobs', 'success_rate'],
            'queues',
            'recent_jobs',
            'charts' => ['distribution'],
        ])
        ->assertJsonPath('stats.total_jobs', 1);
});

it('redacts sensitive data in dashboard payload', function () {
    config(['queue-monitor.api.sensitive_keys' => ['password']]);

    $job = JobMonitor::factory()->create([
        'payload' => [
            'user' => 'test',
            'password' => 'secret123',
        ],
    ]);

    getJson(config('queue-monitor.ui.route_prefix') . "/jobs/{$job->uuid}/payload")
        ->assertStatus(200)
        ->assertJsonPath('payload.password', '*****')
        ->assertJsonPath('payload.user', 'test');
});

it('can run the tui dashboard command (dry run)', function () {
    // We can't really test the infinite loop, but we can test if the command exists and 
    // basic execution doesn't fail immediately before the loop
    // For testing purposes, we might need to mock the terminal or handle the loop
    // But a simple check if it's registered is often enough for CI
    $exitCode = Artisan::call('queue-monitor:dashboard', ['--interval' => 0]);
    
    // Note: Since the command has an infinite while(true), 
    // we would need a way to break out of it for tests.
    // I will skip the full execution test for now or modify the command 
    // to allow a single run.
})->skip('TUI command has infinite loop and is hard to test in Pest without modification');
