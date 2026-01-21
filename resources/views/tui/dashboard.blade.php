<div class="mx-2 my-1">
    <!-- Header Bar -->
    <div class="flex justify-between text-black bg-cyan-400 px-2 font-bold uppercase">
        <span><span class="mr-1">⚡</span> Laravel Queue Monitor</span>
        <span>{{ $timestamp }}</span>
    </div>

    <!-- Summary Row -->
    <div class="mt-1 flex space-x-4 px-1">
        <div>
            <span class="text-gray-500">TOTAL:</span>
            <span class="font-bold text-white">{{ number_format($stats['total_jobs'] ?? 0) }}</span>
        </div>
        <div>
            <span class="text-gray-500">SUCCESS:</span>
            <span class="font-bold text-green-400">{{ number_format($stats['success_rate'] ?? 0, 1) }}%</span>
        </div>
        <div>
            <span class="text-gray-500">AVG:</span>
            <span class="font-bold text-blue-400">{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span>
        </div>
    </div>

    <div class="mt-1 flex space-x-2">
        <!-- Queue Status Column -->
        <div class="flex-1">
            <div class="text-cyan-400 font-bold mb-1 uppercase">● Queue Health</div>
            @if(empty($queues))
                <div class="text-gray-600 italic">No active queues</div>
            @else
                @foreach($queues as $queue)
                    <div class="flex justify-between px-2 mb-1">
                        <span class="text-gray-300">{{ $queue['queue'] }}</span>
                        <span class="{{ $queue['status'] === 'healthy' ? 'text-green-500' : 'text-red-500' }} font-bold">
                            {{ strtoupper($queue['status']) }}
                        </span>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="flex-1"></div>
    </div>

    <!-- Recent Activity Table -->
    <div class="mt-1">
        <div class="text-cyan-400 font-bold mb-1 uppercase">● Recent Activity</div>
        @if($recentJobs->isEmpty())
            <div class="text-gray-600 italic px-1">Waiting for jobs...</div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="text-gray-500 font-bold">
                        <th class="text-left w-12">TYPE</th>
                        <th class="text-left">JOB CLASS</th>
                        <th class="text-left">QUEUE</th>
                        <th class="text-right w-12">TIME</th>
                        <th class="text-right w-12">MS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentJobs as $job)
                        <tr>
                            <td>
                                @if($job->worker_type->isHorizon())
                                    <span class="text-purple-500 font-bold">HORZ</span>
                                @elseif($job->worker_type->isAutoscale())
                                    <span class="text-cyan-500 font-bold">AUTO</span>
                                @else
                                    <span class="text-gray-500">WORK</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $job->status->isFailed() ? 'text-red-500' : 'text-white' }}">
                                    {{ $job->getShortJobClass() }}
                                </span>
                            </td>
                            <td class="text-gray-500">{{ $job->queue }}</td>
                            <td class="text-right text-gray-400">
                                {{ $job->updated_at ? $job->updated_at->format('H:i:s') : '--:--' }}
                            </td>
                            <td class="text-right {{ $job->duration_ms > 1000 ? 'text-yellow-500' : 'text-gray-500' }}">
                                {{ number_format($job->duration_ms ?? 0) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <!-- Failed Jobs (Compact) -->
    @if($failedJobs->isNotEmpty())
        <div class="mt-1">
            <div class="text-red-500 font-bold mb-1 uppercase">● Recent Failures</div>
            @foreach($failedJobs as $job)
                <div class="flex space-x-2 text-red-400">
                    <span class="font-bold">[{{ $job->updated_at ? $job->updated_at->format('H:i') : '--:--' }}]</span>
                    <span class="truncate">{{ $job->getShortJobClass() }}: {{ $job->exception_message }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 text-gray-600">
        REFRESH: 2s | <span class="text-gray-400">Ctrl+C to exit</span>
    </div>
</div>