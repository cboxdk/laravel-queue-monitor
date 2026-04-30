<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Eloquent;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\Tag;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTagRepository implements TagRepositoryContract
{
    public function storeTags(int $jobId, array $tags): void
    {
        $records = array_map(
            fn (string $tag) => [
                'job_id' => $jobId,
                'tag' => $tag,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            $tags
        );

        if (! empty($records)) {
            Tag::insert($records);
        }
    }

    public function getAllTags(): Collection
    {
        /** @var Collection<int, string> $tags */
        $tags = Tag::distinct()
            ->pluck('tag')
            ->sort()
            ->values();

        return $tags;
    }

    /**
     * @return Collection<int, array{tag: string, count: int, successful_count: int, success_rate: float}>
     */
    public function getTagStatistics(): Collection
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        return DB::table($prefix.'tags as t')
            ->join($prefix.'jobs as j', 't.job_id', '=', 'j.id')
            ->select([
                't.tag',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN j.status = ? THEN 1 ELSE 0 END) as successful_count'),
                DB::raw('CASE WHEN SUM(CASE WHEN j.status IN (?, ?, ?) THEN 1 ELSE 0 END) > 0 THEN ROUND((SUM(CASE WHEN j.status = ? THEN 1 ELSE 0 END) * 100.0) / SUM(CASE WHEN j.status IN (?, ?, ?) THEN 1 ELSE 0 END), 2) ELSE 0 END as success_rate'),
            ])
            ->addBinding([
                JobStatus::COMPLETED->value,
                JobStatus::COMPLETED->value, JobStatus::FAILED->value, JobStatus::TIMEOUT->value,
                JobStatus::COMPLETED->value,
                JobStatus::COMPLETED->value, JobStatus::FAILED->value, JobStatus::TIMEOUT->value,
            ])
            ->groupBy('t.tag')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'tag' => (string) $row->tag,
                'count' => (int) $row->count,
                'successful_count' => (int) $row->successful_count,
                'success_rate' => (float) $row->success_rate,
            ]);
    }

    public function getJobIdsWithTag(string $tag): Collection
    {
        /** @var Collection<int, int> $jobIds */
        $jobIds = Tag::where('tag', $tag)
            ->pluck('job_id');

        return $jobIds;
    }
}
