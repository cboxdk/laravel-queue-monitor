<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Requests;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Illuminate\Foundation\Http\FormRequest;

class ListJobsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statuses' => 'sometimes|array',
            'statuses.*' => 'string|in:'.implode(',', JobStatus::values()),
            'queues' => 'sometimes|array',
            'queues.*' => 'string',
            'connection' => 'sometimes|string',
            'job_classes' => 'sometimes|array',
            'job_classes.*' => 'string',
            'server_names' => 'sometimes|array',
            'server_names.*' => 'string',
            'worker_id' => 'sometimes|string',
            'worker_type' => 'sometimes|string|in:'.implode(',', WorkerType::values()),
            'tags' => 'sometimes|array',
            'tags.*' => 'sometimes|string',
            'queued_after' => 'sometimes|date',
            'queued_before' => 'sometimes|date',
            'started_after' => 'sometimes|date',
            'started_before' => 'sometimes|date',
            'completed_after' => 'sometimes|date',
            'completed_before' => 'sometimes|date',
            'min_duration_ms' => 'sometimes|integer|min:0',
            'max_duration_ms' => 'sometimes|integer|min:0',
            'search' => 'sometimes|string|max:255',
            'limit' => 'sometimes|integer|min:1|max:1000',
            'offset' => 'sometimes|integer|min:0',
            'sort_by' => 'sometimes|string|in:queued_at,started_at,completed_at,duration_ms,created_at',
            'sort_direction' => 'sometimes|string|in:asc,desc',
        ];
    }

    /**
     * Get custom error messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statuses.*.in' => 'Invalid job status. Must be one of: '.implode(', ', JobStatus::values()),
            'worker_type.in' => 'Invalid worker type. Must be one of: '.implode(', ', WorkerType::values()),
            'limit.max' => 'Maximum limit is 1000 jobs per request',
            'sort_by.in' => 'Invalid sort field. Must be one of: queued_at, started_at, completed_at, duration_ms, created_at',
        ];
    }
}
