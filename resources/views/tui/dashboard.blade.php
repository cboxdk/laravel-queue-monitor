<div class="mx-2">
    {{-- HEADER BAR --}}
    <div class="flex px-1 bg-gray-800">
        <span class="text-cyan-400 font-bold">Queue Monitor</span>
        <span class="flex-1"></span>
        <span class="{{ $healthy ? 'text-green-400' : 'text-red-400' }} font-bold mr-2">{{ $healthy ? 'Healthy' : 'Degraded' }}</span>
        <span class="text-gray-400">{{ $timestamp }}</span>
    </div>

    {{-- STATS BAR --}}
    <div class="flex px-1 mt-1">
        <span class="text-cyan-400 font-bold mr-1">Jobs:</span>
        <span class="text-white mr-2">{{ number_format($stats['total_jobs'] ?? 0) }}</span>
        <span class="text-cyan-400 font-bold mr-1">Success:</span>
        <span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-400' : 'text-yellow-400' }} mr-2">{{ number_format($stats['success_rate'] ?? 0, 1) }}%</span>
        <span class="text-cyan-400 font-bold mr-1">Failed:</span>
        <span class="text-red-400 mr-2">{{ $stats['failed_jobs'] ?? 0 }}</span>
        <span class="text-cyan-400 font-bold mr-1">Avg:</span>
        <span class="text-blue-400 mr-2">{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span>
        <span class="text-cyan-400 font-bold mr-1">Processing:</span>
        <span class="text-yellow-400">{{ $stats['processing'] ?? 0 }}</span>
    </div>

    {{-- FILTER/SEARCH INDICATOR --}}
    @if($statusFilter || $inSearchMode || $searchQuery)
        <div class="flex px-1 mt-1">
            @if($statusFilter)
                <span class="text-yellow-400 font-bold mr-2">[Filter: {{ ucfirst($statusFilter) }}]</span>
            @endif
            @if($inSearchMode)
                <span class="text-green-400 font-bold mr-1">Search:</span>
                <span class="text-white">{{ $searchQuery }}_</span>
            @elseif($searchQuery)
                <span class="text-gray-400 mr-1">Search:</span>
                <span class="text-white">{{ $searchQuery }}</span>
            @endif
        </div>
    @endif

    @if($currentView === 1)
        {{-- VIEW 1: JOBS TABLE --}}
        <div class="mt-1 bg-gray-800 px-1 flex">
            <span class="w-10 text-white font-bold">STATUS</span>
            <span class="flex-1 text-white font-bold ml-1">JOB CLASS</span>
            <span class="w-16 text-white font-bold">QUEUE</span>
            <span class="w-12 text-white font-bold">DURATION</span>
            <span class="w-10 text-white font-bold">TIME</span>
        </div>

        <div class="flex flex-col">
            @forelse($jobs as $index => $job)
                <div class="px-1 flex {{ $index === $selectedIndex ? 'bg-blue-800' : '' }}">
                    <span class="w-10">
                        @if($job->status->isSuccessful())
                            <span class="text-green-500">Done</span>
                        @elseif($job->status->isFailed())
                            <span class="text-red-500">Fail</span>
                        @elseif($job->status->isProcessing())
                            <span class="text-yellow-500">Run</span>
                        @else
                            <span class="text-blue-500">Wait</span>
                        @endif
                    </span>
                    <span class="flex-1 text-white ml-1 truncate">{{ $job->getShortJobClass() }}@if($job->attempt > 1) <span class="text-yellow-400">×{{ $job->attempt }}</span>@endif</span>
                    <span class="w-16 text-gray-400 truncate">{{ $job->queue }}</span>
                    <span class="w-12 {{ ($job->duration_ms ?? 0) > 1000 ? 'text-yellow-400' : 'text-gray-500' }}">
                        {{ $job->duration_ms !== null ? number_format($job->duration_ms) . 'ms' : '-' }}
                    </span>
                    <span class="w-10 text-gray-600">
                        @if($job->queued_at)
                            {{ $job->queued_at->diffForHumans(null, true, true) }}
                        @else
                            -
                        @endif
                    </span>
                </div>
            @empty
                <div class="px-1 text-gray-500">No jobs found.</div>
            @endforelse
        </div>

    @elseif($currentView === 2)
        {{-- VIEW 2: STATISTICS --}}
        <div class="mt-1 bg-gray-800 px-1">
            <span class="text-white font-bold">Statistics Overview</span>
        </div>
        <div class="flex flex-col px-1 mt-1">
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Total Jobs</span>
                <span class="text-white">{{ number_format($stats['total_jobs'] ?? 0) }}</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Completed</span>
                <span class="text-green-400">{{ number_format($stats['completed_jobs'] ?? 0) }}</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Failed</span>
                <span class="text-red-400">{{ number_format($stats['failed_jobs'] ?? 0) }}</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Processing</span>
                <span class="text-yellow-400">{{ number_format($stats['processing'] ?? 0) }}</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Success Rate</span>
                <span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-400' : 'text-yellow-400' }}">{{ number_format($stats['success_rate'] ?? 0, 2) }}%</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Avg Duration</span>
                <span class="text-blue-400">{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Max Duration</span>
                <span class="text-blue-400">{{ number_format($stats['max_duration_ms'] ?? 0, 0) }}ms</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-24">Avg Memory</span>
                <span class="text-blue-400">{{ number_format($stats['avg_memory_mb'] ?? 0, 2) }}MB</span>
            </div>
        </div>

    @elseif($currentView === 3)
        {{-- VIEW 3: QUEUES --}}
        <div class="mt-1 bg-gray-800 px-1 flex">
            <span class="w-20 text-white font-bold">QUEUE</span>
            <span class="w-12 text-white font-bold">TOTAL</span>
            <span class="w-12 text-white font-bold">ACTIVE</span>
            <span class="w-12 text-white font-bold">FAILED</span>
            <span class="w-12 text-white font-bold">AVG MS</span>
            <span class="w-12 text-white font-bold">HEALTH</span>
        </div>
        <div class="flex flex-col">
            @forelse($queues as $index => $queue)
                <div class="px-1 flex {{ $index === $selectedIndex ? 'bg-blue-800' : '' }}">
                    <span class="w-20 text-white truncate">{{ $queue['queue'] ?? '-' }}</span>
                    <span class="w-12 text-gray-400">{{ $queue['total_last_hour'] ?? 0 }}</span>
                    <span class="w-12 text-yellow-400">{{ $queue['processing'] ?? 0 }}</span>
                    <span class="w-12 text-red-400">{{ $queue['failed'] ?? 0 }}</span>
                    <span class="w-12 text-blue-400">{{ number_format($queue['avg_duration_ms'] ?? 0, 0) }}</span>
                    <span class="w-12 {{ ($queue['status'] ?? '') === 'healthy' ? 'text-green-400' : 'text-red-400' }}">{{ $queue['status'] ?? '-' }}</span>
                </div>
            @empty
                <div class="px-1 text-gray-500">No queue data available.</div>
            @endforelse
        </div>

    @elseif($currentView === 4)
        {{-- VIEW 4: HEALTH --}}
        <div class="mt-1 bg-gray-800 px-1">
            <span class="text-white font-bold">System Health</span>
        </div>
        <div class="flex flex-col px-1 mt-1">
            <div class="flex">
                <span class="text-cyan-400 font-bold w-20">Overall</span>
                <span class="{{ $healthy ? 'text-green-400' : 'text-red-400' }} font-bold">{{ $healthy ? 'HEALTHY' : 'DEGRADED' }}</span>
            </div>
            <div class="flex mt-1">
                <span class="text-cyan-400 font-bold w-20">Queues</span>
                <span class="text-white">{{ count($queues) }} active</span>
            </div>
            @foreach($queues as $queue)
                <div class="flex ml-2">
                    <span class="w-18 text-gray-400">{{ $queue['queue'] ?? '-' }}</span>
                    <span class="{{ ($queue['status'] ?? '') === 'healthy' ? 'text-green-400' : (($queue['status'] ?? '') === 'degraded' ? 'text-yellow-400' : 'text-red-400') }}">
                        {{ $queue['status'] ?? 'unknown' }}
                    </span>
                    <span class="text-gray-500 ml-1">({{ number_format($queue['health_score'] ?? 0, 0) }}%)</span>
                </div>
            @endforeach
            <div class="flex mt-1">
                <span class="text-cyan-400 font-bold w-20">Jobs/hr</span>
                <span class="text-white">{{ number_format($stats['total_jobs'] ?? 0) }} total</span>
            </div>
            <div class="flex">
                <span class="text-cyan-400 font-bold w-20">Failure Rate</span>
                <span class="{{ ($stats['failure_rate'] ?? 0) < 5 ? 'text-green-400' : 'text-red-400' }}">{{ number_format($stats['failure_rate'] ?? 0, 2) }}%</span>
            </div>
        </div>
    @endif

    {{-- VIEW TABS --}}
    <div class="mt-1 flex px-1 bg-gray-800">
        <span class="{{ $currentView === 1 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[1] Jobs</span>
        <span class="{{ $currentView === 2 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[2] Stats</span>
        <span class="{{ $currentView === 3 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[3] Queues</span>
        <span class="{{ $currentView === 4 ? 'text-white font-bold' : 'text-gray-500' }}">[4] Health</span>
    </div>

    {{-- FOOTER / KEYBINDINGS --}}
    <div class="flex px-1">
        <span class="text-cyan-400 font-bold mr-1">jk</span><span class="text-gray-400 mr-2">Nav</span>
        <span class="text-cyan-400 font-bold mr-1">R</span><span class="text-gray-400 mr-2">Replay</span>
        <span class="text-cyan-400 font-bold mr-1">S</span><span class="text-gray-400 mr-2">Status</span>
        <span class="text-cyan-400 font-bold mr-1">F</span><span class="text-gray-400 mr-2">Filter</span>
        <span class="text-cyan-400 font-bold mr-1">Q</span><span class="text-gray-400">Quit</span>
    </div>
</div>
