<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Models\ScalingEvent;

class ScalingEventListener
{
    public function handleScalingDecision(object $event): void
    {
        try {
            /** @var object $decision */
            $decision = property_exists($event, 'decision') ? $event->decision : $event;

            ScalingEvent::create([
                'connection' => property_exists($decision, 'connection') ? $decision->connection : null,
                'queue' => property_exists($decision, 'queue') ? $decision->queue : null,
                'action' => property_exists($decision, 'action') && is_callable([$decision, 'action']) ? $decision->action() : null,
                'current_workers' => property_exists($decision, 'currentWorkers') ? $decision->currentWorkers : null,
                'target_workers' => property_exists($decision, 'targetWorkers') ? $decision->targetWorkers : null,
                'reason' => property_exists($decision, 'reason') ? $decision->reason : null,
                'predicted_pickup_time' => property_exists($decision, 'predictedPickupTime') ? $decision->predictedPickupTime : null,
                'sla_target' => property_exists($decision, 'slaTarget') ? $decision->slaTarget : null,
                'sla_breach_risk' => property_exists($decision, 'isSlaBreachRisk') && is_callable([$decision, 'isSlaBreachRisk']) ? $decision->isSlaBreachRisk() : false,
            ]);
        } catch (\Throwable $e) {
            // Never crash the autoscale process — monitoring is observational only
            report($e);
        }
    }

    public function handleWorkersScaled(object $event): void
    {
        try {
            ScalingEvent::create([
                'connection' => property_exists($event, 'connection') ? $event->connection : null,
                'queue' => property_exists($event, 'queue') ? $event->queue : null,
                'action' => property_exists($event, 'action') ? $event->action : null,
                'current_workers' => property_exists($event, 'from') ? $event->from : null,
                'target_workers' => property_exists($event, 'to') ? $event->to : null,
                'reason' => property_exists($event, 'reason') ? $event->reason : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSlaBreached(object $event): void
    {
        try {
            $activeWorkers = property_exists($event, 'activeWorkers') ? $event->activeWorkers : null;
            $oldestJobAge = property_exists($event, 'oldestJobAge') ? $event->oldestJobAge : null;
            $pending = property_exists($event, 'pending') ? $event->pending : null;
            $slaTarget = property_exists($event, 'slaTarget') ? $event->slaTarget : null;

            ScalingEvent::create([
                'connection' => property_exists($event, 'connection') ? $event->connection : null,
                'queue' => property_exists($event, 'queue') ? $event->queue : null,
                'action' => 'sla_breach',
                'current_workers' => $activeWorkers,
                'target_workers' => $activeWorkers,
                'reason' => "SLA breached: {$oldestJobAge}s > {$slaTarget}s target ({$pending} pending)",
                'sla_target' => $slaTarget,
                'sla_breach_risk' => true,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSlaRecovered(object $event): void
    {
        try {
            $workersScaled = property_exists($event, 'workersScaled') ? $event->workersScaled : null;
            $recoveryTime = property_exists($event, 'recoveryTime') ? $event->recoveryTime : null;

            ScalingEvent::create([
                'connection' => property_exists($event, 'connection') ? $event->connection : null,
                'queue' => property_exists($event, 'queue') ? $event->queue : null,
                'action' => 'sla_recovered',
                'current_workers' => $workersScaled,
                'target_workers' => $workersScaled,
                'reason' => "SLA recovered after {$recoveryTime}s",
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
