<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

/**
 * Pickup-time SLA targets read from `cboxdk/laravel-queue-autoscale` config.
 *
 * Autoscale is optional, so its configuration is read directly rather than
 * through its classes. Every shape autoscale accepts resolves here the same way
 * autoscale resolves it:
 *
 * - `sla_defaults` and a `queues` entry may be a profile class (anything with a
 *   `resolve(): array` method), an array with `sla.target_seconds`, or an array
 *   naming a `profile` baseline that the rest of the array refines.
 * - A `queues` key may be a glob (`tenant.*`); an exact name wins, then the
 *   first matching glob in declaration order.
 * - A queue listed in a `groups` entry takes the group's target, from its
 *   `overrides`, then its `profile`, then `sla_defaults`.
 * - The v1 key `max_pickup_time_seconds` is still honoured.
 */
final readonly class AutoscaleSlaTargets
{
    /**
     * @param  array<string, int|null>  $queueTargets  Keyed by queue name or glob, in config order; null when the entry sets no target of its own
     * @param  array<string, int>  $groupMemberTargets  Keyed by member queue name
     */
    public function __construct(
        public ?int $defaultTarget = null,
        public array $queueTargets = [],
        public array $groupMemberTargets = [],
    ) {}

    public static function fromConfig(): self
    {
        $defaultTarget = self::targetOf(config('queue-autoscale.sla_defaults'));

        $queueTargets = [];
        $queues = config('queue-autoscale.queues', []);

        if (is_array($queues)) {
            foreach ($queues as $key => $entry) {
                if (is_string($key)) {
                    $queueTargets[$key] = self::targetOf($entry);
                }
            }
        }

        $groupMemberTargets = [];
        $groups = config('queue-autoscale.groups', []);

        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (! is_array($group) || ! is_array($group['queues'] ?? null)) {
                    continue;
                }

                $target = self::targetOf($group['overrides'] ?? null)
                    ?? self::targetOf($group['profile'] ?? null)
                    ?? $defaultTarget;

                if ($target === null) {
                    continue;
                }

                foreach ($group['queues'] as $member) {
                    if (is_string($member) && $member !== '') {
                        $groupMemberTargets[$member] = $target;
                    }
                }
            }
        }

        return new self($defaultTarget, $queueTargets, $groupMemberTargets);
    }

    /**
     * Whether autoscale configures any pickup-time target at all.
     */
    public function isConfigured(): bool
    {
        return $this->defaultTarget !== null
            || $this->groupMemberTargets !== []
            || array_filter($this->queueTargets, static fn (?int $target): bool => $target !== null) !== [];
    }

    /**
     * The target for a queue, or null when autoscale configures none for it.
     */
    public function targetFor(string $queue): ?int
    {
        if (array_key_exists($queue, $this->groupMemberTargets)) {
            return $this->groupMemberTargets[$queue];
        }

        if (array_key_exists($queue, $this->queueTargets)) {
            return $this->queueTargets[$queue] ?? $this->defaultTarget;
        }

        foreach ($this->queueTargets as $pattern => $target) {
            if (strpbrk($pattern, '*?[') !== false && fnmatch($pattern, $queue)) {
                return $target ?? $this->defaultTarget;
            }
        }

        return $this->defaultTarget;
    }

    /**
     * The pickup target an entry sets itself, before falling back to defaults.
     */
    private static function targetOf(mixed $entry, int $depth = 0): ?int
    {
        // A profile may itself name a baseline profile; bound the chain so a
        // profile that names itself cannot recurse forever.
        if ($depth > 3) {
            return null;
        }

        if (is_string($entry)) {
            return self::targetOf(self::resolveProfile($entry), $depth + 1);
        }

        if (! is_array($entry)) {
            return null;
        }

        $legacy = $entry['max_pickup_time_seconds'] ?? null;

        if (is_numeric($legacy) && (int) $legacy > 0) {
            return (int) $legacy;
        }

        $sla = $entry['sla'] ?? null;
        $target = is_array($sla) ? ($sla['target_seconds'] ?? null) : null;

        if (is_numeric($target) && (int) $target > 0) {
            return (int) $target;
        }

        // A 'profile' key names the baseline the rest of the entry refines, so
        // the entry's own target (checked above) wins over the profile's.
        return isset($entry['profile']) ? self::targetOf($entry['profile'], $depth + 1) : null;
    }

    /**
     * Resolve a profile class to its settings array, or null when the string
     * does not name a usable profile.
     *
     * @return array<mixed>|null
     */
    private static function resolveProfile(string $class): ?array
    {
        if (! class_exists($class) || ! method_exists($class, 'resolve')) {
            return null;
        }

        try {
            $resolved = (new $class)->resolve();
        } catch (\Throwable) {
            return null;
        }

        return is_array($resolved) ? $resolved : null;
    }
}
