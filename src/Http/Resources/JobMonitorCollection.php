<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class JobMonitorCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects
     */
    public $collects = JobMonitorResource::class;

    /**
     * Transform the resource collection into an array
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
            ],
        ];
    }
}
