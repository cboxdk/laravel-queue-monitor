<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

final class JobPayloadSerializer
{
    /**
     * Serialize job instance to payload array
     *
     * @return array<string, mixed>
     */
    public static function serialize(object $jobInstance): array
    {
        return [
            'displayName' => self::getDisplayName($jobInstance),
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => self::getMaxTries($jobInstance),
            'maxExceptions' => self::getMaxExceptions($jobInstance),
            'timeout' => self::getTimeout($jobInstance),
            'data' => [
                'commandName' => $jobInstance::class,
                'command' => serialize($jobInstance),
            ],
        ];
    }

    /**
     * Deserialize payload to job instance
     *
     * Security: Only allows deserialization of the specific class declared in the payload.
     * This prevents object injection attacks where a malicious serialized payload could
     * instantiate arbitrary classes.
     */
    public static function deserialize(array $payload): ?object
    {
        if (! isset($payload['data']['command'])) {
            return null;
        }

        // Get the expected class name from the payload
        $expectedClass = $payload['data']['commandName'] ?? null;

        if ($expectedClass === null || ! class_exists($expectedClass)) {
            return null;
        }

        try {
            // Security: Only allow the specific expected class to be deserialized
            // This prevents object injection attacks via crafted serialized data
            $command = unserialize(
                $payload['data']['command'],
                ['allowed_classes' => [$expectedClass]]
            );

            // Verify the deserialized object is actually the expected class
            if (! is_object($command) || ! $command instanceof $expectedClass) {
                return null;
            }

            return $command;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get display name from job
     */
    private static function getDisplayName(object $jobInstance): ?string
    {
        if (method_exists($jobInstance, 'displayName')) {
            return $jobInstance->displayName();
        }

        return null;
    }

    /**
     * Get max tries from job
     */
    private static function getMaxTries(object $jobInstance): int
    {
        if (property_exists($jobInstance, 'tries')) {
            return is_int($jobInstance->tries) ? $jobInstance->tries : 1;
        }

        return 1;
    }

    /**
     * Get max exceptions from job
     */
    private static function getMaxExceptions(object $jobInstance): ?int
    {
        if (property_exists($jobInstance, 'maxExceptions')) {
            return is_int($jobInstance->maxExceptions) ? $jobInstance->maxExceptions : null;
        }

        return null;
    }

    /**
     * Get timeout from job
     */
    private static function getTimeout(object $jobInstance): ?int
    {
        if (property_exists($jobInstance, 'timeout')) {
            return is_int($jobInstance->timeout) ? $jobInstance->timeout : null;
        }

        return null;
    }

    /**
     * Extract tags from job
     *
     * @return array<string>|null
     */
    public static function extractTags(object $jobInstance): ?array
    {
        if (! method_exists($jobInstance, 'tags')) {
            return null;
        }

        $tags = $jobInstance->tags();

        if (! is_array($tags)) {
            return null;
        }

        // Ensure all tags are strings and non-empty
        return array_values(array_filter(
            array_map(fn (mixed $tag): string => is_string($tag) ? $tag : (string) $tag, $tags),
            fn (string $tag): bool => $tag !== ''
        ));
    }

    /**
     * Get queue name from job
     */
    public static function getQueue(object $jobInstance): string
    {
        if (property_exists($jobInstance, 'queue') && is_string($jobInstance->queue)) {
            return $jobInstance->queue;
        }

        return 'default';
    }

    /**
     * Check if payload size exceeds limit
     */
    public static function exceedsSizeLimit(array $payload): bool
    {
        $maxSize = config('queue-monitor.storage.payload_max_size', 65535);
        $size = strlen(json_encode($payload) ?: '');

        return $size > $maxSize;
    }
}
