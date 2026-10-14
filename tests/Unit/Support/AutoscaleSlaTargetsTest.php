<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Support\AutoscaleSlaTargets;
use Cbox\LaravelQueueMonitor\Tests\Support\BalancedSlaProfile;
use Cbox\LaravelQueueMonitor\Tests\Support\FastSlaProfile;

beforeEach(function () {
    config()->set('queue-autoscale', []);
});

test('nothing is configured when autoscale is absent', function () {
    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->isConfigured())->toBeFalse()
        ->and($targets->targetFor('default'))->toBeNull();
});

test('sla_defaults as a profile class sets the default target', function () {
    config()->set('queue-autoscale.sla_defaults', BalancedSlaProfile::class);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->isConfigured())->toBeTrue()
        ->and($targets->targetFor('anything'))->toBe(20);
});

test('a queue entry may be a profile class, an sla array, or a profile refined by an array', function () {
    config()->set('queue-autoscale.sla_defaults', BalancedSlaProfile::class);
    config()->set('queue-autoscale.queues', [
        'payments' => FastSlaProfile::class,
        'reports' => ['sla' => ['target_seconds' => 120]],
        'emails' => ['profile' => FastSlaProfile::class, 'workers' => ['max' => 3]],
        'exports' => ['profile' => FastSlaProfile::class, 'sla' => ['target_seconds' => 90]],
        'bulk' => ['workers' => ['max' => 2]],
    ]);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->targetFor('payments'))->toBe(5)
        ->and($targets->targetFor('reports'))->toBe(120)
        ->and($targets->targetFor('emails'))->toBe(5)
        ->and($targets->targetFor('exports'))->toBe(90)
        ->and($targets->targetFor('bulk'))->toBe(20);
});

test('an exact name wins over a glob, and globs match in declaration order', function () {
    config()->set('queue-autoscale.sla_defaults', BalancedSlaProfile::class);
    config()->set('queue-autoscale.queues', [
        'tenant.*' => ['sla' => ['target_seconds' => 60]],
        'tenant.42' => FastSlaProfile::class,
        '*.fifo' => ['sla' => ['target_seconds' => 10]],
    ]);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->targetFor('tenant.42'))->toBe(5)
        ->and($targets->targetFor('tenant.7'))->toBe(60)
        ->and($targets->targetFor('orders.fifo'))->toBe(10)
        ->and($targets->targetFor('tenant.7.fifo'))->toBe(60);
});

test('a grouped queue takes its group target', function () {
    config()->set('queue-autoscale.sla_defaults', BalancedSlaProfile::class);
    config()->set('queue-autoscale.groups', [
        'notifications' => [
            'queues' => ['email', 'sms'],
            'profile' => FastSlaProfile::class,
        ],
        'overridden' => [
            'queues' => ['push'],
            'profile' => FastSlaProfile::class,
            'overrides' => ['sla' => ['target_seconds' => 15]],
        ],
        'defaulted' => [
            'queues' => ['webhooks'],
        ],
    ]);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->targetFor('email'))->toBe(5)
        ->and($targets->targetFor('sms'))->toBe(5)
        ->and($targets->targetFor('push'))->toBe(15)
        ->and($targets->targetFor('webhooks'))->toBe(20);
});

test('the v1 max_pickup_time_seconds key is still honoured', function () {
    config()->set('queue-autoscale.sla_defaults', ['max_pickup_time_seconds' => 45]);
    config()->set('queue-autoscale.queues', ['fast' => ['max_pickup_time_seconds' => 5]]);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->targetFor('fast'))->toBe(5)
        ->and($targets->targetFor('other'))->toBe(45);
});

test('a string that is not a profile class sets no target', function () {
    config()->set('queue-autoscale.sla_defaults', 'App\\Missing\\Profile');
    config()->set('queue-autoscale.queues', ['q' => stdClass::class]);

    $targets = AutoscaleSlaTargets::fromConfig();

    expect($targets->isConfigured())->toBeFalse()
        ->and($targets->targetFor('q'))->toBeNull();
});
