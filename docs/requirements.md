---
title: "Requirements"
description: "Supported PHP and Laravel versions and the Composer dependencies enforced by the package"
weight: 2
---

# Requirements

These are the versions Composer resolves and enforces when you install
`cboxdk/laravel-queue-monitor`. They come directly from the package's
`composer.json` — nothing here is a soft recommendation; the resolver refuses to
install outside these constraints.

## Runtime

| Requirement | Constraint |
| :--- | :--- |
| PHP | `^8.3 \|\| ^8.4 \|\| ^8.5` |
| Laravel (`illuminate/contracts`) | `^11.0 \|\| ^12.0 \|\| ^13.0` |

The package runs on PHP 8.3, 8.4, and 8.5, and on Laravel 11, 12, and 13.

## Composer dependencies

These packages are pulled in automatically as direct dependencies:

| Package | Constraint | Purpose |
| :--- | :--- | :--- |
| `cboxdk/laravel-queue-metrics` | `^3.0` | Per-job CPU and memory instrumentation |
| `nunomaduro/termwind` | `^2.3` | Styled output for the terminal dashboard and commands |
| `spatie/laravel-package-tools` | `^1.16` | Package service-provider scaffolding |

## Queue drivers

Queue Monitor listens to Laravel's queue events, so it works with any driver
that fires them — `database`, `redis`, `sqs`, `beanstalkd`, or a custom driver.
No specific driver is required.

## Optional integrations

These are not installed by Queue Monitor and are not required. When present,
they are auto-detected and enrich the dashboard:

- [cboxdk/laravel-queue-autoscale](https://github.com/cboxdk/laravel-queue-autoscale) — adds the scaling timeline and scaling-event history.
- [Laravel Horizon](https://laravel.com/docs/horizon) — adds supervisor data, workload metrics, and jobs-per-minute when detected.
