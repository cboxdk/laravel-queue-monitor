<div class="mx-2 my-1">
    <div class="flex justify-between text-white bg-blue-600 px-2 py-1 font-bold">
        <span>Laravel Queue Monitor</span>
        <span>{{ $timestamp }}</span>
    </div>

    <div class="flex space-x-2 mt-1">
        <!-- Global Stats -->
        <div class="flex-1 border border-gray-600 p-1">
            <div class="text-blue-400 font-bold mb-1">Global Statistics</div>
            <div class="flex justify-between">
                <span>Total Jobs:</span>
                <span class="font-bold">{{ number_format($stats['total_jobs'] ?? 0) }}</span>
            </div>
            <div class="flex justify-between">
                <span>Success Rate:</span>
                <span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-500' : 'text-red-500' }}">
                    {{ number_format($stats['success_rate'] ?? 0, 1) }}%
                </span>
            </div>
            <div class="flex justify-between">
                <span>Avg Duration:</span>
                <span>{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span>
            </div>
        </div>

        <!-- Queue Health -->
        <div class="flex-1 border border-gray-600 p-1">
            <div class="text-blue-400 font-bold mb-1">Queue Health</div>
            @if(empty($queues))
                <div class="text-gray-500 italic">No active queues found</div>
            @else
                @foreach($queues as $queue)
                    <div class="flex justify-between">
                        <span>{{ $queue['queue'] }}</span>
                        <span class="px-1 {{ $queue['status'] === 'healthy' ? 'bg-green-600' : 'bg-red-600' }}">
                            {{ $queue['status'] }}
                        </span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Recent Jobs -->
    <div class="mt-1 border border-gray-600 p-1">
        <div class="text-blue-400 font-bold mb-1">Recent Activity</div>
        @if($recentJobs->isEmpty())
            <div class="text-gray-500 italic">No recent jobs</div>
        @else
            <div class="flex text-gray-400 border-b border-gray-700 pb-1 mb-1">
                <span class="w-20">Status</span>
                <span class="w-40">Job</span>
                <span class="flex-1">Queue</span>
                <span class="w-20 text-right">Duration</span>
                <span class="w-20 text-right">Time</span>
            </div>
            @foreach($recentJobs as $job)
                <div class="flex">
                    <span class="w-20 font-bold" style="color: {{ $job->status->color() }}">
                        {{ $job->status->label() }}
                    </span>
                    <span class="w-40 truncate">{{ $job->getShortJobClass() }}</span>
                    <span class="flex-1 text-gray-500">{{ $job->queue }}</span>
                    <span class="w-20 text-right">{{ number_format($job->duration_ms ?? 0) }}ms</span>
                    <span class="w-20 text-right text-gray-500">
                        {{ $job->updated_at->format('H:i:s') }}
                    </span>
                </div>
            @endforeach
        @endif
    </div>

    <!-- Recent Failures -->
    @if($failedJobs->isNotEmpty())
        <div class="mt-1 border border-red-600 p-1">
            <div class="text-red-500 font-bold mb-1">Recent Failures</div>
            @foreach($failedJobs as $job)
                <div class="flex space-x-2">
                    <span class="text-red-500 font-bold">{{ $job->getShortJobClass() }}</span>
                    <span class="text-gray-400 truncate">{{ $job->exception_message }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 text-gray-500 text-center">
        Press <span class="text-white">Ctrl+C</span> to exit
    </div>
</div>
