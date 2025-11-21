<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplayJobRequest extends FormRequest
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
            //
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // UUID comes from route parameter, validate format
        $uuid = $this->route('uuid');

        if (! is_string($uuid) || ! \Illuminate\Support\Str::isUuid($uuid)) {
            abort(422, 'Invalid UUID format');
        }
    }
}
