---
title: "Authentication & Access Control"
description: "How Queue Monitor secures the UI and REST API in local and production environments"
weight: 15
---

# Authentication & Access Control

Queue Monitor exposes two HTTP surfaces:

- The web dashboard (`/queue-monitor` by default)
- The REST API (`/api/queue-monitor` by default)

Both surfaces can expose sensitive operational data such as job payloads, exception traces, and replay functionality. You should treat them as protected endpoints.

## Default Security Model

Queue Monitor applies two layers of access control:

1. Route middleware configured in `config/queue-monitor.php`
2. A package-level authorization callback via `LaravelQueueMonitor::auth(...)`

If you do **not** register a custom authorization callback, Queue Monitor falls back to allowing access only in the `local` environment.

That means:

- Local development works out of the box
- Non-local environments return `403 Forbidden` by default

This fallback is enforced for **both** the UI and the API.

## How Access Is Evaluated

Every request to the dashboard or REST API passes through Queue Monitor's internal middleware.

The middleware checks:

1. Whether Queue Monitor is enabled
2. Whether the requested surface (`ui` or `api`) is enabled
3. Whether the request passes `LaravelQueueMonitor::check($request)`

`LaravelQueueMonitor::check($request)` uses:

- Your custom `LaravelQueueMonitor::auth(...)` callback, if you registered one
- Otherwise, a default fallback that only allows the `local` environment

## Important: Middleware Is Not the Whole Security Model

The config defaults are:

```php
'api' => [
    'middleware' => ['api'],
],

'ui' => [
    'middleware' => ['web'],
],
```

These defaults do **not** mean Queue Monitor is open in production.

Access is still blocked outside `local` unless you explicitly authorize it with `LaravelQueueMonitor::auth(...)`.

However, if you plan to use Queue Monitor in staging or production, you should still add framework-level auth middleware. That gives you a clear and explicit security boundary before the package callback runs.

## Recommended Production Setup

Use both:

- Framework auth middleware
- A package-level authorization callback

Example:

```php
// config/queue-monitor.php
'ui' => [
    'middleware' => ['web', 'auth'],
],

'api' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

```php
// In the boot() method of App\Providers\AuthServiceProvider
// or another application service provider
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

LaravelQueueMonitor::auth(function ($request) {
    return $request->user()?->isAdmin() === true;
});
```

This setup ensures:

- UI access requires a logged-in web user
- API access requires an authenticated API user or token
- Queue Monitor itself still enforces an application-specific authorization rule

## UI Authentication

The dashboard routes use the `queue-monitor.ui.middleware` configuration.

Default:

```php
'ui' => [
    'route_prefix' => 'queue-monitor',
    'middleware' => ['web'],
],
```

Recommended in production:

```php
'ui' => [
    'route_prefix' => 'queue-monitor',
    'middleware' => ['web', 'auth'],
],
```

If your application has role-based access control, keep that logic in `LaravelQueueMonitor::auth(...)`.

## API Authentication

The API routes use the `queue-monitor.api.middleware` configuration.

Default:

```php
'api' => [
    'prefix' => 'api/queue-monitor',
    'middleware' => ['api'],
],
```

Recommended in production:

```php
'api' => [
    'prefix' => 'api/queue-monitor',
    'middleware' => ['api', 'auth:sanctum'],
],
```

`auth:api` or another guard can also be used if that matches your application's auth stack better.

## Minimal Production Checklist

Before exposing Queue Monitor outside local development:

- Add `auth` middleware to the UI routes
- Add `auth:sanctum`, `auth:api`, or equivalent to the API routes
- Register `LaravelQueueMonitor::auth(...)` with an explicit authorization rule
- Review whether replay and payload access should be available to all authenticated users

## Example: Admin-Only Access

```php
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

LaravelQueueMonitor::auth(function ($request) {
    $user = $request->user();

    return $user !== null && $user->isAdmin();
});
```

## Example: Allow Internal IPs Only

```php
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

LaravelQueueMonitor::auth(function ($request) {
    return in_array($request->ip(), [
        '10.0.0.10',
        '10.0.0.11',
    ], true);
});
```

## Summary

The key rule is simple:

- Queue Monitor is `local`-only by default
- Production access must be explicitly authorized
- Middleware should be configured as an additional, explicit security layer

For route prefixes, rate limits, and other related options, see the [Configuration](configuration) guide.
