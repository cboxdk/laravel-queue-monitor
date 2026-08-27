<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Fixtures;

use Cbox\LaravelQueueMonitor\Testing\InteractsWithQueueMonitor;
use PHPUnit\Framework\TestCase;

/**
 * Composition site so PHPStan analyses the InteractsWithQueueMonitor trait
 * (a trait is only analysed where it is used, and its real use is in tests,
 * outside the analysed paths). This fixture is never executed.
 */
final class UsesInteractsWithQueueMonitor extends TestCase
{
    use InteractsWithQueueMonitor;
}
