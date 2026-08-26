<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

// Final-by-default: the domain concretes are sealed so behaviour is changed by
// rebinding a contract, never by subclassing an implementation. Models, Commands,
// and Http controllers are omitted because they extend framework base classes;
// Contracts is omitted because it holds interfaces, which cannot be final.
arch('domain classes are final by default')
    ->expect([
        'Cbox\LaravelQueueMonitor\Services',
        'Cbox\LaravelQueueMonitor\Actions',
        'Cbox\LaravelQueueMonitor\DataTransferObjects',
        'Cbox\LaravelQueueMonitor\Support',
        'Cbox\LaravelQueueMonitor\Utilities',
        'Cbox\LaravelQueueMonitor\Listeners',
        'Cbox\LaravelQueueMonitor\Repositories\Eloquent',
        'Cbox\LaravelQueueMonitor\Events',
        'Cbox\LaravelQueueMonitor\Exceptions',
    ])
    ->toBeFinal()
    // Contracts is a sub-namespace of Services and holds interfaces, which can
    // never be final; it is asserted separately by "service contracts are interfaces".
    ->ignoring('Cbox\LaravelQueueMonitor\Services\Contracts');

// Every domain service is resolved through a contract, so the interfaces must
// actually exist as interfaces for a host to bind a replacement against them.
arch('service contracts are interfaces')
    ->expect('Cbox\LaravelQueueMonitor\Services\Contracts')
    ->toBeInterfaces();
