<div class="mx-2">
    <!-- K9S STYLE HEADER -->
    <div class="flex justify-between">
        <div class="flex flex-col">
            <div class="flex"><span class="text-cyan-400 font-bold w-12">Jobs:</span><span class="text-white">{{ number_format($stats['total_jobs'] ?? 0) }}</span></div>
            <div class="flex"><span class="text-cyan-400 font-bold w-12">Rate:</span><span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-400' : 'text-red-400' }}">{{ number_format($stats['success_rate'] ?? 0, 1) }}%</span></div>
            <div class="flex"><span class="text-cyan-400 font-bold w-12">Avg:</span><span class="text-blue-400">{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span></div>
        </div>
        <div class="text-right">
            <div class="flex justify-end"><span class="text-gray-500 mr-2">Context:</span><span class="text-white">{{ config('app.env') }}</span></div>
            <div class="flex justify-end"><span class="text-gray-500 mr-2">Server:</span><span class="text-yellow-400">{{ gethostname() }}</span></div>
            <div class="flex justify-end"><span class="text-gray-500 mr-2">Time:</span><span class="text-white">{{ $timestamp }}</span></div>
        </div>
    </div>

    <!-- TABLE HEADER -->
    <div class="mt-1 bg-gray-800 px-1 flex">
        <span class="w-16 text-white font-bold">STATUS</span>
        <span class="w-12 text-white font-bold">TYPE</span>
        <span class="flex-1 text-white font-bold ml-2">JOB CLASS</span>
        <span class="w-24 text-white font-bold">QUEUE</span>
        <span class="w-12 text-white font-bold text-right">DUR</span>
        <span class="w-12 text-white font-bold text-right">AGE</span>
    </div>

    <!-- TABLE BODY -->
    <div class="flex flex-col">
        @foreach($recentJobs as $job)
            <div class="px-1 flex">
                <span class="w-16">
                    @if($job->status->isSuccessful())
                        <span class="text-green-500">● Done</span>
                    @elseif($job->status->isFailed())
                        <span class="text-red-500">● Fail</span>
                    @elseif($job->status->value === 'processing')
                        <span class="text-yellow-500">● Run</span>
                    @else
                        <span class="text-blue-500">○ Wait</span>
                    @endif
                </span>
                <span class="w-12">
                    @if($job->worker_type->isHorizon())
                        <span class="text-purple-400">HORZ</span>
                    @elseif($job->worker_type->isAutoscale())
                        <span class="text-cyan-400">AUTO</span>
                    @else
                        <span class="text-gray-400">WORK</span>
                    @endif
                </span>
                <span class="flex-1 text-white ml-2 truncate">{{ $job->getShortJobClass() }}</span>
                <span class="w-24 text-gray-500 truncate">{{ $job->queue }}</span>
                <span class="w-12 text-right {{ $job->duration_ms > 1000 ? 'text-yellow-400' : 'text-gray-500' }}">
                    {{ number_format($job->duration_ms ?? 0) }}
                </span>
                <span class="w-12 text-right text-gray-600">
                    {{ $job->created_at->diffForHumans(null, true, true) }}
                </span>
            </div>
        @endforeach
    </div>

    <!-- FAILURES -->
    @if($failedJobs->isNotEmpty())
        <div class="mt-1 pt-1">
            <div class="text-red-500 font-bold mb-1 uppercase underline">Recent Failures</div>
            @foreach($failedJobs as $job)
                <div class="flex space-x-2 text-red-400 px-1">
                    <span class="font-bold">ERR</span>
                    <span class="truncate">{{ $job->getShortJobClass() }}: {{ Str::limit($job->exception_message, 100) }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- K9S STYLE FOOTER -->
    <div class="mt-1 flex space-x-4 bg-gray-800 px-2 py-0">
        <div class="flex"><span class="text-cyan-400 font-bold mr-1">^C</span><span class="text-gray-300">Quit</span></div>
        <div class="flex"><span class="text-cyan-400 font-bold mr-1">R</span><span class="text-gray-300">Replay</span></div>
        <div class="flex"><span class="text-cyan-400 font-bold mr-1">D</span><span class="text-gray-300">Delete</span></div>
        <div class="flex"><span class="text-cyan-400 font-bold mr-1">P</span><span class="text-gray-300">Prune</span></div>
    </div>
</div>
