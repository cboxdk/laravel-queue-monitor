<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Contracts;

use Illuminate\Support\Collection;

interface TagRepositoryContract
{
    /**
     * Store tags for a job
     *
     * @param  array<string>  $tags
     */
    public function storeTags(int $jobId, array $tags): void;

    /**
     * Get all unique tags
     *
     * @return Collection<int, string>
     */
    public function getAllTags(): Collection;

    /**
     * Get tag statistics
     *
     * @return Collection<int, array{tag: string, count: int, success_rate: float}>
     */
    public function getTagStatistics(): Collection;

    /**
     * Get jobs with a specific tag
     *
     * @return Collection<int, int>
     */
    public function getJobIdsWithTag(string $tag): Collection;
}
