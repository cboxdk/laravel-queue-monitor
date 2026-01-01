<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;

/**
 * Deferred job for storing normalized tags.
 *
 * Used when queue-monitor.storage.deferred_tags is enabled
 * to avoid blocking the main job completion flow.
 */
final class StoreJobTagsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string>  $tags
     */
    public function __construct(
        public readonly int $jobMonitorId,
        public readonly array $tags,
    ) {
        // Use a separate queue to avoid blocking main queue
        $this->onQueue('queue-monitor-tags');
    }

    /**
     * Execute the job
     */
    public function handle(TagRepositoryContract $tagRepository): void
    {
        $tagRepository->storeTags($this->jobMonitorId, $this->tags);
    }
}
