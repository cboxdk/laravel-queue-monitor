<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Http\Middleware\EnsureQueueMonitorEnabled;
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('allows API request when monitor and API are enabled', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.api.enabled', true);

    $middleware = new EnsureQueueMonitorEnabled;

    $response = $middleware->handle(
        Request::create('/api/queue-monitor/jobs'),
        fn () => new Response('OK'),
        'api'
    );

    expect($response->getStatusCode())->toBe(200);
});

test('returns 503 when monitor is disabled', function () {
    config()->set('queue-monitor.enabled', false);

    $middleware = new EnsureQueueMonitorEnabled;

    $middleware->handle(
        Request::create('/api/queue-monitor/jobs'),
        fn () => new Response('OK'),
        'api'
    );
})->throws(HttpException::class);

test('returns 503 when API is disabled for API context', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.api.enabled', false);

    $middleware = new EnsureQueueMonitorEnabled;

    $middleware->handle(
        Request::create('/api/queue-monitor/jobs'),
        fn () => new Response('OK'),
        'api'
    );
})->throws(HttpException::class);

test('allows UI request when API is disabled but UI is enabled', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.api.enabled', false);
    config()->set('queue-monitor.ui.enabled', true);

    $middleware = new EnsureQueueMonitorEnabled;

    $response = $middleware->handle(
        Request::create('/queue-monitor'),
        fn () => new Response('OK'),
        'ui'
    );

    expect($response->getStatusCode())->toBe(200);
});

test('returns 503 when UI is disabled for UI context', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.ui.enabled', false);

    $middleware = new EnsureQueueMonitorEnabled;

    $middleware->handle(
        Request::create('/queue-monitor'),
        fn () => new Response('OK'),
        'ui'
    );
})->throws(HttpException::class);

test('returns 403 when auth callback denies access', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.api.enabled', true);

    LaravelQueueMonitor::auth(fn () => false);

    $middleware = new EnsureQueueMonitorEnabled;

    $middleware->handle(
        Request::create('/api/queue-monitor/jobs'),
        fn () => new Response('OK'),
        'api'
    );
})->throws(HttpException::class);

test('defaults to local environment check when no auth callback registered', function () {
    config()->set('queue-monitor.enabled', true);
    config()->set('queue-monitor.api.enabled', true);

    // Clear the auth callback set in TestCase::setUp
    LaravelQueueMonitor::$authUsing = null;

    $middleware = new EnsureQueueMonitorEnabled;

    // In testing environment (not local), should deny
    $middleware->handle(
        Request::create('/api/queue-monitor/jobs'),
        fn () => new Response('OK'),
        'api'
    );
})->throws(HttpException::class);
