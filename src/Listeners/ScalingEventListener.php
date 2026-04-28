<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Models\ClusterEvent;
use Cbox\LaravelQueueMonitor\Models\ScalingEvent;
use Illuminate\Support\Facades\Cache;

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

            $data = [
                'connection' => property_exists($event, 'connection') ? $event->connection : null,
                'queue' => property_exists($event, 'queue') ? $event->queue : null,
                'action' => 'sla_breach',
                'current_workers' => $activeWorkers,
                'target_workers' => $activeWorkers,
                'reason' => "SLA breached: {$oldestJobAge}s > {$slaTarget}s target ({$pending} pending)",
                'sla_target' => $slaTarget,
                'sla_breach_risk' => true,
                'pending' => $pending,
                'active_workers' => $activeWorkers,
            ];

            // v3 severity methods
            if (is_callable([$event, 'breachSeconds'])) {
                $data['breach_seconds'] = $event->breachSeconds();
            }
            if (is_callable([$event, 'breachPercentage'])) {
                $data['breach_percentage'] = $event->breachPercentage();
            }

            ScalingEvent::create($data);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSlaRecovered(object $event): void
    {
        try {
            $data = [
                'connection' => property_exists($event, 'connection') ? $event->connection : 'unknown',
                'queue' => property_exists($event, 'queue') ? $event->queue : 'unknown',
                'action' => 'sla_recovered',
                'current_workers' => 0,
                'target_workers' => 0,
                'reason' => 'SLA recovered',
            ];

            // v3 style: has margin methods and direct properties
            if (property_exists($event, 'activeWorkers')) {
                $data['current_workers'] = $event->activeWorkers;
                $data['target_workers'] = $event->activeWorkers;
                $data['active_workers'] = $event->activeWorkers;
            }
            if (property_exists($event, 'pending')) {
                $data['pending'] = $event->pending;
            }
            if (property_exists($event, 'currentJobAge') && property_exists($event, 'slaTarget')) {
                $data['reason'] = "SLA recovered: {$event->currentJobAge}s < {$event->slaTarget}s target";
                $data['sla_target'] = $event->slaTarget;
            }

            // v2 fallback
            if (property_exists($event, 'workersScaled')) {
                $data['current_workers'] = $data['current_workers'] ?: $event->workersScaled;
                $data['target_workers'] = $data['target_workers'] ?: $event->workersScaled;
            }
            if (property_exists($event, 'recoveryTime') && $data['reason'] === 'SLA recovered') {
                $data['reason'] = "SLA recovered after {$event->recoveryTime}s";
            }

            // v3 margin methods
            if (is_callable([$event, 'marginSeconds'])) {
                $data['margin_seconds'] = $event->marginSeconds();
            }
            if (is_callable([$event, 'marginPercentage'])) {
                $data['margin_percentage'] = $event->marginPercentage();
            }

            ScalingEvent::create($data);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── v3 handlers ─────────────────────────────────────────────────

    public function handleSlaBreachPredicted(object $event): void
    {
        try {
            /** @var object $decision */
            $decision = property_exists($event, 'decision') ? $event->decision : $event;

            ScalingEvent::create([
                'connection' => property_exists($decision, 'connection') ? $decision->connection : 'unknown',
                'queue' => property_exists($decision, 'queue') ? $decision->queue : 'unknown',
                'action' => property_exists($decision, 'action') && is_callable([$decision, 'action']) ? $decision->action() : 'sla_breach_predicted',
                'current_workers' => property_exists($decision, 'currentWorkers') ? $decision->currentWorkers : 0,
                'target_workers' => property_exists($decision, 'targetWorkers') ? $decision->targetWorkers : 0,
                'reason' => property_exists($decision, 'reason') ? $decision->reason : 'SLA breach predicted',
                'predicted_pickup_time' => property_exists($decision, 'predictedPickupTime') ? $decision->predictedPickupTime : null,
                'sla_target' => property_exists($decision, 'slaTarget') ? $decision->slaTarget : null,
                'sla_breach_risk' => true,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleManagerStarted(object $event): void
    {
        try {
            ClusterEvent::create([
                'cluster_id' => property_exists($event, 'clusterId') ? $event->clusterId : 'unknown',
                'manager_id' => property_exists($event, 'managerId') ? $event->managerId : null,
                'event_type' => 'manager_started',
                'host' => property_exists($event, 'host') ? $event->host : null,
                'meta' => [
                    'cluster_enabled' => property_exists($event, 'clusterEnabled') ? $event->clusterEnabled : null,
                    'interval_seconds' => property_exists($event, 'intervalSeconds') ? $event->intervalSeconds : null,
                    'package_version' => property_exists($event, 'packageVersion') ? $event->packageVersion : null,
                    'started_at' => property_exists($event, 'startedAt') ? $event->startedAt : null,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleManagerStopped(object $event): void
    {
        try {
            $startedAt = property_exists($event, 'startedAt') ? $event->startedAt : null;
            $stoppedAt = property_exists($event, 'stoppedAt') ? $event->stoppedAt : null;
            $uptimeSeconds = ($startedAt !== null && $stoppedAt !== null) ? ($stoppedAt - $startedAt) : null;

            ClusterEvent::create([
                'cluster_id' => property_exists($event, 'clusterId') ? $event->clusterId : 'unknown',
                'manager_id' => property_exists($event, 'managerId') ? $event->managerId : null,
                'event_type' => 'manager_stopped',
                'host' => property_exists($event, 'host') ? $event->host : null,
                'reason' => property_exists($event, 'reason') ? $event->reason : null,
                'meta' => [
                    'worker_count' => property_exists($event, 'workerCount') ? $event->workerCount : null,
                    'started_at' => $startedAt,
                    'stopped_at' => $stoppedAt,
                    'uptime_seconds' => $uptimeSeconds,
                    'package_version' => property_exists($event, 'packageVersion') ? $event->packageVersion : null,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleLeaderChanged(object $event): void
    {
        try {
            ClusterEvent::create([
                'cluster_id' => property_exists($event, 'clusterId') ? $event->clusterId : 'unknown',
                'manager_id' => property_exists($event, 'observedByManagerId') ? $event->observedByManagerId : null,
                'event_type' => 'leader_changed',
                'leader_id' => property_exists($event, 'currentLeaderId') ? $event->currentLeaderId : null,
                'previous_leader_id' => property_exists($event, 'previousLeaderId') ? $event->previousLeaderId : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handlePresenceChanged(object $event): void
    {
        try {
            ClusterEvent::create([
                'cluster_id' => property_exists($event, 'clusterId') ? $event->clusterId : 'unknown',
                'manager_id' => property_exists($event, 'observedByManagerId') ? $event->observedByManagerId : null,
                'event_type' => 'presence_changed',
                'leader_id' => property_exists($event, 'leaderId') ? $event->leaderId : null,
                'meta' => [
                    'manager_ids' => property_exists($event, 'managerIds') ? $event->managerIds : [],
                    'added_manager_ids' => property_exists($event, 'addedManagerIds') ? $event->addedManagerIds : [],
                    'removed_manager_ids' => property_exists($event, 'removedManagerIds') ? $event->removedManagerIds : [],
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleScalingSignalUpdated(object $event): void
    {
        try {
            $clusterId = property_exists($event, 'clusterId') ? $event->clusterId : 'unknown';

            // Signal sampling: skip if identical to last signal and within max interval
            if ($this->isDuplicateSignal($clusterId, $event)) {
                return;
            }

            ClusterEvent::create([
                'cluster_id' => $clusterId,
                'event_type' => 'scaling_signal',
                'leader_id' => property_exists($event, 'leaderId') ? $event->leaderId : null,
                'current_hosts' => property_exists($event, 'currentHosts') ? $event->currentHosts : null,
                'recommended_hosts' => property_exists($event, 'recommendedHosts') ? $event->recommendedHosts : null,
                'current_capacity' => property_exists($event, 'currentCapacity') ? $event->currentCapacity : null,
                'required_workers' => property_exists($event, 'requiredWorkers') ? $event->requiredWorkers : null,
                'action' => property_exists($event, 'action') ? $event->action : null,
                'reason' => property_exists($event, 'reason') ? $event->reason : null,
                'created_at' => now(),
            ]);

            $this->updateSignalCache($clusterId, $event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSummaryPublished(object $event): void
    {
        try {
            $clusterId = property_exists($event, 'clusterId') ? $event->clusterId : 'unknown';
            $summary = property_exists($event, 'summary') ? $event->summary : [];

            // Summary sampling: skip if identical hash and within max interval
            if ($this->isDuplicateSummary($clusterId, $summary)) {
                return;
            }

            ClusterEvent::create([
                'cluster_id' => $clusterId,
                'event_type' => 'summary_published',
                'leader_id' => property_exists($event, 'leaderId') ? $event->leaderId : null,
                'meta' => [
                    'summary' => $summary,
                    'published_at' => property_exists($event, 'publishedAt') ? $event->publishedAt : null,
                ],
                'created_at' => now(),
            ]);

            Cache::put("qm:last_summary:{$clusterId}", [
                'hash' => md5(serialize($summary)),
                'at' => time(),
            ], 120);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Signal sampling helpers ─────────────────────────────────────

    private function isDuplicateSignal(string $clusterId, object $event): bool
    {
        /** @var array{values: list<mixed>, at: int}|null $cached */
        $cached = Cache::get("qm:last_scaling_signal:{$clusterId}");

        if ($cached === null) {
            return false;
        }

        // Always store at least once per 60 seconds
        if ((time() - $cached['at']) >= 60) {
            return false;
        }

        // Compare key fields
        $current = [
            property_exists($event, 'currentHosts') ? $event->currentHosts : null,
            property_exists($event, 'recommendedHosts') ? $event->recommendedHosts : null,
            property_exists($event, 'currentCapacity') ? $event->currentCapacity : null,
            property_exists($event, 'requiredWorkers') ? $event->requiredWorkers : null,
            property_exists($event, 'action') ? $event->action : null,
        ];

        return $current === $cached['values'];
    }

    private function updateSignalCache(string $clusterId, object $event): void
    {
        Cache::put("qm:last_scaling_signal:{$clusterId}", [
            'values' => [
                property_exists($event, 'currentHosts') ? $event->currentHosts : null,
                property_exists($event, 'recommendedHosts') ? $event->recommendedHosts : null,
                property_exists($event, 'currentCapacity') ? $event->currentCapacity : null,
                property_exists($event, 'requiredWorkers') ? $event->requiredWorkers : null,
                property_exists($event, 'action') ? $event->action : null,
            ],
            'at' => time(),
        ], 120);
    }

    private function isDuplicateSummary(string $clusterId, mixed $summary): bool
    {
        /** @var array{hash: string, at: int}|null $cached */
        $cached = Cache::get("qm:last_summary:{$clusterId}");

        if ($cached === null) {
            return false;
        }

        if ((time() - $cached['at']) >= 60) {
            return false;
        }

        return md5(serialize($summary)) === $cached['hash'];
    }
}
