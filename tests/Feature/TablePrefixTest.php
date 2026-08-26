<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Feature;

use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Cbox\LaravelQueueMonitor\Models\Tag;
use Cbox\LaravelQueueMonitor\Tests\TestCase;

class TablePrefixTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('queue-monitor.database.table_prefix', 'custom_monitor_');
    }

    public function test_models_resolve_table_names_from_configured_prefix(): void
    {
        $this->assertSame('custom_monitor_jobs', (new JobMonitor)->getTable());
        $this->assertSame('custom_monitor_tags', (new Tag)->getTable());
        $this->assertSame('custom_monitor_scaling_events', (new ScalingEvent)->getTable());
        $this->assertSame('custom_monitor_cluster_events', (new ClusterEvent)->getTable());
    }
}
