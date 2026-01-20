<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Queue Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full" x-data="dashboard()">
    <div class="min-h-full">
        <nav class="bg-gray-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div class="hidden md:block">
                            <div class="ml-10 flex items-baseline space-x-4">
                                <a href="#" class="bg-gray-900 text-white rounded-md px-3 py-2 text-sm font-medium" aria-current="page">Dashboard</a>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-300 text-sm">Auto-refreshing</span>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <!-- Total Jobs -->
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="rounded-md bg-indigo-500 p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500">Total Jobs</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="formatNumber(stats.total_jobs)">0</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Failed Jobs -->
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="rounded-md bg-red-500 p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500">Failed Jobs</dt>
                                        <dd class="text-lg font-medium text-gray-900" x-text="formatNumber(stats.failed_jobs)">0</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Success Rate -->
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="rounded-md bg-green-500 p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500">Success Rate</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            <span x-text="formatNumber(stats.success_rate, 1)">0</span>%
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Avg Duration -->
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="rounded-md bg-blue-500 p-3">
                                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500">Avg Duration</dt>
                                        <dd class="text-lg font-medium text-gray-900">
                                            <span x-text="formatNumber(stats.avg_duration_ms)">0</span>ms
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 space-y-8">
                        
                        <!-- Recent Jobs -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium leading-6 text-gray-900">Recent Jobs</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queue</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Queued</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <template x-for="job in recentJobs" :key="job.uuid">
                                            <tr class="hover:bg-gray-50 cursor-pointer" @click="showJob(job.uuid)">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                                          :class="{
                                                              'bg-green-50 text-green-700 ring-green-600/20': job.status.value === 'completed',
                                                              'bg-red-50 text-red-700 ring-red-600/20': job.status.value === 'failed' || job.status.value === 'timeout',
                                                              'bg-yellow-50 text-yellow-800 ring-yellow-600/20': job.status.value === 'processing',
                                                              'bg-blue-50 text-blue-700 ring-blue-700/10': job.status.value === 'queued',
                                                          }"
                                                          x-text="job.status.label">
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900" x-text="job.job_class"></div>
                                                    <div x-show="job.is_failed" class="text-xs text-red-500 truncate max-w-xs" x-text="job.error"></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="job.queue"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right" x-text="job.duration"></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right" x-text="job.queued_at"></td>
                                            </tr>
                                        </template>
                                        <tr x-show="recentJobs.length === 0">
                                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No jobs found
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="lg:col-span-1 space-y-8">
                        
                        <!-- Queue Health -->
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                                <h3 class="text-lg font-medium leading-6 text-gray-900">Queue Health</h3>
                            </div>
                            <ul role="list" class="divide-y divide-gray-200">
                                <template x-for="queue in queues" :key="queue.queue">
                                    <li class="px-4 py-4 sm:px-6">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm font-medium text-indigo-600 truncate" x-text="queue.queue"></div>
                                            <div class="ml-2 flex-shrink-0 flex">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                      :class="{
                                                          'bg-green-100 text-green-800': queue.status === 'healthy',
                                                          'bg-red-100 text-red-800': queue.status !== 'healthy'
                                                      }"
                                                      x-text="queue.status">
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex justify-between">
                                            <div class="flex text-xs text-gray-500">
                                                <span x-text="queue.jobs_per_minute + ' jobs/min'"></span>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                                <li x-show="queues.length === 0" class="px-4 py-4 text-sm text-gray-500 text-center">
                                    No active queues
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Chart placeholder -->
                         <div class="bg-white shadow rounded-lg overflow-hidden p-4">
                            <div class="text-sm font-medium text-gray-500 mb-2">Job Distribution</div>
                            <div id="distribution-chart" style="height: 200px;"></div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
        
        <!-- Job Details Modal -->
        <div x-show="selectedJob" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6" @click.away="selectedJob = null">
                        <div class="sm:flex sm:items-start">
                             <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Job Details</h3>
                                <div class="mt-2">
                                    <div x-show="loadingPayload" class="text-center py-4">
                                        Loading...
                                    </div>
                                    <div x-show="!loadingPayload">
                                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide mt-4">Payload</h4>
                                        <pre class="bg-gray-100 p-2 rounded text-xs mt-1 overflow-auto max-h-60" x-text="JSON.stringify(jobPayload, null, 2)"></pre>
                                        
                                        <template x-if="jobException">
                                            <div>
                                                <h4 class="text-xs font-bold text-red-500 uppercase tracking-wide mt-4">Exception Trace</h4>
                                                <pre class="bg-red-50 p-2 rounded text-xs mt-1 overflow-auto max-h-60 text-red-800" x-text="jobException"></pre>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                            <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto" @click="selectedJob = null">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        function dashboard() {
            return {
                stats: {},
                queues: [],
                recentJobs: [],
                selectedJob: null,
                jobPayload: {},
                jobException: null,
                loadingPayload: false,
                chartInstance: null,

                init() {
                    this.fetchMetrics();
                    setInterval(() => this.fetchMetrics(), {{ config('queue-monitor.ui.refresh_interval', 3000) }});
                    
                    this.initChart();
                    window.addEventListener('resize', () => {
                         this.chartInstance && this.chartInstance.resize();
                    });
                },
                
                initChart() {
                    this.chartInstance = echarts.init(document.getElementById('distribution-chart'));
                },
                
                updateChart(data) {
                    if (!this.chartInstance || !data) return; 
                    
                    const option = {
                        tooltip: {
                            trigger: 'item'
                        },
                        series: [
                            {
                                name: 'Jobs',
                                type: 'pie',
                                radius: ['40%', '70%'],
                                avoidLabelOverlap: false,
                                itemStyle: {
                                    borderRadius: 10,
                                    borderColor: '#fff',
                                    borderWidth: 2
                                },
                                label: {
                                    show: false,
                                    position: 'center'
                                },
                                emphasis: {
                                    label: {
                                        show: true,
                                        fontSize: 20,
                                        fontWeight: 'bold'
                                    }
                                },
                                labelLine: {
                                    show: false
                                },
                                data: Object.keys(data).map(key => ({
                                    value: data[key].total,
                                    name: key.split('\\').pop() // Short class name
                                })).slice(0, 5) // Top 5
                            }
                        ]
                    };
                    
                    this.chartInstance.setOption(option);
                },

                async fetchMetrics() {
                    try {
                        const response = await fetch('{{ route('queue-monitor.metrics') }}');
                        const data = await response.json();
                        
                        this.stats = data.stats;
                        this.queues = data.queues;
                        this.recentJobs = data.recent_jobs;
                        
                        this.updateChart(data.charts.distribution);
                        
                    } catch (error) {
                        console.error('Error fetching metrics:', error);
                    }
                },
                
                async showJob(uuid) {
                    this.selectedJob = uuid;
                    this.loadingPayload = true;
                    this.jobPayload = {};
                    this.jobException = null;
                    
                    try {
                        const response = await fetch(`{{ route('queue-monitor.job.payload', '') }}/${uuid}`);
                        const data = await response.json();
                        this.jobPayload = data.payload;
                        this.jobException = data.exception;
                    } catch (e) {
                        this.jobPayload = { error: 'Failed to load payload' };
                    } finally {
                        this.loadingPayload = false;
                    }
                },

                formatNumber(num, decimals = 0) {
                    if (num === undefined || num === null) return '0';
                    return new Intl.NumberFormat('en-US', { 
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals 
                    }).format(num);
                }
            }
        }
    </script>
</body>
</html>