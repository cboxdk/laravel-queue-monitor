<div class="mx-1">
    {{-- ═══════════ HEADER BAR ═══════════ --}}
    <div class="flex px-1 bg-blue-900">
        <span class="text-cyan-400 font-bold">⚡ Queue Monitor</span>
        <span class="flex-1"></span>
        @if(!empty($alerts))
            <span class="text-red-400 font-bold mr-2">{{ count($alerts) }} alert{{ count($alerts) > 1 ? 's' : '' }}</span>
        @endif
        <span class="{{ $healthy ? 'text-green-400' : 'text-red-400' }} font-bold mr-2">{{ $healthy ? '● Healthy' : '▲ Degraded' }}</span>
        <span class="text-gray-400">{{ $timestamp }}</span>
    </div>

    {{-- ═══════════ TAB BAR (always visible, k9s-style) ═══════════ --}}
    @if($jobDetail === null)
        <div class="flex px-1 bg-gray-800">
            <span class="{{ $currentView === 1 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[1] Jobs</span>
            <span class="{{ $currentView === 2 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[2] Stats</span>
            <span class="{{ $currentView === 3 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[3] Queues</span>
            <span class="{{ $currentView === 4 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[4] Health</span>
            <span class="{{ $currentView === 5 ? 'text-white font-bold' : 'text-gray-500' }} mr-2">[5] Analytics</span>
            <span class="{{ $currentView === 6 ? 'text-white font-bold' : 'text-gray-500' }}">[6] Infra</span>
        </div>
    @endif

    @if($jobDetail !== null)
        {{-- ═══════════ JOB DETAIL VIEW ═══════════ --}}
        @include('queue-monitor::tui.partials.job-detail', ['job' => $jobDetail, 'retryChain' => $jobRetryChain])
    @else
        {{-- ═══════════ STATS BAR ═══════════ --}}
        <div class="flex px-1 mt-1">
            <span class="text-cyan-400 font-bold mr-1">Jobs:</span>
            <span class="text-white mr-2">{{ number_format($stats['total'] ?? 0) }}</span>
            <span class="text-cyan-400 font-bold mr-1">✓</span>
            <span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-400' : (($stats['success_rate'] ?? 0) > 80 ? 'text-yellow-400' : 'text-red-400') }} mr-2">{{ number_format($stats['success_rate'] ?? 0, 1) }}%</span>
            <span class="text-cyan-400 font-bold mr-1">✗</span>
            <span class="text-red-400 mr-2">{{ $stats['failed'] ?? 0 }}</span>
            <span class="text-cyan-400 font-bold mr-1">⏱</span>
            <span class="text-blue-400 mr-2">{{ number_format($stats['avg_duration_ms'] ?? 0, 0) }}ms</span>
            <span class="text-cyan-400 font-bold mr-1">⟳</span>
            <span class="text-yellow-400">{{ $stats['processing'] ?? 0 }}</span>
        </div>

        {{-- ═══════════ ACTIVE FILTERS ═══════════ --}}
        @if($statusFilter || $queueFilter || $inSearchMode || $searchQuery)
            <div class="flex px-1 mt-1">
                @if($statusFilter)
                    <span class="text-yellow-400 font-bold mr-2">[{{ ucfirst($statusFilter) }}]</span>
                @endif
                @if($queueFilter)
                    <span class="text-blue-300 font-bold mr-2">[Q: {{ $queueFilter }}]</span>
                @endif
                @if($inSearchMode)
                    <span class="text-green-400 font-bold mr-1">🔍</span>
                    <span class="text-white">{{ $searchQuery }}_</span>
                @elseif($searchQuery)
                    <span class="text-gray-400 mr-1">🔍</span>
                    <span class="text-white">{{ $searchQuery }}</span>
                @endif
            </div>
        @endif

        @if($currentView === 1)
            {{-- ═══════════ VIEW 1: JOBS TABLE ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1 flex">
                <span class="w-10 text-white font-bold">STATUS</span>
                <span class="flex-1 text-white font-bold ml-1">JOB CLASS</span>
                <span class="w-14 text-white font-bold">QUEUE</span>
                <span class="w-10 text-white font-bold">SERVER</span>
                <span class="w-10 text-white font-bold">DURATION</span>
                <span class="w-6 text-white font-bold">#</span>
                <span class="w-10 text-white font-bold">TIME</span>
            </div>

            <div class="flex flex-col">
                @forelse($jobs as $index => $job)
                    <div class="px-1 flex {{ $index === $selectedIndex ? 'bg-blue-800' : '' }}">
                        <span class="w-10">
                            @if($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::COMPLETED)
                                <span class="text-green-500">✓ Done</span>
                            @elseif($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::FAILED)
                                <span class="text-red-500">✗ Fail</span>
                            @elseif($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::TIMEOUT)
                                <span class="text-red-400">⏰ T/O</span>
                            @elseif($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::PROCESSING)
                                <span class="text-yellow-500">⟳ Run</span>
                            @elseif($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::QUEUED)
                                <span class="text-blue-500">◦ Wait</span>
                            @elseif($job->status === \Cbox\LaravelQueueMonitor\Enums\JobStatus::CANCELLED)
                                <span class="text-gray-500">⊘ Can</span>
                            @endif
                        </span>
                        <span class="flex-1 text-white ml-1 truncate">{{ $job->getShortJobClass() }}@if($job->attempt > 1) <span class="text-yellow-400">×{{ $job->attempt }}</span>@endif</span>
                        <span class="w-14 text-gray-400 truncate">{{ $job->queue }}</span>
                        <span class="w-10 text-gray-500 truncate">{{ $job->server_name ? Str::limit($job->server_name, 8) : '-' }}</span>
                        <span class="w-10 {{ ($job->duration_ms ?? 0) > 5000 ? 'text-red-400' : (($job->duration_ms ?? 0) > 1000 ? 'text-yellow-400' : 'text-gray-400') }}">
                            {{ $job->duration_ms !== null ? ($job->duration_ms >= 1000 ? number_format($job->duration_ms / 1000, 1) . 's' : $job->duration_ms . 'ms') : '-' }}
                        </span>
                        <span class="w-6 text-gray-500">{{ $job->attempt }}/{{ $job->max_attempts }}</span>
                        <span class="w-10 text-gray-600">
                            @if($job->queued_at)
                                {{ $job->queued_at->diffForHumans(null, true, true) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                @empty
                    <div class="px-1 text-gray-500 mt-1">No jobs found.</div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="flex px-1 mt-1 bg-gray-800">
                <span class="text-gray-400">
                    {{ $pageOffset + 1 }}-{{ min($pageOffset + $perPage, $totalJobs) }} of {{ number_format($totalJobs) }}
                </span>
                <span class="flex-1"></span>
                @if($pageOffset > 0)
                    <span class="text-cyan-400 mr-2">← prev</span>
                @endif
                @if($pageOffset + $perPage < $totalJobs)
                    <span class="text-cyan-400">next →</span>
                @endif
            </div>

        @elseif($currentView === 2)
            {{-- ═══════════ VIEW 2: STATISTICS ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1">
                <span class="text-white font-bold">📊 Statistics Overview</span>
            </div>
            <div class="flex flex-col px-1 mt-1">
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Total Jobs</span>
                    <span class="text-white font-bold">{{ number_format($stats['total'] ?? 0) }}</span>
                </div>
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Completed</span>
                    <span class="text-green-400">{{ number_format($stats['completed'] ?? 0) }}</span>
                </div>
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Failed</span>
                    <span class="text-red-400">{{ number_format($stats['failed'] ?? 0) }}</span>
                </div>
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Processing</span>
                    <span class="text-yellow-400">{{ number_format($stats['processing'] ?? 0) }}</span>
                </div>
                <div class="flex mt-1">
                    <span class="text-cyan-400 font-bold w-24">Success Rate</span>
                    <span class="{{ ($stats['success_rate'] ?? 0) > 95 ? 'text-green-400' : (($stats['success_rate'] ?? 0) > 80 ? 'text-yellow-400' : 'text-red-400') }} font-bold">{{ number_format($stats['success_rate'] ?? 0, 2) }}%</span>
                </div>
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Failure Rate</span>
                    <span class="{{ ($stats['failure_rate'] ?? 0) < 5 ? 'text-green-400' : 'text-red-400' }}">{{ number_format($stats['failure_rate'] ?? 0, 2) }}%</span>
                </div>
                <div class="flex mt-1">
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
                <div class="flex">
                    <span class="text-cyan-400 font-bold w-24">Max Memory</span>
                    <span class="text-blue-400">{{ number_format($stats['max_memory_mb'] ?? 0, 2) }}MB</span>
                </div>
            </div>

            {{-- Throughput sparkline (last 10 minutes) --}}
            @php
                $throughputData = $stats['throughput_last_10'] ?? [];
            @endphp

        @elseif($currentView === 3)
            {{-- ═══════════ VIEW 3: QUEUES ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1 flex">
                <span class="w-16 text-white font-bold">QUEUE</span>
                <span class="w-10 text-white font-bold">TOTAL</span>
                <span class="w-10 text-white font-bold">ACTIVE</span>
                <span class="w-10 text-white font-bold">FAILED</span>
                <span class="w-10 text-white font-bold">AVG MS</span>
                <span class="w-10 text-white font-bold">RATE</span>
                <span class="w-10 text-white font-bold">HEALTH</span>
            </div>
            <div class="flex flex-col">
                @forelse($queues as $index => $queue)
                    <div class="px-1 flex {{ $index === $selectedIndex ? 'bg-blue-800' : '' }}">
                        <span class="w-16 text-white truncate">{{ $queue['queue'] ?? '-' }}</span>
                        <span class="w-10 text-gray-400">{{ $queue['total_last_hour'] ?? 0 }}</span>
                        <span class="w-10 text-yellow-400">{{ $queue['processing'] ?? 0 }}</span>
                        <span class="w-10 {{ ($queue['failed'] ?? 0) > 0 ? 'text-red-400 font-bold' : 'text-gray-500' }}">{{ $queue['failed'] ?? 0 }}</span>
                        <span class="w-10 text-blue-400">{{ number_format($queue['avg_duration_ms'] ?? 0, 0) }}</span>
                        <span class="w-10 {{ ($queue['success_rate'] ?? 100) > 95 ? 'text-green-400' : 'text-yellow-400' }}">{{ number_format($queue['success_rate'] ?? 0, 0) }}%</span>
                        <span class="w-10 font-bold {{ ($queue['status'] ?? '') === 'healthy' ? 'text-green-400' : (($queue['status'] ?? '') === 'degraded' ? 'text-yellow-400' : 'text-red-400') }}">
                            {{ ($queue['status'] ?? '') === 'healthy' ? '●' : (($queue['status'] ?? '') === 'degraded' ? '▲' : '✗') }} {{ $queue['status'] ?? '-' }}
                        </span>
                    </div>
                @empty
                    <div class="px-1 text-gray-500 mt-1">No queue data available.</div>
                @endforelse
            </div>

        @elseif($currentView === 4)
            {{-- ═══════════ VIEW 4: HEALTH ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1">
                <span class="text-white font-bold">🏥 System Health</span>
                @if($healthData)
                    <span class="ml-2 {{ ($healthData['score'] ?? 0) >= 80 ? 'text-green-400' : (($healthData['score'] ?? 0) >= 50 ? 'text-yellow-400' : 'text-red-400') }} font-bold">
                        Score: {{ $healthData['score'] ?? 0 }}%
                    </span>
                @endif
            </div>

            @if($healthData)
                <div class="flex flex-col px-1 mt-1">
                    <div class="flex">
                        <span class="text-cyan-400 font-bold w-20">Overall</span>
                        <span class="{{ ($healthData['status'] ?? 'unknown') === 'healthy' ? 'text-green-400' : 'text-red-400' }} font-bold">
                            {{ ($healthData['status'] ?? 'unknown') === 'healthy' ? '● HEALTHY' : '▲ DEGRADED' }}
                        </span>
                    </div>
                </div>

                {{-- Health checks --}}
                <div class="mt-1 bg-gray-800 px-1 flex">
                    <span class="w-4 text-white font-bold"></span>
                    <span class="w-20 text-white font-bold ml-1">CHECK</span>
                    <span class="flex-1 text-white font-bold">DETAILS</span>
                </div>
                @foreach(($healthData['checks'] ?? []) as $checkName => $check)
                    <div class="px-1 flex">
                        <span class="w-4">{{ ($check['healthy'] ?? false) ? '✅' : '❌' }}</span>
                        <span class="w-20 text-white ml-1">{{ ucwords(str_replace('_', ' ', $checkName)) }}</span>
                        <span class="flex-1 {{ ($check['healthy'] ?? false) ? 'text-gray-400' : 'text-red-400' }} truncate">{{ $check['message'] ?? '' }}</span>
                    </div>
                @endforeach
            @else
                <div class="px-1 mt-1 text-gray-500">Loading health data...</div>
            @endif

            {{-- Alerts --}}
            @if(!empty($alerts))
                <div class="mt-1 bg-gray-800 px-1">
                    <span class="text-white font-bold">⚠ Active Alerts</span>
                </div>
                @foreach($alerts as $alertName => $alert)
                    <div class="px-1 flex">
                        <span class="w-4">
                            @if(($alert['severity'] ?? '') === 'critical')
                                <span class="text-red-400">🔴</span>
                            @elseif(($alert['severity'] ?? '') === 'warning')
                                <span class="text-yellow-400">🟡</span>
                            @else
                                <span class="text-blue-400">🔵</span>
                            @endif
                        </span>
                        <span class="w-20 text-white ml-1 font-bold">{{ ucwords(str_replace('_', ' ', $alertName)) }}</span>
                        <span class="w-12 {{ ($alert['severity'] ?? '') === 'critical' ? 'text-red-400' : 'text-yellow-400' }}">{{ $alert['severity'] ?? '' }}</span>
                        <span class="flex-1 text-gray-400 truncate">{{ $alert['message'] ?? '' }}</span>
                    </div>
                @endforeach
            @endif

            {{-- Per-queue health --}}
            <div class="mt-1 bg-gray-800 px-1">
                <span class="text-white font-bold">Queue Health</span>
            </div>
            @foreach($queues as $queue)
                <div class="flex px-1">
                    <span class="w-20 text-white">{{ $queue['queue'] ?? '-' }}</span>
                    <span class="w-14 font-bold {{ ($queue['status'] ?? '') === 'healthy' ? 'text-green-400' : (($queue['status'] ?? '') === 'degraded' ? 'text-yellow-400' : 'text-red-400') }}">
                        {{ $queue['status'] ?? 'unknown' }}
                    </span>
                    <span class="text-gray-500">{{ number_format($queue['health_score'] ?? 0, 0) }}% · {{ $queue['total_last_hour'] ?? 0 }} jobs/hr · {{ $queue['failed'] ?? 0 }} failed</span>
                </div>
            @endforeach

        @elseif($currentView === 5)
            {{-- ═══════════ VIEW 5: ANALYTICS ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1">
                <span class="text-white font-bold">📈 Analytics</span>
            </div>

            @if($analyticsData)
                {{-- Job classes --}}
                <div class="mt-1 px-1">
                    <span class="text-cyan-400 font-bold">Job Classes</span>
                </div>
                <div class="bg-gray-800 px-1 flex">
                    <span class="flex-1 text-white font-bold">CLASS</span>
                    <span class="w-10 text-white font-bold">TOTAL</span>
                    <span class="w-10 text-white font-bold">DONE</span>
                    <span class="w-10 text-white font-bold">FAIL</span>
                    <span class="w-10 text-white font-bold">AVG MS</span>
                    <span class="w-10 text-white font-bold">RATE</span>
                </div>
                @forelse(array_slice($analyticsData['job_classes'] ?? [], 0, 10) as $jc)
                    <div class="px-1 flex">
                        <span class="flex-1 text-white truncate">{{ class_basename($jc['job_class'] ?? '') }}</span>
                        <span class="w-10 text-gray-400">{{ $jc['total'] ?? 0 }}</span>
                        <span class="w-10 text-green-400">{{ $jc['completed'] ?? 0 }}</span>
                        <span class="w-10 {{ ($jc['failed'] ?? 0) > 0 ? 'text-red-400 font-bold' : 'text-gray-500' }}">{{ $jc['failed'] ?? 0 }}</span>
                        <span class="w-10 text-blue-400">{{ number_format($jc['avg_duration_ms'] ?? 0, 0) }}</span>
                        <span class="w-10 {{ ($jc['success_rate'] ?? 0) > 95 ? 'text-green-400' : 'text-yellow-400' }}">{{ number_format($jc['success_rate'] ?? 0, 0) }}%</span>
                    </div>
                @empty
                    <div class="px-1 text-gray-500">No job class data.</div>
                @endforelse

                {{-- Failure patterns --}}
                @php
                    $topExceptions = $analyticsData['failure_patterns']['top_exceptions'] ?? [];
                @endphp
                @if(!empty($topExceptions))
                    <div class="mt-1 px-1">
                        <span class="text-red-400 font-bold">Failure Patterns</span>
                    </div>
                    <div class="bg-gray-800 px-1 flex">
                        <span class="flex-1 text-white font-bold">EXCEPTION</span>
                        <span class="w-10 text-white font-bold">COUNT</span>
                        <span class="w-20 text-white font-bold">AFFECTED</span>
                    </div>
                    @foreach(array_slice($topExceptions, 0, 8) as $fp)
                        <div class="px-1 flex">
                            <span class="flex-1 text-red-300 truncate">{{ class_basename($fp['exception_class'] ?? '') }}</span>
                            <span class="w-10 text-red-400 font-bold">{{ $fp['count'] ?? 0 }}</span>
                            <span class="w-20 text-gray-400 truncate">{{ implode(', ', array_map('class_basename', array_slice($fp['affected_jobs'] ?? [], 0, 3))) }}</span>
                        </div>
                    @endforeach
                @endif

                {{-- Server stats --}}
                @if(!empty($analyticsData['servers']))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Servers</span>
                    </div>
                    <div class="bg-gray-800 px-1 flex">
                        <span class="flex-1 text-white font-bold">SERVER</span>
                        <span class="w-10 text-white font-bold">TOTAL</span>
                        <span class="w-10 text-white font-bold">FAIL</span>
                        <span class="w-10 text-white font-bold">AVG MS</span>
                    </div>
                    @foreach(array_slice($analyticsData['servers'], 0, 6) as $srv)
                        <div class="px-1 flex">
                            <span class="flex-1 text-white truncate">{{ $srv['server_name'] ?? '-' }}</span>
                            <span class="w-10 text-gray-400">{{ $srv['total'] ?? 0 }}</span>
                            <span class="w-10 {{ ($srv['failed'] ?? 0) > 0 ? 'text-red-400' : 'text-gray-500' }}">{{ $srv['failed'] ?? 0 }}</span>
                            <span class="w-10 text-blue-400">{{ number_format($srv['avg_duration_ms'] ?? 0, 0) }}</span>
                        </div>
                    @endforeach
                @endif

                {{-- Tag stats --}}
                @if(!empty($analyticsData['tags']))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Tags</span>
                    </div>
                    @foreach(array_slice($analyticsData['tags'], 0, 5) as $tag)
                        <div class="px-1 flex">
                            <span class="text-blue-300 mr-2">#{{ $tag['tag'] ?? '' }}</span>
                            <span class="text-gray-400">{{ $tag['count'] ?? 0 }} jobs · {{ number_format($tag['success_rate'] ?? 0, 0) }}% success</span>
                        </div>
                    @endforeach
                @endif
            @else
                <div class="px-1 mt-1 text-gray-500">Loading analytics...</div>
            @endif

        @elseif($currentView === 6)
            {{-- ═══════════ VIEW 6: INFRASTRUCTURE ═══════════ --}}
            <div class="mt-1 bg-gray-800 px-1">
                <span class="text-white font-bold">🏗 Infrastructure</span>
            </div>

            @if($infrastructureData)
                {{-- Worker utilization --}}
                @php
                    $util = $infrastructureData['scaling']['utilization'] ?? [];
                    $utilPct = $util['percentage'] ?? 0;
                    $utilStatus = $util['status'] ?? 'unknown';
                    $busyWorkers = $util['busy_workers'] ?? 0;
                    $totalWorkers = $util['total_workers'] ?? 0;
                    $barWidth = 30;
                    $filledWidth = (int) round($barWidth * $utilPct / 100);
                @endphp
                <div class="flex flex-col px-1 mt-1">
                    <div class="flex">
                        <span class="text-cyan-400 font-bold w-24">Utilization</span>
                        <span class="{{ $utilPct > 85 ? 'text-red-400' : ($utilPct > 60 ? 'text-green-400' : ($utilPct > 30 ? 'text-yellow-400' : 'text-gray-400')) }} font-bold">
                            {{ $utilPct }}%
                        </span>
                        <span class="text-gray-500 ml-2">[{{ str_repeat('█', $filledWidth) }}{{ str_repeat('░', $barWidth - $filledWidth) }}]</span>
                        <span class="text-gray-500 ml-1">({{ $utilStatus }})</span>
                    </div>
                    <div class="flex">
                        <span class="text-cyan-400 font-bold w-24">Workers</span>
                        <span class="text-white">{{ $busyWorkers }} busy / {{ $totalWorkers }} total</span>
                    </div>
                </div>

                {{-- Horizon supervisors --}}
                @if(($infrastructureData['workers']['available'] ?? false) && !empty($infrastructureData['workers']['supervisors'] ?? []))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Supervisors</span>
                    </div>
                    @foreach($infrastructureData['workers']['supervisors'] as $sup)
                        <div class="px-1 flex">
                            <span class="text-white w-24 truncate">{{ $sup['name'] ?? '' }}</span>
                            <span class="{{ ($sup['status'] ?? '') === 'running' ? 'text-green-400' : 'text-yellow-400' }} w-12">{{ $sup['status'] ?? '' }}</span>
                            <span class="text-gray-400">{{ $sup['processes'] ?? 0 }} proc · {{ implode(', ', $sup['queues'] ?? []) }}</span>
                        </div>
                    @endforeach
                @elseif(!($infrastructureData['workers']['available'] ?? false))
                    <div class="px-1 mt-1 text-gray-500">Horizon not detected. Worker metrics require Laravel Horizon.</div>
                @endif

                {{-- Worker type breakdown --}}
                @if(!empty($infrastructureData['worker_types']['by_type'] ?? []))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Worker Types (last hour)</span>
                    </div>
                    @foreach($infrastructureData['worker_types']['by_type'] as $wt)
                        <div class="px-1 flex">
                            <span class="text-white w-20">{{ $wt['label'] ?? '' }}</span>
                            <span class="text-gray-400">{{ $wt['total_jobs'] ?? 0 }} jobs · {{ $wt['total_workers'] ?? 0 }} workers · {{ implode(', ', $wt['queues'] ?? []) }}</span>
                        </div>
                    @endforeach
                @endif

                {{-- Capacity --}}
                @if(!empty($infrastructureData['capacity']['queues'] ?? []))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Queue Capacity</span>
                    </div>
                    <div class="bg-gray-800 px-1 flex">
                        <span class="w-16 text-white font-bold">QUEUE</span>
                        <span class="w-10 text-white font-bold">AVG MS</span>
                        <span class="w-10 text-white font-bold">WRKRS</span>
                        <span class="w-12 text-white font-bold">MAX/MIN</span>
                        <span class="w-12 text-white font-bold">PEAK/MIN</span>
                        <span class="w-14 text-white font-bold">HEADROOM</span>
                        <span class="w-14 text-white font-bold">STATUS</span>
                    </div>
                    @foreach($infrastructureData['capacity']['queues'] as $cap)
                        <div class="px-1 flex">
                            <span class="w-16 text-white truncate">{{ $cap['queue'] ?? '' }}</span>
                            <span class="w-10 text-blue-400">{{ $cap['avg_duration_ms'] ?? 0 }}</span>
                            <span class="w-10 text-gray-400">{{ $cap['workers'] ?? 0 }}</span>
                            <span class="w-12 text-gray-400">{{ $cap['max_jobs_per_minute'] ?? 0 }}</span>
                            <span class="w-12 text-gray-400">{{ $cap['peak_jobs_per_minute'] ?? 0 }}</span>
                            <span class="w-14 {{ ($cap['headroom_percent'] ?? 0) < 15 ? 'text-red-400 font-bold' : (($cap['headroom_percent'] ?? 0) < 40 ? 'text-yellow-400' : 'text-green-400') }}">{{ number_format($cap['headroom_percent'] ?? 0, 0) }}%</span>
                            <span class="w-14 {{ ($cap['status'] ?? '') === 'at_capacity' ? 'text-red-400' : (($cap['status'] ?? '') === 'optimal' ? 'text-green-400' : 'text-gray-400') }}">{{ $cap['status'] ?? '-' }}</span>
                        </div>
                    @endforeach
                @endif

                {{-- SLA Compliance --}}
                @if(!empty($infrastructureData['sla']['per_queue'] ?? []))
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">SLA Compliance (Pickup Time)</span>
                    </div>
                    @foreach($infrastructureData['sla']['per_queue'] as $sla)
                        @php
                            $compliance = $sla['compliance'] ?? 0;
                            $slaBarWidth = 20;
                            $slaFilled = (int) round($slaBarWidth * $compliance / 100);
                        @endphp
                        <div class="px-1 flex">
                            <span class="w-16 text-white truncate">{{ $sla['queue'] ?? '' }}</span>
                            <span class="{{ $compliance >= 95 ? 'text-green-400' : ($compliance >= 80 ? 'text-yellow-400' : 'text-red-400') }} font-bold w-10">{{ number_format($compliance, 1) }}%</span>
                            <span class="text-gray-500">[{{ str_repeat('█', $slaFilled) }}{{ str_repeat('░', $slaBarWidth - $slaFilled) }}]</span>
                            <span class="text-gray-500 ml-2">{{ $sla['within'] ?? 0 }}/{{ $sla['total'] ?? 0 }} within {{ $sla['target_seconds'] ?? 30 }}s</span>
                        </div>
                    @endforeach
                @endif

                {{-- Scaling events --}}
                @if($infrastructureData['scaling']['has_autoscale'] ?? false)
                    <div class="mt-1 px-1">
                        <span class="text-cyan-400 font-bold">Scaling Events</span>
                    </div>
                    @php
                        $summary = $infrastructureData['scaling']['summary'] ?? [];
                    @endphp
                    <div class="px-1 flex">
                        <span class="text-gray-400">Decisions: {{ $summary['total_decisions'] ?? 0 }}</span>
                        <span class="text-green-400 ml-2">↑{{ $summary['scale_ups'] ?? 0 }}</span>
                        <span class="text-blue-400 ml-2">↓{{ $summary['scale_downs'] ?? 0 }}</span>
                        @if(($summary['sla_breaches'] ?? 0) > 0)
                            <span class="text-red-400 ml-2 font-bold">⚠ {{ $summary['sla_breaches'] }} breaches</span>
                        @endif
                    </div>
                    @foreach(array_slice($infrastructureData['scaling']['history'] ?? [], 0, 5) as $event)
                        <div class="px-1 flex">
                            <span class="w-8 {{ ($event['action'] ?? '') === 'scale_up' ? 'text-green-400' : (($event['action'] ?? '') === 'scale_down' ? 'text-blue-400' : 'text-red-400') }}">
                                {{ ($event['action'] ?? '') === 'scale_up' ? '↑' : (($event['action'] ?? '') === 'scale_down' ? '↓' : '⚠') }}
                            </span>
                            <span class="text-white w-16 truncate">{{ $event['queue'] ?? '' }}</span>
                            <span class="text-gray-400">{{ $event['current_workers'] ?? 0 }}→{{ $event['target_workers'] ?? 0 }}</span>
                            <span class="text-gray-500 ml-2 truncate">{{ $event['reason'] ?? '' }}</span>
                            <span class="text-gray-600 ml-1">{{ $event['time_human'] ?? '' }}</span>
                        </div>
                    @endforeach
                @endif
            @else
                <div class="px-1 mt-1 text-gray-500">Loading infrastructure data...</div>
            @endif
        @endif

        {{-- ═══════════ KEYBINDINGS FOOTER (always at bottom) ═══════════ --}}
        <div class="flex px-1 bg-gray-800 mt-1">
            @if($currentView === 1)
                <span class="text-cyan-400 font-bold mr-1">↑↓</span><span class="text-gray-400 mr-2">Nav</span>
                <span class="text-cyan-400 font-bold mr-1">Enter</span><span class="text-gray-400 mr-2">Detail</span>
                <span class="text-cyan-400 font-bold mr-1">←→</span><span class="text-gray-400 mr-2">Page</span>
                <span class="text-cyan-400 font-bold mr-1">S</span><span class="text-gray-400 mr-2">Status</span>
                <span class="text-cyan-400 font-bold mr-1">W</span><span class="text-gray-400 mr-2">Queue</span>
                <span class="text-cyan-400 font-bold mr-1">F</span><span class="text-gray-400 mr-2">Search</span>
                <span class="text-cyan-400 font-bold mr-1">R</span><span class="text-gray-400 mr-2">Replay</span>
                <span class="text-cyan-400 font-bold mr-1">Q</span><span class="text-gray-400">Quit</span>
            @else
                <span class="text-cyan-400 font-bold mr-1">1-6</span><span class="text-gray-400 mr-2">Views</span>
                <span class="text-cyan-400 font-bold mr-1">↑↓</span><span class="text-gray-400 mr-2">Nav</span>
                <span class="text-cyan-400 font-bold mr-1">Q</span><span class="text-gray-400">Quit</span>
            @endif
        </div>
    @endif
</div>
