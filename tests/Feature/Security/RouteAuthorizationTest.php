<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

use function Pest\Laravel\getJson;

/**
 * The middleware unit tests prove the gate's logic; these prove it is actually
 * wired onto the routes, so removing it from the route groups fails a test
 * instead of silently shipping a public dashboard.
 */
beforeEach(function () {
    // Drop the permissive test callback so the default (deny outside local)
    // applies, and make the app report a non-local environment.
    LaravelQueueMonitor::$authUsing = null;
    config()->set('app.env', 'production');
    app()->detectEnvironment(fn () => 'production');
});

test('the dashboard route denies when no auth callback is registered', function () {
    getJson(route('queue-monitor.dashboard'))->assertForbidden();
});

test('the jobs API route denies when no auth callback is registered', function () {
    config()->set('queue-monitor.api.enabled', true);

    getJson(route('queue-monitor.jobs.index'))->assertForbidden();
});

test('a registered auth callback that denies is honoured on the routes', function () {
    LaravelQueueMonitor::auth(fn () => false);

    getJson(route('queue-monitor.dashboard'))->assertForbidden();
});
