{{-- Job Detail View --}}
@php
    $statusColor = match($job->status) {
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::COMPLETED => 'text-green-400',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::FAILED, \Cbox\LaravelQueueMonitor\Enums\JobStatus::TIMEOUT => 'text-red-400',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::PROCESSING => 'text-yellow-400',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::QUEUED => 'text-blue-400',
        default => 'text-gray-400',
    };
    $statusIcon = match($job->status) {
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::COMPLETED => '✓',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::FAILED => '✗',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::TIMEOUT => '⏰',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::PROCESSING => '⟳',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::QUEUED => '◦',
        \Cbox\LaravelQueueMonitor\Enums\JobStatus::CANCELLED => '⊘',
        default => '?',
    };
    $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);
@endphp

{{-- Header --}}
<div class="mt-1 bg-gray-800 px-1 flex">
    <span class="{{ $statusColor }} font-bold mr-2">{{ $statusIcon }} {{ $job->status->label() }}</span>
    <span class="text-white font-bold flex-1">{{ $job->getShortJobClass() }}</span>
    <span class="text-gray-400">{{ $job->uuid }}</span>
</div>

{{-- Core info --}}
<div class="flex flex-col px-1 mt-1">
    <div class="flex">
        <span class="text-cyan-400 font-bold w-16">Class</span>
        <span class="text-white">{{ $job->job_class }}</span>
    </div>
    @if($job->display_name && $job->display_name !== $job->job_class)
        <div class="flex">
            <span class="text-cyan-400 font-bold w-16">Name</span>
            <span class="text-gray-400">{{ $job->display_name }}</span>
        </div>
    @endif
    <div class="flex">
        <span class="text-cyan-400 font-bold w-16">Queue</span>
        <span class="text-white">{{ $job->queue }}</span>
        <span class="text-gray-500 ml-2">on {{ $job->connection }}</span>
    </div>
    <div class="flex">
        <span class="text-cyan-400 font-bold w-16">Server</span>
        <span class="text-white">{{ $job->server_name }}</span>
        <span class="text-gray-500 ml-2">({{ $job->worker_type->label() }})</span>
    </div>
    <div class="flex">
        <span class="text-cyan-400 font-bold w-16">Worker</span>
        <span class="text-gray-400">{{ $job->worker_id }}</span>
    </div>
    <div class="flex">
        <span class="text-cyan-400 font-bold w-16">Attempt</span>
        <span class="text-white">{{ $job->attempt }} / {{ $job->max_attempts }}</span>
        @if($job->isRetryable())
            <span class="text-yellow-400 ml-2">(retryable)</span>
        @endif
    </div>
    @if($job->tags && count($job->tags) > 0)
        <div class="flex">
            <span class="text-cyan-400 font-bold w-16">Tags</span>
            <span class="text-blue-300">{{ implode(', ', array_map(fn($t) => '#' . $t, $job->tags)) }}</span>
        </div>
    @endif
</div>

{{-- Timestamps --}}
<div class="mt-1 bg-gray-800 px-1">
    <span class="text-white font-bold">Timestamps</span>
</div>
<div class="flex flex-col px-1">
    <div class="flex">
        <span class="text-cyan-400 w-16">Queued</span>
        <span class="text-white">{{ $job->queued_at->format('Y-m-d H:i:s') }}</span>
        <span class="text-gray-500 ml-2">({{ $job->queued_at->diffForHumans() }})</span>
    </div>
    @if($job->available_at)
        <div class="flex">
            <span class="text-cyan-400 w-16">Available</span>
            <span class="text-white">{{ $job->available_at->format('Y-m-d H:i:s') }}</span>
            @if($job->queued_at->ne($job->available_at))
                <span class="text-gray-500 ml-2">(+{{ number_format($job->queued_at->diffInMilliseconds($job->available_at)) }}ms delay)</span>
            @endif
        </div>
    @endif
    @if($job->started_at)
        <div class="flex">
            <span class="text-cyan-400 w-16">Started</span>
            <span class="text-white">{{ $job->started_at->format('Y-m-d H:i:s') }}</span>
            <span class="text-gray-500 ml-2">(+{{ number_format($job->queued_at->diffInMilliseconds($job->started_at)) }}ms wait)</span>
        </div>
    @endif
    @if($job->completed_at)
        <div class="flex">
            <span class="text-cyan-400 w-16">Finished</span>
            <span class="text-white">{{ $job->completed_at->format('Y-m-d H:i:s') }}</span>
            <span class="text-gray-500 ml-2">({{ number_format($job->queued_at->diffInMilliseconds($job->completed_at)) }}ms total)</span>
        </div>
    @endif
</div>

{{-- Metrics --}}
<div class="mt-1 bg-gray-800 px-1">
    <span class="text-white font-bold">Metrics</span>
</div>
<div class="flex px-1">
    <span class="text-cyan-400 font-bold mr-1">Duration:</span>
    <span class="{{ ($job->duration_ms ?? 0) > 5000 ? 'text-red-400' : (($job->duration_ms ?? 0) > 1000 ? 'text-yellow-400' : 'text-white') }} mr-3">{{ $job->duration_ms !== null ? number_format($job->duration_ms) . 'ms' : '-' }}</span>
    @if($job->cpu_time_ms !== null && $job->duration_ms)
        @php $cpuPct = ($job->cpu_time_ms / $job->duration_ms) * 100; @endphp
        <span class="text-cyan-400 font-bold mr-1">CPU:</span>
        <span class="text-white mr-3">{{ $cpuPct < 1 ? '<1%' : round($cpuPct) . '%' }}</span>
    @endif
    <span class="text-cyan-400 font-bold mr-1">Memory:</span>
    <span class="text-white mr-3">{{ $job->memory_peak_mb !== null ? number_format((float)$job->memory_peak_mb, 2) . 'MB' : '-' }}</span>
    @if($job->file_descriptors !== null)
        <span class="text-cyan-400 font-bold mr-1">FDs:</span>
        <span class="text-white">{{ $job->file_descriptors }}</span>
    @endif
</div>

{{-- Exception (if failed) --}}
@if($job->isFailed() && $job->exception_class)
    <div class="mt-1 bg-red-800 px-1">
        <span class="text-white font-bold">Exception</span>
    </div>
    <div class="flex flex-col px-1">
        <div class="flex">
            <span class="text-red-400 font-bold">{{ $job->getShortExceptionClass() }}</span>
        </div>
        <div class="flex">
            <span class="text-red-300">{{ Str::limit($job->exception_message ?? '', 200) }}</span>
        </div>
        @if($job->exception_trace)
            @php
                $trace = \Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor::redactTrace($job->exception_trace);
                $traceLines = array_slice(explode("\n", $trace ?? ''), 0, 6);
            @endphp
            @foreach($traceLines as $line)
                <div>
                    <span class="text-gray-500">{{ Str::limit(trim($line), 120) }}</span>
                </div>
            @endforeach
        @endif
    </div>
@endif

{{-- Payload --}}
@if($job->payload)
    <div class="mt-1 bg-gray-800 px-1">
        <span class="text-white font-bold">Payload</span>
    </div>
    @php
        $redacted = \Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor::redact($job->payload, $sensitiveKeys);
        $payloadJson = json_encode($redacted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $payloadLines = array_slice(explode("\n", $payloadJson ?: '{}'), 0, 12);
    @endphp
    @foreach($payloadLines as $line)
        <div class="px-1">
            <span class="text-gray-400">{{ Str::limit($line, 120) }}</span>
        </div>
    @endforeach
    @if(count(explode("\n", $payloadJson ?: '')) > 12)
        <div class="px-1">
            <span class="text-gray-600">... ({{ count(explode("\n", $payloadJson ?: '')) - 12 }} more lines)</span>
        </div>
    @endif
@endif

{{-- Retry Chain --}}
@if($retryChain->count() > 1)
    <div class="mt-1 bg-gray-800 px-1">
        <span class="text-white font-bold">Retry Chain ({{ $retryChain->count() }} attempts)</span>
    </div>
    @foreach($retryChain as $attempt)
        @php
            $isCurrent = $attempt->uuid === $job->uuid;
            $attemptColor = match($attempt->status) {
                \Cbox\LaravelQueueMonitor\Enums\JobStatus::COMPLETED => 'text-green-400',
                \Cbox\LaravelQueueMonitor\Enums\JobStatus::FAILED, \Cbox\LaravelQueueMonitor\Enums\JobStatus::TIMEOUT => 'text-red-400',
                \Cbox\LaravelQueueMonitor\Enums\JobStatus::PROCESSING => 'text-yellow-400',
                default => 'text-gray-400',
            };
        @endphp
        <div class="px-1 flex {{ $isCurrent ? 'bg-blue-800' : '' }}">
            <span class="w-4 {{ $attemptColor }}">{{ $isCurrent ? '▸' : ' ' }}</span>
            <span class="w-8 text-gray-400">#{{ $attempt->attempt }}</span>
            <span class="w-10 {{ $attemptColor }}">{{ $attempt->status->label() }}</span>
            <span class="w-10 text-gray-400">{{ $attempt->duration_ms !== null ? number_format($attempt->duration_ms) . 'ms' : '-' }}</span>
            <span class="text-gray-500">{{ $attempt->server_name ?? '' }}</span>
            @if($attempt->exception_message)
                <span class="text-red-300 ml-2 truncate">{{ Str::limit($attempt->exception_message, 60) }}</span>
            @endif
        </div>
    @endforeach
@endif

{{-- Footer --}}
<div class="mt-1 flex px-1 bg-gray-800">
    <span class="text-cyan-400 font-bold mr-1">Esc/B</span><span class="text-gray-400 mr-2">Back</span>
    @if($job->isFailed())
        <span class="text-cyan-400 font-bold mr-1">R</span><span class="text-gray-400 mr-2">Replay</span>
    @endif
    <span class="text-cyan-400 font-bold mr-1">Q</span><span class="text-gray-400">Quit</span>
</div>
