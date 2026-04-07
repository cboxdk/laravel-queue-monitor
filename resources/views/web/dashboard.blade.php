<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#4f6df5', light: '#7b91f7', dark: '#3b57d4', faint: '#eef1fe' },
                    },
                    fontFamily: {
                        sans: ['DM Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'Menlo', 'monospace'],
                    },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }

        /* Shimmer loading skeleton */
        .shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* Pulse indicators */
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes pulse-alert { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .pulse-alert { animation: pulse-alert 2s ease-in-out infinite; }

        /* Stagger children entrance */
        @keyframes fade-up { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .stagger-in > * { animation: fade-up 0.35s ease-out both; }
        .stagger-in > *:nth-child(1) { animation-delay: 0ms; }
        .stagger-in > *:nth-child(2) { animation-delay: 40ms; }
        .stagger-in > *:nth-child(3) { animation-delay: 80ms; }
        .stagger-in > *:nth-child(4) { animation-delay: 120ms; }
        .stagger-in > *:nth-child(5) { animation-delay: 160ms; }
        .stagger-in > *:nth-child(6) { animation-delay: 200ms; }

        /* Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

        /* Table hover indicator */
        table tbody tr { border-left: 3px solid transparent; transition: border-color 0.15s ease, background-color 0.15s ease; }
        table tbody tr:hover { border-left-color: #4f6df5; }

        /* Drill arrow on hover */
        .drill-arrow::after { content: ' \2192'; opacity: 0; transition: opacity 0.12s ease; }
        .drill-arrow:hover::after { opacity: 1; }

        /* Card hover lift */
        .card-hover { transition: box-shadow 0.2s ease, transform 0.2s ease; }
        .card-hover:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-1px); }

        /* JSON/code viewer */
        pre.json-viewer { tab-size: 2; }

        /* Subtle noise texture on body */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.015'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-[#eaeff7] via-[#e0e6f2] to-[#e8e2f5]"
      x-data="dashboard()" x-init="init()" x-cloak>
    <div class="min-h-full relative z-10">

        {{-- ==================== HEADER ==================== --}}
        <header class="bg-white/95 backdrop-blur-sm border-b border-gray-200/80 sticky top-0 z-30">
            <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex h-14 items-center justify-between">
                    <div class="flex items-center gap-3">
                        <a :href="dashboardUrl" class="flex items-center gap-3 group">
                            <svg class="h-7 w-7 text-brand transition-transform group-hover:scale-105" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="text-[15px] font-semibold text-gray-900 tracking-tight">Queue Monitor</span>
                        </a>
                        {{-- Health badge: uses health.status when available, falls back to success_rate --}}
                        <button x-show="overview.stats.total !== undefined"
                              @click="navigateTo('health'); $nextTick(() => fetchHealth())"
                              class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full border transition-colors cursor-pointer hover:opacity-80"
                              :class="{
                                  'bg-emerald-50 text-emerald-700 border-emerald-200': healthBadge() === 'healthy',
                                  'bg-amber-50 text-amber-700 border-amber-200': healthBadge() === 'degraded',
                                  'bg-red-50 text-red-700 border-red-200': healthBadge() === 'unhealthy',
                              }"
                              :title="healthBadge() === 'healthy' ? 'All systems healthy' : ((overview.stats.failed ?? 0) + ' failed jobs')"
                              x-text="healthBadge() === 'healthy' ? 'Healthy' : (healthBadge() === 'degraded' ? 'Degraded' : 'Unhealthy')">
                        </button>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-[11px] text-gray-400 font-mono tabular-nums" x-data x-init="setInterval(() => $el.textContent = new Date().toLocaleTimeString(), 1000)" x-text="new Date().toLocaleTimeString()"></span>
                        <button @click="toggleLive()" class="flex items-center gap-2 text-[11px] font-semibold px-3 py-1.5 rounded-full transition-all"
                                :class="isLive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm shadow-emerald-100' : 'bg-gray-100 text-gray-500 border border-gray-200'">
                            <span class="relative flex h-2 w-2">
                                <span x-show="isLive" class="pulse-dot absolute inline-flex h-full w-full rounded-full bg-emerald-500"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2" :class="isLive ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                            </span>
                            <span x-text="isLive ? 'Live' : 'Paused'"></span>
                        </button>
                        <div x-show="error" x-transition class="flex items-center gap-2 text-[11px] font-medium px-3 py-1.5 rounded-full bg-red-50 text-red-700 border border-red-200">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            <span x-text="error"></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- ==================== TAB NAVIGATION (always visible) ==================== --}}
        <nav class="bg-white/95 backdrop-blur-sm border-b border-gray-200/80">
            <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex gap-0 overflow-x-auto" role="tablist">
                    <template x-for="tab in [{id:'overview',label:'Overview',icon:'chart'},{id:'jobs',label:'Jobs',icon:'list'},{id:'analytics',label:'Analytics',icon:'pie'},{id:'health',label:'Health',icon:'heart'},{id:'infrastructure',label:'Infrastructure',icon:'server'}]" :key="tab.id">
                        <button @click="navigateTo(tab.id)" role="tab"
                                :aria-selected="activeTab === tab.id"
                                class="relative flex items-center gap-2 px-5 py-3 text-sm font-medium whitespace-nowrap transition-colors border-b-2"
                                :class="activeTab === tab.id ? 'text-brand border-brand' : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'">
                            <template x-if="tab.icon === 'chart'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                            </template>
                            <template x-if="tab.icon === 'list'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            </template>
                            <template x-if="tab.icon === 'pie'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>
                            </template>
                            <template x-if="tab.icon === 'heart'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>
                            </template>
                            <template x-if="tab.icon === 'server'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" /></svg>
                            </template>
                            <span x-text="tab.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </nav>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">

            {{-- ==================== TAB CONTENT (hidden when viewing job detail or drill-down) ==================== --}}
            <div x-show="!jobView && !drillDown">

            {{-- ==================== OVERVIEW TAB ==================== --}}
            <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Stats Grid --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6 stagger-in">
                    <div class="bg-white border border-gray-200/80 border-l-4 border-l-brand rounded-xl shadow-sm p-4 card-hover">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Jobs (24h)</div>
                        <template x-if="loading.overview"><div class="h-9 w-20 shimmer rounded"></div></template>
                        <template x-if="!loading.overview">
                            <div class="text-2xl lg:text-4xl font-bold text-gray-900 tabular-nums" x-text="formatNumber(overview.stats.total_jobs)">0</div>
                        </template>
                    </div>
                    <div class="bg-white border border-gray-200/80 border-l-4 border-l-emerald-500 rounded-xl shadow-sm p-4 card-hover">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Success Rate</div>
                        <template x-if="loading.overview"><div class="h-9 w-20 shimmer rounded"></div></template>
                        <template x-if="!loading.overview">
                            <div class="text-2xl lg:text-4xl font-bold tabular-nums" :class="overview.stats.success_rate >= 95 ? 'text-emerald-600' : overview.stats.success_rate >= 80 ? 'text-amber-600' : 'text-red-600'">
                                <span x-text="formatNumber(overview.stats.success_rate, 1)">0</span><span class="text-lg font-normal">%</span>
                            </div>
                        </template>
                    </div>
                    <div class="bg-white border border-gray-200/80 border-l-4 border-l-red-500 rounded-xl shadow-sm p-4 card-hover">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Failed</div>
                        <template x-if="loading.overview"><div class="h-9 w-20 shimmer rounded"></div></template>
                        <template x-if="!loading.overview">
                            <div class="text-2xl lg:text-4xl font-bold tabular-nums" :class="overview.stats.failed > 0 ? 'text-red-600' : 'text-gray-900'" x-text="formatNumber(overview.stats.failed)">0</div>
                        </template>
                    </div>
                    <div class="bg-white border border-gray-200/80 border-l-4 border-l-amber-500 rounded-xl shadow-sm p-4 card-hover">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Avg Duration</div>
                        <template x-if="loading.overview"><div class="h-9 w-20 shimmer rounded"></div></template>
                        <template x-if="!loading.overview">
                            <div class="text-2xl lg:text-4xl font-bold text-gray-900 font-mono tabular-nums" x-text="formatDuration(overview.stats.avg_duration_ms)">0ms</div>
                        </template>
                    </div>
                    <div class="bg-white border border-gray-200/80 border-l-4 border-l-purple-500 rounded-xl shadow-sm p-4 col-span-2 lg:col-span-1 card-hover">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Queue Backlog</div>
                        <template x-if="loading.overview"><div class="h-9 w-20 shimmer rounded"></div></template>
                        <template x-if="!loading.overview">
                            <div class="text-2xl lg:text-4xl font-bold tabular-nums" :class="overview.stats.queue_backlog > 50 ? 'text-amber-600' : 'text-gray-900'" x-text="formatNumber(overview.stats.queue_backlog || 0)">0</div>
                        </template>
                    </div>
                </div>

                {{-- Two column layout --}}
                <div class="flex flex-col lg:flex-row gap-6">
                    {{-- Left: Recent Jobs Table --}}
                    <div class="flex-1 min-w-0">
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Recent Jobs</h3>
                                <button @click="navigateTo('jobs')" class="text-[11px] font-semibold text-brand hover:text-brand-dark transition drill-arrow">View all</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead>
                                        <tr class="bg-gray-50/60">
                                            <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                            <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Job</th>
                                            <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Queue</th>
                                            <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Duration</th>
                                            <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">CPU</th>
                                            <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Memory</th>
                                            <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Time</th>
                                            <th class="px-4 py-2.5 w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <template x-if="loading.overview">
                                            <template x-for="i in 5" :key="'skel-'+i">
                                                <tr><td class="px-4 py-3"><div class="h-5 w-16 shimmer rounded-full"></div></td><td class="px-4 py-3"><div class="h-4 w-32 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-4 w-16 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-4 w-12 shimmer rounded ml-auto"></div></td><td class="px-4 py-3"><div class="h-4 w-16 shimmer rounded ml-auto"></div></td><td></td></tr>
                                            </template>
                                        </template>
                                        <template x-if="!loading.overview">
                                            <template x-for="job in overview.recentJobs.slice(0, 15)" :key="job.uuid">
                                                <tr class="group hover:bg-brand-faint/40 cursor-pointer transition-colors" @click="openJobView(job.uuid)">
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(job.status.value)" x-text="job.status.label"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-sm font-mono text-brand hover:underline drill-arrow" x-text="job.job_class" @click.stop="openDrillDown('job_class', job.full_job_class || job.job_class)"></span>
                                                            <span x-show="job.attempt > 1" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-200" x-text="'x' + job.attempt" :title="'Attempt ' + job.attempt"></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-sm text-brand hover:underline drill-arrow" x-text="job.queue" @click.stop="openDrillDown('queue', job.queue)"></td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatDuration(job.duration_ms)"></td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatCpu(job.cpu_time_ms, job.duration_ms)"></td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatMemoryShort(job.memory_peak_mb, job.worker_memory_limit_mb)"></td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-[11px] text-gray-400 text-right" x-text="job.queued_at"></td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap text-right">
                                                        <button x-show="job.is_failed" @click.stop="replayJob(job.uuid)" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-50 rounded-md hover:bg-amber-100 border border-amber-200 transition">
                                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                                                            Replay
                                                        </button>
                                                        <button x-show="!job.is_failed" @click.stop="replayJob(job.uuid)" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium text-amber-700 bg-amber-50 rounded-md hover:bg-amber-100 border border-amber-200 transition opacity-0 group-hover:opacity-100">
                                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                                                            Replay
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                    </tbody>
                                </table>
                                <div x-show="!loading.overview && overview.recentJobs.length === 0" class="px-6 py-16 text-center">
                                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 mb-3">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-500">No jobs recorded yet</p>
                                    <p class="text-[11px] text-gray-400 mt-1">Jobs will appear here once they're dispatched</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Sidebar --}}
                    <div class="w-full lg:w-[380px] flex-shrink-0 space-y-6">
                        {{-- Queue Health --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900">Queue Health</h3>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <template x-if="loading.overview">
                                    <template x-for="i in 3" :key="'qskel-'+i">
                                        <div class="px-5 py-3 flex items-center justify-between"><div class="h-4 w-24 shimmer rounded"></div><div class="h-4 w-16 shimmer rounded"></div></div>
                                    </template>
                                </template>
                                <template x-if="!loading.overview">
                                    <template x-for="q in overview.queues" :key="q.queue">
                                        <div class="px-5 py-3">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-2 w-2 rounded-full" :class="q.status === 'healthy' ? 'bg-emerald-400' : 'bg-red-400'"></span>
                                                    <span class="text-sm font-medium text-brand hover:underline cursor-pointer drill-arrow" x-text="q.queue" @click="openDrillDown('queue', q.queue)"></span>
                                                </div>
                                                <div class="flex items-center gap-3 text-[11px] text-gray-500">
                                                    <span x-text="q.total_last_hour + '/hr'"></span>
                                                    <span x-show="q.processing > 0" class="text-blue-600 font-semibold" x-text="q.processing + ' active'"></span>
                                                    <span x-show="q.failed > 0" class="text-red-600 font-semibold" x-text="q.failed + ' failed'"></span>
                                                </div>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full transition-all duration-500" :class="q.failed > 0 ? 'bg-red-400' : 'bg-brand/50'" :style="'width: ' + Math.min(100, Math.max(2, (q.total_last_hour / Math.max(1, ...overview.queues.map(x => x.total_last_hour))) * 100)) + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </template>
                            </div>
                            <div x-show="!loading.overview && overview.queues.length === 0" class="py-10 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25" /></svg>
                                </div>
                                <p class="text-sm text-gray-400">No active queues</p>
                            </div>
                        </div>

                        {{-- Active Alerts --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900">Active Alerts</h3>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <template x-for="alert in (overview.alerts?.active || [])" :key="alert.message">
                                    <div class="px-5 py-3 flex items-start gap-3">
                                        <span class="mt-0.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                              :class="{ 'bg-red-100 text-red-700': alert.severity === 'critical', 'bg-amber-100 text-amber-700': alert.severity === 'warning', 'bg-blue-100 text-blue-700': alert.severity === 'info' }" x-text="alert.severity"></span>
                                        <span class="text-sm text-gray-700" x-text="alert.message"></span>
                                    </div>
                                </template>
                            </div>
                            <div x-show="!overview.alerts?.active?.length" class="py-10 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 mb-2">
                                    <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <p class="text-sm text-gray-400">No active alerts</p>
                            </div>
                        </div>

                        {{-- Throughput Chart --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900">Throughput <span class="text-gray-400 font-normal">(1h)</span></h3>
                            </div>
                            <div class="p-4">
                                <div id="throughput-chart" style="height: 180px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== JOBS TAB ==================== --}}
            <div x-show="activeTab === 'jobs'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Filter Bar --}}
                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 mb-4">
                    <div class="flex flex-wrap gap-3 items-center">
                        <div class="relative flex-1 min-w-[200px]">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                            <input type="text" x-model.debounce.300ms="filters.search" @input="resetPaginationAndFetch()" placeholder="Search jobs..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition">
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <span class="text-gray-700">Status</span>
                                <span x-show="filters.statuses.length" class="inline-flex items-center justify-center h-5 min-w-[20px] px-1 text-[10px] font-bold bg-brand text-white rounded-full" x-text="filters.statuses.length"></span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                                <template x-for="s in ['queued','processing','completed','failed','timeout']" :key="s">
                                    <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" :value="s" x-model="filters.statuses" @change="resetPaginationAndFetch()" class="rounded border-gray-300 text-brand focus:ring-brand">
                                        <span class="text-sm capitalize" x-text="s"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <span class="text-gray-700" x-text="filters.queue || 'All Queues'"></span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                                <button @click="filters.queue = ''; open = false; resetPaginationAndFetch()" class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50">All Queues</button>
                                <template x-for="q in availableQueues" :key="q">
                                    <button @click="filters.queue = q; open = false; resetPaginationAndFetch()" class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50" x-text="q"></button>
                                </template>
                            </div>
                        </div>
                        <div class="relative">
                            <input type="datetime-local" x-model="filters.dateFrom" @change="resetPaginationAndFetch()" class="px-3 py-2 pr-7 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                            <button x-show="filters.dateFrom" @click="filters.dateFrom = ''; resetPaginationAndFetch()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" title="Clear"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                        </div>
                        <div class="relative">
                            <input type="datetime-local" x-model="filters.dateTo" @change="resetPaginationAndFetch()" class="px-3 py-2 pr-7 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                            <button x-show="filters.dateTo" @click="filters.dateTo = ''; resetPaginationAndFetch()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" title="Clear"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                        </div>
                        <button @click="filters.showAdvanced = !filters.showAdvanced" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition" :class="filters.showAdvanced ? 'bg-brand/10 text-brand' : 'text-gray-600 hover:bg-gray-50'">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                            More
                        </button>
                        <button x-show="hasActiveFilters()" @click="clearFilters()" class="text-[11px] font-semibold text-red-600 hover:text-red-800 transition">Clear all</button>
                    </div>

                    {{-- Advanced filters --}}
                    <div x-show="filters.showAdvanced" x-transition class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Job Class</label>
                            <input type="text" x-model.debounce.300ms="filters.jobClass" @input="resetPaginationAndFetch()" placeholder="e.g. SendEmail" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Server</label>
                            <input type="text" x-model.debounce.300ms="filters.server" @input="resetPaginationAndFetch()" placeholder="hostname" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Attempts</label>
                            <select x-model="filters.minAttempts" @change="resetPaginationAndFetch()" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                                <option value="">Any</option><option value="2">2+ (retried)</option><option value="3">3+</option><option value="5">5+</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Duration</label>
                            <select x-model="filters.minDuration" @change="resetPaginationAndFetch()" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                                <option value="">Any</option><option value="1000">1s+ (slow)</option><option value="5000">5s+ (very slow)</option><option value="10000">10s+ (outlier)</option><option value="30000">30s+ (extreme)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Sort By</label>
                            <select x-model="sorting.field" @change="fetchJobs()" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                                <option value="queued_at">Queued At</option><option value="duration_ms">Duration</option><option value="job_class">Job Class</option><option value="status">Status</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Direction</label>
                            <select x-model="sorting.direction" @change="fetchJobs()" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                                <option value="desc">Newest First</option><option value="asc">Oldest First</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Bulk Actions --}}
                <div x-show="selectedJobs.length > 0" x-transition class="bg-brand-faint border border-brand/20 rounded-xl p-3 mb-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-brand" x-text="selectedJobs.length + ' job' + (selectedJobs.length === 1 ? '' : 's') + ' selected'"></span>
                    <div class="flex gap-2">
                        <button @click="batchReplay()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 border border-amber-200 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                            Replay selected
                        </button>
                        <button @click="batchDelete()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200 transition">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            Delete selected
                        </button>
                    </div>
                </div>

                {{-- Jobs Table --}}
                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr class="bg-gray-50/60">
                                    <th class="px-4 py-2.5 w-10"><input type="checkbox" @change="toggleAllJobs($event)" :checked="selectedJobs.length > 0 && selectedJobs.length === jobs.data.length" class="rounded border-gray-300 text-brand focus:ring-brand"></th>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('status')"><span class="flex items-center gap-1">Status <span x-text="sortIndicator('status')"></span></span></th>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('job_class')"><span class="flex items-center gap-1">Job <span x-text="sortIndicator('job_class')"></span></span></th>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Queue</th>
                                    <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('duration_ms')"><span class="flex items-center gap-1 justify-end">Duration <span x-text="sortIndicator('duration_ms')"></span></span></th>
                                    <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">CPU</th>
                                    <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Memory</th>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Server</th>
                                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider">Attempt</th>
                                    <th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('queued_at')"><span class="flex items-center gap-1 justify-end">Time <span x-text="sortIndicator('queued_at')"></span></span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-if="loading.jobs">
                                    <template x-for="i in 8" :key="'jskel-'+i">
                                        <tr><td class="px-4 py-3"><div class="h-4 w-4 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-5 w-16 shimmer rounded-full"></div></td><td class="px-4 py-3"><div class="h-4 w-36 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-4 w-16 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-4 w-14 shimmer rounded ml-auto"></div></td><td class="px-4 py-3"><div class="h-4 w-20 shimmer rounded"></div></td><td class="px-4 py-3"><div class="h-4 w-6 shimmer rounded mx-auto"></div></td><td class="px-4 py-3"><div class="h-4 w-24 shimmer rounded ml-auto"></div></td></tr>
                                    </template>
                                </template>
                                <template x-if="!loading.jobs">
                                    <template x-for="job in jobs.data" :key="job.uuid">
                                        <tr class="hover:bg-brand-faint/40 cursor-pointer transition-colors" @click="openJobView(job.uuid)">
                                            <td class="px-4 py-2.5" @click.stop><input type="checkbox" :value="job.uuid" x-model="selectedJobs" class="rounded border-gray-300 text-brand focus:ring-brand"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(job.status.value)" x-text="job.status.label"></span></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-sm font-mono text-brand hover:underline drill-arrow" x-text="job.job_class" @click.stop="openDrillDown('job_class', job.full_job_class || job.job_class)"></span>
                                                    <span x-show="job.attempt > 1" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded-full" :class="job.is_failed ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200'" x-text="'x' + job.attempt" :title="'Attempt ' + job.attempt + ' of ' + (job.max_attempts || '?')"></span>
                                                </div>
                                                <div x-show="job.is_failed && job.error" class="text-[11px] text-red-500 truncate max-w-xs mt-0.5" x-text="job.error"></div>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-brand hover:underline drill-arrow" x-text="job.queue" @click.stop="openDrillDown('queue', job.queue)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatDuration(job.duration_ms)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatCpu(job.cpu_time_ms, job.duration_ms)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-500 text-right font-mono tabular-nums" x-text="formatMemoryShort(job.memory_peak_mb, job.worker_memory_limit_mb)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-[11px] text-brand hover:underline drill-arrow font-mono truncate max-w-[120px]" x-text="job.server" @click.stop="openDrillDown('server', job.server)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-center">
                                                <span x-show="job.attempt <= 1" class="text-sm text-gray-400">1</span>
                                                <span x-show="job.attempt > 1" class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded-full" :class="job.is_failed ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'" x-text="job.attempt + '/' + (job.max_attempts || '?')"></span>
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-[11px] text-gray-400 text-right" x-text="formatTime(job.queued_at)"></td>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div x-show="!loading.jobs && jobs.data.length === 0" class="px-6 py-16 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 mb-3">
                            <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                        </div>
                        <p class="text-sm font-medium text-gray-500">No jobs match your filters</p>
                        <p class="text-[11px] text-gray-400 mt-1">Try adjusting or clearing your filters</p>
                    </div>
                    <div x-show="jobs.meta.total > 0" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/40">
                        <span class="text-[11px] text-gray-500">Showing <span x-text="pagination.offset + 1"></span>-<span x-text="Math.min(pagination.offset + pagination.limit, jobs.meta.total)"></span> of <span x-text="formatNumber(jobs.meta.total)"></span></span>
                        <div class="flex gap-2">
                            <button @click="prevPage()" :disabled="pagination.offset === 0" class="px-3 py-1.5 text-[11px] font-medium border border-gray-200 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">Previous</button>
                            <button @click="nextPage()" :disabled="pagination.offset + pagination.limit >= jobs.meta.total" class="px-3 py-1.5 text-[11px] font-medium border border-gray-200 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">Next</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== ANALYTICS TAB ==================== --}}
            <div x-show="activeTab === 'analytics'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Analytics</h3>
                    <button @click="fetchAnalytics()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        <svg class="h-3.5 w-3.5" :class="loading.analytics && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                        Refresh
                    </button>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Job Class Distribution</h4></div>
                        <div class="p-4"><div id="distribution-chart" style="height: 280px;"></div></div>
                        <div x-show="!loading.analytics && (analytics.job_classes || []).length === 0" class="px-5 py-12 text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2"><svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /></svg></div>
                            <p class="text-sm text-gray-400">No job data available</p>
                        </div>
                    </div>
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Per-Queue Statistics</h4></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead><tr class="bg-gray-50/60"><th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase">Queue</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Total</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Completed</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Failed</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Avg ms</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Success %</th></tr></thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="q in (analytics.queues || [])" :key="q.queue">
                                        <tr class="hover:bg-gray-50/60">
                                            <td class="px-4 py-2.5 text-sm font-medium text-brand hover:underline cursor-pointer drill-arrow" x-text="q.queue" @click="openDrillDown('queue', q.queue)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(q.total_jobs)"></td>
                                            <td class="px-4 py-2.5 text-sm text-emerald-600 text-right tabular-nums" x-text="formatNumber(q.completed)"></td>
                                            <td class="px-4 py-2.5 text-sm text-red-600 text-right tabular-nums" x-text="formatNumber(q.failed)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(q.avg_duration_ms, 0)"></td>
                                            <td class="px-4 py-2.5 text-sm text-right font-medium tabular-nums" :class="q.success_rate >= 95 ? 'text-emerald-600' : q.success_rate >= 80 ? 'text-amber-600' : 'text-red-600'" x-text="formatNumber(q.success_rate, 1) + '%'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div x-show="!loading.analytics && (analytics.queues || []).length === 0" class="px-5 py-12 text-center text-sm text-gray-400">No queue data</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Per-Server Statistics</h4></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead><tr class="bg-gray-50/60"><th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase">Server</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Jobs</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Avg ms</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Success %</th></tr></thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="s in (analytics.servers || [])" :key="s.server_name">
                                        <tr class="hover:bg-gray-50/60">
                                            <td class="px-4 py-2.5 text-sm font-mono text-brand hover:underline cursor-pointer drill-arrow" x-text="s.server_name" @click="openDrillDown('server', s.server_name)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(s.total_jobs)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(s.avg_duration_ms, 0)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 tabular-nums" x-text="formatNumber(s.success_rate, 1) + '%'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div x-show="!loading.analytics && (analytics.servers || []).length === 0" class="px-5 py-12 text-center text-sm text-gray-400">No server data</div>
                    </div>
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Failure Patterns</h4></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead><tr class="bg-gray-50/60"><th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase">Exception</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase">Count</th><th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase">Affected Jobs</th></tr></thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="fp in (analytics.failure_patterns?.top_exceptions || [])" :key="fp.exception_class">
                                        <tr class="hover:bg-gray-50/60">
                                            <td class="px-4 py-2.5 text-sm font-mono text-red-700 cursor-pointer hover:underline truncate max-w-[200px]" x-text="shortClass(fp.exception_class)" @click="filterJobsByException(fp.exception_class)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(fp.count)"></td>
                                            <td class="px-4 py-2.5 text-sm text-gray-500 text-right tabular-nums" x-text="formatNumber(fp.affected_job_classes)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div x-show="!loading.analytics && (analytics.failure_patterns?.top_exceptions || []).length === 0" class="px-5 py-12 text-center text-sm text-gray-400">No failure patterns detected</div>
                    </div>
                </div>
            </div>

            {{-- ==================== HEALTH TAB ==================== --}}
            <div x-show="activeTab === 'health'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">System Health</h3>
                    <button @click="fetchHealth()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        <svg class="h-3.5 w-3.5" :class="loading.health && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                        Refresh
                    </button>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-8 flex flex-col items-center justify-center">
                        <template x-if="loading.health"><div class="h-24 w-24 shimmer rounded-full"></div></template>
                        <template x-if="!loading.health">
                            <div>
                                <div class="text-6xl font-bold text-center tabular-nums" :class="health.score >= 80 ? 'text-emerald-600' : health.score >= 50 ? 'text-amber-600' : 'text-red-600'" x-text="health.score ?? '-'"></div>
                                <div class="text-sm text-gray-500 text-center mt-2">Health Score</div>
                                <div class="mt-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold" :class="{ 'bg-emerald-100 text-emerald-700': health.status === 'healthy', 'bg-amber-100 text-amber-700': health.status === 'degraded', 'bg-red-100 text-red-700': health.status === 'unhealthy' || health.status === 'critical' }" x-text="(health.status || 'unknown').toUpperCase()"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden lg:col-span-2">
                        <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Health Checks</h4></div>
                        <div class="divide-y divide-gray-50">
                            <template x-for="name in Object.keys(health.checks || {})" :key="name">
                                <div class="px-5 py-3 flex items-start gap-3">
                                    <span class="mt-0.5">
                                        <template x-if="health.checks[name].healthy"><svg class="h-5 w-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"></path></svg></template>
                                        <template x-if="!health.checks[name].healthy"><svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path></svg></template>
                                    </span>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-900 capitalize" x-text="name.replace(/_/g, ' ')"></div>
                                        <div class="text-[11px] text-gray-500 mt-0.5" x-text="health.checks[name].message"></div>
                                        <template x-if="name === 'stuck_jobs' && health.checks[name].details?.stuck_jobs?.length > 0">
                                            <div class="mt-2 space-y-1.5">
                                                <template x-for="sj in health.checks[name].details.stuck_jobs" :key="sj.uuid">
                                                    <div class="flex items-center gap-2 text-[11px] bg-red-50 border border-red-100 rounded-lg px-3 py-1.5">
                                                        <span class="font-mono font-medium text-red-800 cursor-pointer hover:underline" x-text="shortClass(sj.job_class)" @click="openJobView(sj.uuid)"></span>
                                                        <span class="text-red-600" x-text="'-> ' + sj.queue"></span>
                                                        <span class="text-red-400" x-text="'on ' + sj.server"></span>
                                                        <span class="text-red-400 ml-auto" x-text="'since ' + formatTime(sj.stuck_since)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="!loading.health && Object.keys(health.checks || {}).length === 0" class="py-10 text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2"><svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                            <p class="text-sm text-gray-400">No health checks configured</p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Active Alerts</h4></div>
                    <div class="divide-y divide-gray-50">
                        <template x-for="alert in (health.alerts?.active || [])" :key="alert.message">
                            <div class="px-5 py-3 flex items-start gap-3">
                                <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="{ 'bg-red-100 text-red-700': alert.severity === 'critical', 'bg-amber-100 text-amber-700': alert.severity === 'warning', 'bg-blue-100 text-blue-700': alert.severity === 'info' }" x-text="alert.severity"></span>
                                <span class="text-sm text-gray-700" x-text="alert.message"></span>
                            </div>
                        </template>
                    </div>
                    <div x-show="(health.alerts?.active || []).length === 0" class="py-10 text-center">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 mb-2"><svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                        <p class="text-sm text-gray-400">No active alerts</p>
                    </div>
                </div>
            </div>

            {{-- ==================== INFRASTRUCTURE TAB ==================== --}}
            <div x-show="activeTab === 'infrastructure'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Infrastructure</h3>
                    <button @click="fetchInfrastructure()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        <svg class="h-3.5 w-3.5" :class="loading.infrastructure && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                        Refresh
                    </button>
                </div>
                <template x-if="loading.infrastructure">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6"><div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-8"><div class="h-24 w-24 shimmer rounded-full mx-auto"></div></div><div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-6 lg:col-span-2"><div class="h-6 w-48 shimmer rounded mb-4"></div><div class="h-4 w-full shimmer rounded mb-2"></div><div class="h-4 w-3/4 shimmer rounded"></div></div></div>
                    </div>
                </template>
                <template x-if="!loading.infrastructure">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-8 flex flex-col items-center justify-center">
                                <div class="relative">
                                    <svg class="h-44 w-44" viewBox="0 0 160 160">
                                        <defs><linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#10b981" /><stop offset="60%" stop-color="#f59e0b" /><stop offset="100%" stop-color="#ef4444" /></linearGradient></defs>
                                        <circle cx="80" cy="80" r="65" fill="none" stroke="#f3f4f6" stroke-width="12" />
                                        <circle cx="80" cy="80" r="65" fill="none" stroke="url(#gaugeGradient)" stroke-width="12" stroke-linecap="round" :stroke-dasharray="(2 * Math.PI * 65 * (infrastructure.scaling?.utilization?.percentage ?? 0) / 100) + ' ' + (2 * Math.PI * 65)" transform="rotate(-90 80 80)" style="transition: stroke-dasharray 0.6s ease" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-4xl font-bold tabular-nums" :class="(infrastructure.scaling?.utilization?.percentage ?? 0) > 85 ? 'text-red-600' : (infrastructure.scaling?.utilization?.percentage ?? 0) > 60 ? 'text-emerald-600' : (infrastructure.scaling?.utilization?.percentage ?? 0) > 30 ? 'text-amber-600' : 'text-gray-500'" x-text="(infrastructure.scaling?.utilization?.percentage ?? 0) + '%'"></span>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-1">Utilization</span>
                                    </div>
                                </div>
                                <div class="mt-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold" :class="{ 'bg-gray-100 text-gray-600': (infrastructure.scaling?.utilization?.status) === 'idle', 'bg-amber-100 text-amber-700': (infrastructure.scaling?.utilization?.status) === 'underutilized', 'bg-emerald-100 text-emerald-700': (infrastructure.scaling?.utilization?.status) === 'optimal', 'bg-red-100 text-red-700': (infrastructure.scaling?.utilization?.status) === 'overloaded' }" x-text="(infrastructure.scaling?.utilization?.status || 'unknown').replace('_', ' ').toUpperCase()"></span>
                                </div>
                            </div>
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden lg:col-span-2">
                                <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Worker Overview</h4></div>
                                <div class="p-5">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">
                                        <div><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Workers</div><div class="text-2xl font-bold text-gray-900 tabular-nums"><span x-text="infrastructure.scaling?.utilization?.busy_workers ?? 0"></span><span class="text-gray-400 text-lg font-normal"> / </span><span x-text="infrastructure.scaling?.utilization?.total_workers ?? 0"></span></div><div class="text-[10px] text-gray-400 mt-0.5">busy / total</div></div>
                                        <div><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Processing (1h)</div><div class="text-2xl font-bold text-gray-900 tabular-nums" x-text="formatDuration(infrastructure.scaling?.utilization?.total_processing_ms ?? 0)"></div></div>
                                        <div x-show="infrastructure.workers?.available"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Jobs/Min</div><div class="text-2xl font-bold text-brand tabular-nums" x-text="infrastructure.workers?.jobs_per_minute ?? '-'"></div></div>
                                    </div>
                                    <template x-if="infrastructure.workers?.available && (infrastructure.workers?.supervisors ?? []).length > 0">
                                        <div class="mt-4 border-t border-gray-100 pt-4">
                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Horizon Supervisors</div>
                                            <div class="space-y-2">
                                                <template x-for="sup in infrastructure.workers.supervisors" :key="sup.name">
                                                    <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-gray-50">
                                                        <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full" :class="sup.status === 'running' ? 'bg-emerald-500' : 'bg-gray-400'"></span><span class="text-sm font-medium text-gray-700" :title="sup.name" x-text="sup.name.includes(':') ? sup.name.split(':').pop() : sup.name"></span></div>
                                                        <div class="flex items-center gap-3"><span class="text-[11px] text-gray-500" x-text="sup.processes + ' processes'"></span><span class="text-[10px] text-gray-400" x-text="sup.queues.join(', ')"></span></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="!infrastructure.workers?.available" class="mt-4 border-t border-gray-100 pt-4"><div class="text-[11px] text-gray-400 italic">Horizon not detected. Install Laravel Horizon for detailed worker metrics.</div></div>
                                </div>
                            </div>
                        </div>

                        {{-- Worker Type Breakdown --}}
                        <div x-show="(infrastructure.worker_types?.by_type || []).length > 0" class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Queue Managers</h4><p class="text-[11px] text-gray-400 mt-0.5">Which manager handles which queue (last hour)</p></div>
                            <div class="divide-y divide-gray-50">
                                <template x-for="mgr in (infrastructure.worker_types?.by_type || [])" :key="mgr.type">
                                    <div class="px-5 py-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="{ 'bg-purple-50 text-purple-700': mgr.type === 'horizon', 'bg-blue-50 text-blue-700': mgr.type === 'autoscale', 'bg-gray-100 text-gray-600': mgr.type === 'queue_work' }" x-text="mgr.label"></span>
                                                <span class="text-[11px] text-gray-500" x-text="mgr.total_jobs + ' jobs · ' + mgr.total_workers + ' workers'"></span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="item in mgr.breakdown" :key="item.queue">
                                                <button @click="openDrillDown('queue', item.queue)" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md border transition-colors cursor-pointer" :class="item.failed > 0 ? 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                                                    <span class="font-medium" x-text="item.queue"></span>
                                                    <span class="text-[10px] opacity-60" x-text="item.total + ' jobs'"></span>
                                                    <span x-show="item.unique_workers > 0" class="text-[10px] opacity-60" x-text="'· ' + item.unique_workers + 'w'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Unhandled Queues --}}
                        <template x-if="hasUnhandledQueues()">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 flex items-start gap-3">
                                    <svg class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path></svg>
                                    <div>
                                        <h4 class="text-sm font-semibold text-amber-800">Unhandled Queues Detected</h4>
                                        <p class="text-[11px] text-amber-700 mt-1">Jobs are being dispatched to queues with no active workers.</p>
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            <template x-for="q in getUnhandledQueues()" :key="q.queue">
                                                <button @click="drillDownToJobs('queue', q.queue)" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md bg-amber-100 border border-amber-300 text-amber-800 hover:bg-amber-200 cursor-pointer font-medium"><span x-text="q.queue"></span><span class="text-[10px] opacity-70" x-text="q.pending + ' pending'"></span></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Queue Capacity --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Queue Capacity</h4></div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100" x-show="(infrastructure.capacity?.queues || []).length > 0">
                                    <thead><tr class="bg-gray-50/60"><th class="px-4 py-2.5 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Queue</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Workers</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Avg Duration</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Max Jobs/min</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Peak Jobs/min</th><th class="px-4 py-2.5 text-right text-[10px] font-bold text-gray-400 uppercase tracking-wider">Headroom</th><th class="px-4 py-2.5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</th></tr></thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <template x-for="q in (infrastructure.capacity?.queues || [])" :key="q.queue">
                                            <tr class="hover:bg-gray-50/60 transition-colors">
                                                <td class="px-4 py-2.5 text-sm font-medium text-gray-900" x-text="q.queue"></td>
                                                <td class="px-4 py-2.5 text-sm text-gray-600 text-right tabular-nums" x-text="q.workers"></td>
                                                <td class="px-4 py-2.5 text-sm text-gray-600 text-right tabular-nums" x-text="formatDuration(q.avg_duration_ms)"></td>
                                                <td class="px-4 py-2.5 text-sm text-gray-600 text-right tabular-nums" x-text="q.max_jobs_per_minute"></td>
                                                <td class="px-4 py-2.5 text-sm text-gray-600 text-right tabular-nums" x-text="q.peak_jobs_per_minute"></td>
                                                <td class="px-4 py-2.5 text-sm text-right tabular-nums" :class="q.headroom_percent < 15 ? 'text-red-600 font-semibold' : q.headroom_percent < 40 ? 'text-amber-600' : 'text-gray-600'" x-text="q.headroom_percent + '%'"></td>
                                                <td class="px-4 py-2.5 text-center"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="{ 'bg-blue-100 text-blue-700': q.status === 'over_provisioned', 'bg-emerald-50 text-emerald-700': q.status === 'optimal', 'bg-red-50 text-red-700': q.status === 'at_capacity', 'bg-gray-100 text-gray-600': q.status === 'no_data' }" x-text="q.status === 'over_provisioned' ? 'Over-provisioned' : q.status === 'at_capacity' ? 'At Capacity' : q.status === 'optimal' ? 'Optimal' : 'No Data'"></span></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div x-show="(infrastructure.capacity?.queues || []).length === 0" class="py-10 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2"><svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" /></svg></div>
                                <p class="text-sm text-gray-400">No queue capacity data</p>
                            </div>
                        </div>

                        {{-- SLA Compliance --}}
                        <div x-show="(infrastructure.sla?.per_queue || []).length > 0">
                            <div class="mb-3"><h4 class="text-sm font-semibold text-gray-900">SLA Compliance <span class="text-[11px] font-normal text-gray-400">(Pickup Time - Last Hour<span x-show="infrastructure.sla?.source === 'autoscale'"> · targets from autoscale config</span>)</span></h4></div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <template x-for="sla in (infrastructure.sla?.per_queue || [])" :key="sla.queue">
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4" :class="sla.compliance < 95 ? 'pulse-alert border-red-200' : ''">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-semibold text-brand hover:underline cursor-pointer" x-text="sla.queue" @click="openDrillDown('queue', sla.queue)"></span>
                                            <span class="text-[10px] font-medium text-gray-400" x-text="'<' + sla.target_seconds + 's target'"></span>
                                        </div>
                                        <div class="flex items-baseline gap-2 mb-2">
                                            <span class="text-2xl font-bold tabular-nums" :class="sla.compliance >= 99 ? 'text-emerald-600' : sla.compliance >= 95 ? 'text-amber-600' : 'text-red-600'" x-text="sla.compliance + '%'"></span>
                                            <span class="text-[11px] text-gray-400" x-text="sla.within + '/' + sla.total + ' jobs'"></span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-2"><div class="h-2 rounded-full transition-all duration-500" :class="sla.compliance >= 99 ? 'bg-emerald-500' : sla.compliance >= 95 ? 'bg-amber-500' : 'bg-red-500'" :style="'width: ' + sla.compliance + '%'"></div></div>
                                        <div x-show="sla.breached > 0" class="mt-1.5 text-[10px] text-red-500 font-semibold" x-text="sla.breached + ' breached'"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Autoscale Activity --}}
                        <div x-show="infrastructure.scaling?.has_autoscale">
                                <div class="mb-3"><h4 class="text-sm font-semibold text-gray-900">Autoscale Activity <span class="text-[11px] font-normal text-gray-400">(Last Hour)</span></h4></div>
                                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-4 stagger-in">
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Decisions</div><div class="text-2xl font-bold text-gray-900 tabular-nums" x-text="infrastructure.scaling?.summary?.total_decisions ?? 0"></div></div>
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Scale Ups</div><div class="text-2xl font-bold text-emerald-600 tabular-nums" x-text="infrastructure.scaling?.summary?.scale_ups ?? 0"></div></div>
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Scale Downs</div><div class="text-2xl font-bold text-blue-600 tabular-nums" x-text="infrastructure.scaling?.summary?.scale_downs ?? 0"></div></div>
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">SLA Breaches</div><div class="text-2xl font-bold tabular-nums" :class="(infrastructure.scaling?.summary?.sla_breaches ?? 0) > 0 ? 'text-red-600' : 'text-gray-900'" x-text="infrastructure.scaling?.summary?.sla_breaches ?? 0"></div></div>
                                    <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 col-span-2 lg:col-span-1"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">SLA Recoveries</div><div class="text-2xl font-bold text-emerald-600 tabular-nums" x-text="infrastructure.scaling?.summary?.sla_recoveries ?? 0"></div></div>
                                </div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Scaling Timeline</h4></div>
                                    <div class="max-h-96 overflow-y-auto custom-scroll divide-y divide-gray-50">
                                        <template x-for="(event, idx) in (infrastructure.scaling?.history || [])" :key="idx">
                                            <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                                <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full" :class="{ 'bg-emerald-500': event.action === 'scale_up', 'bg-blue-500': event.action === 'scale_down', 'bg-red-500': event.action === 'sla_breach', 'bg-emerald-400': event.action === 'sla_recovered', 'bg-gray-400': event.action === 'hold' }"></span></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="{ 'bg-emerald-50 text-emerald-700': event.action === 'scale_up', 'bg-blue-50 text-blue-700': event.action === 'scale_down', 'bg-red-50 text-red-700': event.action === 'sla_breach', 'bg-emerald-50 text-emerald-600': event.action === 'sla_recovered', 'bg-gray-100 text-gray-600': event.action === 'hold' }" x-text="event.action.replace('_', ' ').toUpperCase()"></span>
                                                        <span class="text-sm font-medium text-gray-900" x-text="event.queue"></span>
                                                        <span x-show="event.current_workers !== event.target_workers" class="text-[11px] text-gray-500" x-text="event.current_workers + ' -> ' + event.target_workers + ' workers'"></span>
                                                        <span x-show="event.sla_breach_risk" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-bold bg-red-100 text-red-700">SLA Risk</span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-500 mt-0.5 truncate" x-text="event.reason"></p>
                                                </div>
                                                <div class="flex-shrink-0 text-right"><span class="text-[10px] text-gray-400" x-text="event.time_human"></span></div>
                                            </div>
                                        </template>
                                    </div>
                                    <div x-show="(infrastructure.scaling?.history || []).length === 0" class="py-10 text-center">
                                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2"><svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-3L16.5 18m0 0L12 13.5m4.5 4.5V4.5" /></svg></div>
                                        <p class="text-sm text-gray-400">No scaling events recorded</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Horizon Workload --}}
                        <div x-show="infrastructure.workers?.available && (infrastructure.workers?.workload || []).length > 0" class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Horizon Workload</h4></div>
                                <div x-show="infrastructure.workers.workload.every(w => w.length === 0 && w.wait === 0)" class="py-10 text-center">
                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 mb-2"><svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                                    <p class="text-sm text-emerald-600 font-medium">All queues clear</p>
                                    <p class="text-[11px] text-gray-400 mt-1">No pending jobs across <span x-text="infrastructure.workers.workload.length"></span> queues</p>
                                </div>
                                <div x-show="!infrastructure.workers.workload.every(w => w.length === 0 && w.wait === 0)" class="divide-y divide-gray-50">
                                    <template x-for="w in infrastructure.workers.workload" :key="w.queue">
                                        <div class="px-5 py-3">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="text-sm font-medium text-gray-900" x-text="w.queue"></span>
                                                <div class="flex items-center gap-4 text-[11px] text-gray-500"><span x-text="w.length + ' pending'"></span><span x-text="w.wait + 's wait'"></span><span x-text="w.processes + ' workers'"></span></div>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-2"><div class="h-2 rounded-full transition-all duration-500" :class="w.length > 100 ? 'bg-red-500' : w.length > 50 ? 'bg-amber-500' : 'bg-brand'" :style="'width: ' + Math.min(100, (w.length / Math.max(1, ...infrastructure.workers.workload.map(x => x.length))) * 100) + '%'"></div></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            </div>{{-- END TAB CONTENT --}}

            {{-- ==================== FULL-PAGE JOB DETAIL VIEW ==================== --}}
            <div x-show="jobView" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

                {{-- Back nav + header --}}
                <div class="mb-6">
                    <button @click="closeJobView()" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition mb-4 group">
                        <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                        Back to <span x-text="previousTab === 'jobs' ? 'Jobs' : 'Overview'"></span>
                    </button>

                    {{-- Loading state --}}
                    <div x-show="loading.detail" class="space-y-4">
                        <div class="h-8 w-64 shimmer rounded"></div>
                        <div class="h-4 w-96 shimmer rounded"></div>
                        <div class="grid grid-cols-4 gap-4 mt-6"><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div></div>
                    </div>

                    {{-- Loaded state --}}
                    <div x-show="!loading.detail && jobDetail">
                        {{-- Job header --}}
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusClass(jobDetail?.job?.status?.value)" x-text="jobDetail?.job?.status?.label"></span>
                                    <h1 class="text-xl font-bold text-gray-900" x-text="jobDetail?.job?.short_job_class"></h1>
                                </div>
                                <div class="flex items-center gap-3 text-[11px] text-gray-500">
                                    <span class="font-mono text-gray-400 select-all" x-text="jobDetail?.job?.uuid"></span>
                                    <button @click="copyToClipboard(window.location.href)" class="text-gray-400 hover:text-brand transition" title="Copy link">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-4.122a4.5 4.5 0 00-6.364-6.364L4.5 6.75a4.5 4.5 0 006.364 6.364l4.5-4.5z" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="replayJob(jobDetail?.job?.uuid)" class="inline-flex items-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 border border-amber-200 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                                    Replay
                                </button>
                                <button @click="confirmDeleteJob(jobDetail?.job?.uuid)" class="inline-flex items-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79" /></svg>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detail content grid --}}
                <div x-show="!loading.detail && jobDetail" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- Left column: Details + Metrics --}}
                    <div class="lg:col-span-2 space-y-6">

                        {{-- Metadata cards --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 stagger-in">
                            <div class="bg-white border border-gray-200/80 rounded-xl p-4">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Duration</div>
                                <div class="text-lg font-bold font-mono text-gray-900 tabular-nums" x-text="formatDuration(jobDetail?.job?.metrics?.duration_ms)"></div>
                            </div>
                            <div class="bg-white border border-gray-200/80 rounded-xl p-4">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">CPU</div>
                                <div class="text-lg font-bold font-mono text-gray-900 tabular-nums" x-text="formatCpu(jobDetail?.job?.metrics?.cpu_time_ms, jobDetail?.job?.metrics?.duration_ms)"></div>
                            </div>
                            <div class="bg-white border border-gray-200/80 rounded-xl p-4">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Memory</div>
                                <div class="text-lg font-bold font-mono tabular-nums" :class="jobDetail?.job?.metrics?.worker_memory_limit_mb && (jobDetail.job.metrics.memory_peak_mb / jobDetail.job.metrics.worker_memory_limit_mb) > 0.8 ? 'text-red-600' : 'text-gray-900'" x-text="formatMemory(jobDetail?.job?.metrics?.memory_peak_mb, jobDetail?.job?.metrics?.worker_memory_limit_mb)"></div>
                            </div>
                            <div class="bg-white border border-gray-200/80 rounded-xl p-4">
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Attempt</div>
                                <div class="text-lg font-bold text-gray-900 tabular-nums" x-text="(jobDetail?.job?.attempt || '-') + (jobDetail?.job?.max_attempts ? ' / ' + jobDetail.job.max_attempts : '')"></div>
                            </div>
                        </div>

                        {{-- Exception (if failed) --}}
                        <div x-show="jobDetail?.exception" class="bg-white border border-red-200 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-red-100 bg-red-50/50 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-red-800 flex items-center gap-2">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                    Exception
                                </h3>
                                <button @click="copyToClipboard(formatExceptionMarkdown(jobDetail?.exception))"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg transition"
                                        title="Copy as markdown (paste to Claude Code)">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                    Copy
                                </button>
                            </div>
                            <div class="p-5 space-y-3">
                                <div class="text-sm font-mono font-semibold text-red-800" x-text="jobDetail?.exception?.short_class"></div>
                                <div class="text-sm text-red-700" x-text="jobDetail?.exception?.message"></div>
                                <div class="bg-gray-950 rounded-lg overflow-hidden">
                                    <div class="max-h-[500px] overflow-y-auto custom-scroll p-4 font-mono text-[11px] leading-relaxed" x-html="formatStackTrace(jobDetail?.exception?.trace)"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Payload --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden" x-data="{ showRaw: false }">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>
                                    Payload
                                </h3>
                            </div>
                            <template x-if="jobDetail?.payload">
                                <div class="p-5">
                                    {{-- Meta badges --}}
                                    <div class="flex flex-wrap gap-2 mb-4" x-data="{ parsed: parsePayload(jobDetail.payload) }">
                                        <template x-if="parsed.meta.maxTries"><span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-gray-100 text-gray-600 rounded-full">max tries: <span x-text="parsed.meta.maxTries" class="font-semibold"></span></span></template>
                                        <template x-if="parsed.meta.timeout !== undefined"><span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-gray-100 text-gray-600 rounded-full">timeout: <span x-text="parsed.meta.timeout ?? 'none'" class="font-semibold"></span></span></template>
                                        <template x-if="parsed.meta.maxExceptions"><span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-gray-100 text-gray-600 rounded-full">max exceptions: <span x-text="parsed.meta.maxExceptions" class="font-semibold"></span></span></template>
                                        <template x-if="parsed.meta.backoff"><span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-gray-100 text-gray-600 rounded-full">backoff: <span x-text="parsed.meta.backoff" class="font-semibold"></span></span></template>
                                    </div>
                                    {{-- Command --}}
                                    <div x-show="jobDetail.payload?.data?.commandName" class="mb-4">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1.5">Command</div>
                                        <div class="text-sm font-mono font-medium text-gray-800 bg-gray-50 rounded-lg px-3 py-2" x-text="jobDetail.payload.data.commandName"></div>
                                    </div>
                                    {{-- Extracted properties --}}
                                    <div class="mb-4" x-data="{ parsed: parsePayload(jobDetail.payload) }">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Job Parameters</div>
                                        <div class="space-y-1.5">
                                            <template x-for="prop in parsed.properties" :key="prop.name">
                                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg">
                                                    <span class="text-[11px] font-mono font-semibold text-gray-700" x-text="prop.name"></span>
                                                    <span class="text-[11px] text-gray-400">=</span>
                                                    <span class="text-[11px] font-mono text-brand" x-text="prop.value"></span>
                                                </div>
                                            </template>
                                        </div>
                                        <div x-show="parsed.properties.length === 0" class="text-[11px] text-gray-400 mt-1">No extractable parameters</div>
                                    </div>
                                    {{-- Raw toggle --}}
                                    <button @click="showRaw = !showRaw" class="text-[11px] text-gray-400 hover:text-gray-600 transition mb-2 flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5 transition-transform" :class="showRaw ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                        <span x-text="showRaw ? 'Hide raw payload' : 'Show raw payload'"></span>
                                    </button>
                                    <div x-show="showRaw" x-transition class="bg-gray-900 rounded-lg p-4 overflow-x-auto max-h-80 overflow-y-auto custom-scroll">
                                        <pre class="json-viewer text-[11px] text-green-400 whitespace-pre-wrap break-words font-mono" x-text="JSON.stringify(jobDetail.payload, null, 2)"></pre>
                                    </div>
                                </div>
                            </template>
                            <div x-show="!jobDetail?.payload" class="px-5 py-12 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 mb-2"><svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg></div>
                                <p class="text-sm text-gray-400">No payload data stored</p>
                            </div>
                        </div>
                    </div>

                    {{-- Right column: Info + Retry Timeline --}}
                    <div class="space-y-6">

                        {{-- Job Details --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Details</h3></div>
                            <div class="p-5 space-y-4">
                                <dl class="space-y-3">
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Full Class</dt><dd class="text-[11px] font-mono text-brand hover:underline cursor-pointer truncate max-w-[180px]" x-text="jobDetail?.job?.job_class" @click="openDrillDown('job_class', jobDetail?.job?.job_class)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Queue</dt><dd class="text-[11px] text-brand hover:underline cursor-pointer" x-text="jobDetail?.job?.queue" @click="openDrillDown('queue', jobDetail?.job?.queue)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Connection</dt><dd class="text-[11px] text-gray-900" x-text="jobDetail?.job?.connection"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Server</dt><dd class="text-[11px] font-mono text-brand hover:underline cursor-pointer truncate max-w-[180px]" x-text="jobDetail?.job?.server" @click="openDrillDown('server', jobDetail?.job?.server)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Worker</dt><dd class="text-[11px] text-gray-900" x-text="jobDetail?.job?.worker_type?.label"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">File Descriptors</dt><dd class="text-[11px] text-gray-900" x-text="jobDetail?.job?.metrics?.file_descriptors || '-'"></dd></div>
                                </dl>
                                {{-- Tags --}}
                                <div x-show="jobDetail?.job?.tags?.length > 0" class="pt-3 border-t border-gray-100">
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Tags</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="tag in (jobDetail?.job?.tags || [])" :key="tag">
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700" x-text="tag"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Timestamps --}}
                        <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Timestamps</h3></div>
                            <div class="p-5">
                                <dl class="space-y-3">
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Queued</dt><dd class="text-[11px] text-gray-900 font-mono tabular-nums" x-text="formatDateTime(jobDetail?.job?.timestamps?.queued_at)"></dd></div>
                                    <div x-show="jobDetail?.job?.timestamps?.available_at" class="flex justify-between"><dt class="text-[11px] text-gray-500">Available</dt><dd class="text-[11px] text-gray-900 font-mono tabular-nums" x-text="formatDateTime(jobDetail?.job?.timestamps?.available_at)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Started</dt><dd class="text-[11px] text-gray-900 font-mono tabular-nums" x-text="formatDateTime(jobDetail?.job?.timestamps?.started_at)"></dd></div>
                                    <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Completed</dt><dd class="text-[11px] text-gray-900 font-mono tabular-nums" x-text="formatDateTime(jobDetail?.job?.timestamps?.completed_at)"></dd></div>
                                </dl>
                            </div>
                        </div>

                        {{-- Retry Timeline — click navigates to that attempt's full page --}}
                        <div x-show="jobDetail?.retry_chain?.length > 0" class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <h3 class="text-sm font-semibold text-gray-900">Retry Timeline</h3>
                            </div>
                            <div class="divide-y divide-gray-50">
                                <template x-for="(attempt, idx) in (jobDetail?.retry_chain || [])" :key="attempt.uuid">
                                    <div @click="attempt.uuid !== jobView ? openJobView(attempt.uuid) : null"
                                         class="px-5 py-3 flex items-start gap-3 transition-colors"
                                         :class="attempt.uuid === jobView ? 'bg-brand-faint border-l-2 border-l-brand' : 'hover:bg-gray-50 cursor-pointer border-l-2 border-l-transparent'">
                                        {{-- Status dot --}}
                                        <div class="mt-1 flex-shrink-0">
                                            <div class="w-2.5 h-2.5 rounded-full" :class="{
                                                'bg-emerald-500': attempt.status.value === 'completed',
                                                'bg-red-500': attempt.status.value === 'failed' || attempt.status.value === 'timeout',
                                                'bg-blue-500': attempt.status.value === 'processing',
                                                'bg-amber-500': attempt.status.value === 'queued'
                                            }"></div>
                                        </div>
                                        {{-- Content --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold" :class="attempt.uuid === jobView ? 'text-brand' : 'text-gray-900'" x-text="'#' + attempt.attempt"></span>
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusClass(attempt.status.value)" x-text="attempt.status.label"></span>
                                            </div>
                                            <div class="flex items-center gap-3 mt-0.5 text-[11px]">
                                                <span x-show="attempt.started_at" class="text-gray-500 font-mono tabular-nums" x-text="formatDateTime(attempt.started_at)"></span>
                                                <span x-show="attempt.duration_ms" class="text-gray-400">·</span>
                                                <span x-show="attempt.duration_ms" class="text-gray-500 font-mono tabular-nums" x-text="formatDuration(attempt.duration_ms)"></span>
                                                <span x-show="attempt.server_name" class="text-gray-400">·</span>
                                                <span x-show="attempt.server_name" class="text-gray-400 font-mono" x-text="attempt.server_name"></span>
                                            </div>
                                            <div x-show="attempt.exception_message" class="text-[11px] text-red-500 mt-0.5 truncate" x-text="attempt.exception_message"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== FULL-PAGE DRILL-DOWN VIEW ==================== --}}
            <div x-show="drillDown" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

                {{-- Back nav + header --}}
                <div class="mb-6">
                    <button @click="closeDrillDown()" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition mb-4 group">
                        <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                        Back
                    </button>

                    <div class="flex items-center gap-3">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider" x-text="drillDown?.type?.replace('_', ' ')"></div>
                    </div>
                    <template x-if="drillDown?.type === 'job_class'">
                        <h1 class="text-xl font-mono" :title="drillDown?.value">
                            <span class="text-gray-400" x-text="(drillDown?.value || '').replace(/[^\\\\]*$/, '')"></span><span class="font-bold text-gray-900" x-text="shortClass(drillDown?.value)"></span>
                        </h1>
                    </template>
                    <template x-if="drillDown?.type !== 'job_class'">
                        <h1 class="text-xl font-bold text-gray-900" x-text="drillDown?.value"></h1>
                    </template>
                </div>

                {{-- Loading --}}
                <div x-show="drillDownLoading" class="space-y-4">
                    <div class="grid grid-cols-4 gap-4"><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div><div class="h-20 shimmer rounded-xl"></div></div>
                    <div class="h-4 w-1/3 shimmer rounded"></div><div class="h-48 shimmer rounded-xl"></div>
                </div>

                {{-- Content --}}
                <div x-show="!drillDownLoading && drillDownData" class="space-y-6">

                    {{-- Stat cards --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 stagger-in">
                        <div class="bg-white border border-gray-200/80 rounded-xl p-4 card-hover">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums" x-text="formatNumber(drillDownData?.stats?.total)"></div>
                        </div>
                        <div class="bg-white border border-gray-200/80 rounded-xl p-4 card-hover">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Completed</div>
                            <div class="text-2xl font-bold text-emerald-600 tabular-nums" x-text="formatNumber(drillDownData?.stats?.completed)"></div>
                        </div>
                        <div class="bg-white border border-gray-200/80 rounded-xl p-4 card-hover">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Failed</div>
                            <div class="text-2xl font-bold text-red-600 tabular-nums" x-text="formatNumber(drillDownData?.stats?.failed)"></div>
                        </div>
                        <div class="bg-white border border-gray-200/80 rounded-xl p-4 card-hover">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Avg Duration</div>
                            <div class="text-2xl font-bold text-gray-900 font-mono tabular-nums" x-text="formatDuration(drillDownData?.stats?.avg_duration_ms)"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {{-- Left: Throughput + Recent Jobs --}}
                        <div class="lg:col-span-2 space-y-6">
                            {{-- Throughput chart --}}
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Throughput <span class="text-gray-400 font-normal">(1h)</span></h3></div>
                                <div class="p-4"><div id="drilldown-throughput-chart" style="height: 200px;"></div></div>
                            </div>

                            {{-- Recent Jobs --}}
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-900">Recent Jobs</h3>
                                    <button @click="drillDownToJobs(drillDown.type, drillDown.value)" class="text-[11px] font-semibold text-brand hover:text-brand-dark transition drill-arrow">View all in Jobs tab</button>
                                </div>
                                <div class="divide-y divide-gray-50">
                                    <template x-for="job in (drillDownData?.recent_jobs || [])" :key="job.uuid">
                                        <div class="flex items-center gap-3 px-5 py-2.5 hover:bg-brand-faint/40 cursor-pointer transition-colors" @click="closeDrillDown(); openJobView(job.uuid)">
                                            <span class="flex-shrink-0 h-2 w-2 rounded-full" :class="drillDownStatusClass(job.status)"></span>
                                            <div class="flex-1 min-w-0">
                                                <span x-show="job.summary" class="text-sm text-gray-700 truncate block" x-text="job.summary"></span>
                                                <span x-show="!job.summary" class="text-sm text-gray-500 truncate block" x-text="job.job_class"></span>
                                            </div>
                                            <span x-show="job.attempt > 1" class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 flex-shrink-0" x-text="'x' + job.attempt"></span>
                                            <span class="text-[11px] font-mono text-gray-500 flex-shrink-0 tabular-nums" x-text="job.duration"></span>
                                            <span class="text-[11px] text-gray-400 flex-shrink-0" x-text="formatTime(job.queued_at)"></span>
                                        </div>
                                    </template>
                                </div>
                                <div x-show="(drillDownData?.recent_jobs || []).length === 0" class="px-5 py-10 text-center text-sm text-gray-400">No recent jobs</div>
                            </div>
                        </div>

                        {{-- Right: Performance + Failure Patterns --}}
                        <div class="space-y-6">
                            {{-- Performance --}}
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Performance</h3></div>
                                <div class="p-5">
                                    <dl class="space-y-3">
                                        <div class="flex justify-between"><dt class="text-[11px] text-gray-500">p50</dt><dd class="text-[11px] font-medium text-gray-900 font-mono tabular-nums" x-text="formatDuration(drillDownData?.stats?.p50_duration_ms)"></dd></div>
                                        <div class="flex justify-between"><dt class="text-[11px] text-gray-500">p95</dt><dd class="text-[11px] font-medium text-gray-900 font-mono tabular-nums" x-text="formatDuration(drillDownData?.stats?.p95_duration_ms)"></dd></div>
                                        <div class="flex justify-between"><dt class="text-[11px] text-gray-500">p99</dt><dd class="text-[11px] font-medium text-gray-900 font-mono tabular-nums" x-text="formatDuration(drillDownData?.stats?.p99_duration_ms)"></dd></div>
                                        <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Memory avg</dt><dd class="text-[11px] font-medium text-gray-900 font-mono tabular-nums" x-text="drillDownData?.stats?.avg_memory_mb != null ? drillDownData.stats.avg_memory_mb + ' MB' : '-'"></dd></div>
                                        <div class="flex justify-between"><dt class="text-[11px] text-gray-500">Success rate</dt><dd class="text-[11px] font-medium font-mono tabular-nums" :class="(drillDownData?.stats?.success_rate ?? 0) >= 95 ? 'text-emerald-600' : (drillDownData?.stats?.success_rate ?? 0) >= 80 ? 'text-amber-600' : 'text-red-600'" x-text="formatNumber(drillDownData?.stats?.success_rate, 1) + '%'"></dd></div>
                                    </dl>
                                </div>
                            </div>

                            {{-- Failure Patterns --}}
                            <div x-show="(drillDownData?.failure_patterns || []).length > 0" class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-900">Failure Patterns</h3></div>
                                <div class="divide-y divide-gray-50">
                                    <template x-for="fp in (drillDownData?.failure_patterns || [])" :key="fp.exception_class">
                                        <div class="flex items-center justify-between px-5 py-2.5 hover:bg-red-50/50 cursor-pointer transition-colors" @click="filterJobsByException(fp.exception_class)">
                                            <span class="text-sm font-mono text-red-700 truncate" x-text="shortClass(fp.exception_class)"></span>
                                            <span class="text-[11px] text-gray-500 flex-shrink-0 ml-3" x-text="fp.count + 'x'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    {{-- ==================== CONFIRM DELETE DIALOG ==================== --}}
    <div x-show="confirmDelete" x-cloak class="relative z-50">
        <div class="fixed inset-0 bg-gray-900/30 backdrop-blur-sm" @click="confirmDelete = null"></div>
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div x-show="confirmDelete" x-transition class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
                <h3 class="text-base font-semibold text-gray-900">Delete Job</h3>
                <p class="mt-2 text-sm text-gray-500">Are you sure you want to delete this job? This action cannot be undone.</p>
                <div class="mt-5 flex gap-3 justify-end">
                    <button @click="confirmDelete = null" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                    <button @click="deleteJob(confirmDelete)" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Delete</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== JAVASCRIPT ==================== --}}
    <script>
        function dashboard() {
            return {
                activeTab: 'overview',
                previousTab: 'overview',

                // Job detail view (replaces slide-over)
                jobView: null,
                jobDetail: null,

                // Data stores
                overview: { stats: {}, queues: [], alerts: {}, recentJobs: [], charts: {} },
                jobs: { data: [], meta: { total: 0, limit: 50, offset: 0 } },
                analytics: {},
                health: {},
                infrastructure: {},

                // Jobs tab state
                filters: { search: '', statuses: [], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' },
                availableQueues: [],
                selectedJobs: [],
                sorting: { field: 'queued_at', direction: 'desc' },
                pagination: { offset: 0, limit: 50, total: 0 },

                // Auto-refresh
                refreshInterval: null,
                isLive: true,
                loading: { overview: true, jobs: false, analytics: false, health: false, infrastructure: false, detail: false },
                error: null,
                retryCount: 0,
                maxRetries: 3,

                // Confirm dialog
                confirmDelete: null,

                // Drill-down panel
                drillDown: null,
                drillDownData: null,
                drillDownLoading: false,
                drillDownChart: null,

                // Chart instances
                throughputChart: null,
                distributionChart: null,

                // URLs
                dashboardUrl: '{{ route("queue-monitor.dashboard") }}',
                jobViewUrlBase: '{{ route("queue-monitor.job.view", ["uuid" => "__PH__"]) }}'.replace('__PH__', ''),
                drillDownUrlBase: {
                    queue: '{{ route("queue-monitor.queue.view", ["queue" => "__PH__"]) }}'.replace('__PH__', ''),
                    server: '{{ route("queue-monitor.server.view", ["server" => "__PH__"]) }}'.replace('__PH__', ''),
                    job_class: '{{ route("queue-monitor.class.view", ["jobClass" => "__PH__"]) }}'.replace('__PH__', ''),
                },

                init() {
                    this.fetchOverview();
                    this.fetchHealth();
                    this.startAutoRefresh();

                    // Deep-link: auto-open job, drill-down, or tab from URL
                    const initialJobUuid = @json($jobUuid ?? null);
                    const initialDrillDownType = @json($drillDownType ?? null);
                    const initialDrillDownValue = @json($drillDownValue ?? null);
                    if (initialJobUuid) {
                        this.openJobView(initialJobUuid, false);
                    } else if (initialDrillDownType && initialDrillDownValue) {
                        this.openDrillDown(initialDrillDownType, initialDrillDownValue, false);
                    } else {
                        // Restore tab from hash fragment (#jobs, #analytics, etc.)
                        const hash = window.location.hash.replace('#', '');
                        if (['jobs', 'analytics', 'health', 'infrastructure'].includes(hash)) {
                            this.navigateTo(hash);
                        }
                    }

                    // Handle browser back/forward
                    window.addEventListener('popstate', (e) => {
                        // Clean up current views
                        try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (ex) {}
                        this.drillDownChart = null;

                        if (e.state?.jobUuid) {
                            this.drillDown = null; this.drillDownData = null;
                            this.jobView = e.state.jobUuid;
                            this.jobDetail = null;
                            this.fetchJobDetail(e.state.jobUuid);
                        } else if (e.state?.drillDown) {
                            this.jobView = null; this.jobDetail = null;
                            this.openDrillDown(e.state.drillDown.type, e.state.drillDown.value, false);
                        } else {
                            this.jobView = null; this.jobDetail = null;
                            this.drillDown = null; this.drillDownData = null;
                            if (e.state?.tab) this.activeTab = e.state.tab;
                        }
                    });

                    window.addEventListener('resize', () => {
                        this.throughputChart?.resize();
                        this.distributionChart?.resize();
                        this.drillDownChart?.resize();
                    });

                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            if (this.drillDown) this.closeDrillDown();
                            else if (this.jobView) this.closeJobView();
                        }
                    });
                },

                startAutoRefresh() {
                    if (this.refreshInterval) clearInterval(this.refreshInterval);
                    this.refreshInterval = setInterval(() => {
                        if (!this.isLive || this.jobView) return;
                        if (this.activeTab === 'overview') this.fetchOverview();
                        else if (this.activeTab === 'jobs' && !this.hasActiveFilters()) this.fetchJobs();
                    }, {{ config('queue-monitor.ui.refresh_interval', 3000) }});
                },

                toggleLive() { this.isLive = !this.isLive; },

                // ========== NAVIGATION ==========

                navigateTo(tab) {
                    // Close sub-views without pushing their own state
                    if (this.jobView) { this.jobView = null; this.jobDetail = null; }
                    if (this.drillDown) {
                        try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (e) {}
                        this.drillDownChart = null;
                        this.drillDown = null; this.drillDownData = null;
                    }
                    this.activeTab = tab;
                    this.pushTabState(tab);
                    if (tab === 'overview' && !this.overview.stats.total_jobs && this.overview.stats.total_jobs !== 0) this.fetchOverview();
                    else if (tab === 'jobs' && this.jobs.data.length === 0) this.fetchJobs();
                    else if (tab === 'analytics' && Object.keys(this.analytics).length === 0) this.fetchAnalytics();
                    else if (tab === 'health' && Object.keys(this.health).length === 0) this.fetchHealth();
                    else if (tab === 'infrastructure' && Object.keys(this.infrastructure).length === 0) this.fetchInfrastructure();
                    this.$nextTick(() => {
                        if (tab === 'overview') this.initThroughputChart();
                        if (tab === 'analytics') this.initDistributionChart();
                    });
                },

                pushTabState(tab) {
                    const hash = tab === 'overview' ? '' : '#' + tab;
                    history.pushState({ tab }, '', this.dashboardUrl + hash);
                },

                openJobView(uuid, pushHistory = true) {
                    this.previousTab = this.activeTab;
                    this.drillDown = null; this.drillDownData = null;
                    try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (e) {}
                    this.drillDownChart = null;
                    this.jobView = uuid;
                    this.jobDetail = null;
                    this.fetchJobDetail(uuid);
                    if (pushHistory) {
                        const url = this.jobViewUrlBase + uuid;
                        history.pushState({ jobUuid: uuid }, '', url);
                    }
                },

                closeJobView() {
                    this.jobView = null;
                    this.jobDetail = null;
                    this.pushTabState(this.previousTab);
                },

                // ========== DATA FETCHING ==========

                async fetchWithRetry(url, options = {}, retries = 0) {
                    try {
                        const response = await fetch(url, options);
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        this.error = null;
                        this.retryCount = 0;
                        return await response.json();
                    } catch (err) {
                        if (retries < this.maxRetries) {
                            const delay = Math.pow(2, retries) * 1000;
                            this.error = `Failed to load data. Retrying in ${delay/1000}s...`;
                            await new Promise(r => setTimeout(r, delay));
                            return this.fetchWithRetry(url, options, retries + 1);
                        }
                        this.error = 'Failed to load data. Please refresh the page.';
                        throw err;
                    }
                },

                async fetchOverview() {
                    try {
                        const data = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.metrics") }}');
                        this.overview.stats = data.stats || {};
                        this.overview.queues = data.queues || [];
                        this.overview.alerts = data.alerts || {};
                        this.overview.recentJobs = data.recent_jobs || [];
                        this.overview.charts = data.charts || {};
                        const queueNames = (data.queues || []).map(q => q.queue);
                        if (queueNames.length > this.availableQueues.length) this.availableQueues = queueNames;
                        this.loading.overview = false;
                        this.$nextTick(() => { this.initThroughputChart(); this.updateThroughputChart(data.charts?.throughput); });
                    } catch (e) { this.loading.overview = false; console.error('fetchOverview error:', e); }
                },

                async fetchJobs() {
                    if (this.jobs.data.length === 0) this.loading.jobs = true;
                    try {
                        const params = new URLSearchParams();
                        if (this.filters.search) params.append('search', this.filters.search);
                        this.filters.statuses.forEach(s => params.append('statuses[]', s));
                        if (this.filters.queue) params.append('queues[]', this.filters.queue);
                        if (this.filters.dateFrom) params.append('queued_after', this.filters.dateFrom);
                        if (this.filters.dateTo) params.append('queued_before', this.filters.dateTo);
                        if (this.filters.jobClass) params.append('job_classes[]', this.filters.jobClass);
                        if (this.filters.server) params.append('server_names[]', this.filters.server);
                        if (this.filters.minAttempts) params.append('min_attempts', this.filters.minAttempts);
                        if (this.filters.minDuration) params.append('min_duration_ms', this.filters.minDuration);
                        params.append('limit', this.pagination.limit);
                        params.append('offset', this.pagination.offset);
                        params.append('sort_by', this.sorting.field);
                        params.append('sort_direction', this.sorting.direction);
                        const data = await this.fetchWithRetry(`{{ route("queue-monitor.dashboard.jobs") }}?${params.toString()}`);
                        this.jobs.data = data.data || [];
                        this.jobs.meta = data.meta || {};
                        this.pagination.total = data.meta?.total || 0;
                        const metaQueues = data.meta?.available_queues || [];
                        if (metaQueues.length > 0) this.availableQueues = metaQueues;
                        else if (this.availableQueues.length === 0 && this.jobs.data.length > 0) this.availableQueues = [...new Set(this.jobs.data.map(j => j.queue).filter(Boolean))].sort();
                    } catch (e) { console.error('fetchJobs error:', e); } finally { this.loading.jobs = false; }
                },

                async fetchJobDetail(uuid) {
                    this.loading.detail = true;
                    try {
                        const url = '{{ route("queue-monitor.dashboard.job.detail", ["uuid" => "_UUID_"]) }}'.replace('_UUID_', uuid);
                        const data = await this.fetchWithRetry(url);
                        this.jobDetail = data;
                    } catch (e) { console.error('fetchJobDetail error:', e); } finally { this.loading.detail = false; }
                },

                async fetchAnalytics() {
                    if (Object.keys(this.analytics).length === 0) this.loading.analytics = true;
                    try {
                        const data = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.analytics") }}');
                        this.analytics = data;
                        this.$nextTick(() => { this.initDistributionChart(); this.updateDistributionChart(data.job_classes); });
                    } catch (e) { console.error('fetchAnalytics error:', e); } finally { this.loading.analytics = false; }
                },

                async fetchHealth() {
                    if (Object.keys(this.health).length === 0) this.loading.health = true;
                    try { this.health = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.health") }}'); } catch (e) { console.error('fetchHealth error:', e); } finally { this.loading.health = false; }
                },

                async fetchInfrastructure() {
                    if (Object.keys(this.infrastructure).length === 0) this.loading.infrastructure = true;
                    try { this.infrastructure = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.infrastructure") }}'); } catch (e) { console.error('fetchInfrastructure error:', e); } finally { this.loading.infrastructure = false; }
                },

                // ========== ACTIONS ==========

                async replayJob(uuid) {
                    if (!uuid) return;
                    try {
                        const apiBase = '{{ config("queue-monitor.api.prefix", "api/queue-monitor") }}';
                        await fetch(`/${apiBase}/jobs/${uuid}/replay`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
                        if (this.activeTab === 'overview') this.fetchOverview();
                        if (this.activeTab === 'jobs') this.fetchJobs();
                    } catch (e) { this.error = 'Failed to replay job'; console.error('replayJob error:', e); }
                },

                confirmDeleteJob(uuid) { this.confirmDelete = uuid; },

                async deleteJob(uuid) {
                    if (!uuid) return;
                    this.confirmDelete = null;
                    try {
                        const apiBase = '{{ config("queue-monitor.api.prefix", "api/queue-monitor") }}';
                        await fetch(`/${apiBase}/jobs/${uuid}`, { method: 'DELETE', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
                        if (this.jobView) this.closeJobView();
                        if (this.activeTab === 'overview') this.fetchOverview();
                        if (this.activeTab === 'jobs') this.fetchJobs();
                    } catch (e) { this.error = 'Failed to delete job'; console.error('deleteJob error:', e); }
                },

                async batchReplay() {
                    if (this.selectedJobs.length === 0) return;
                    try {
                        const apiBase = '{{ config("queue-monitor.api.prefix", "api/queue-monitor") }}';
                        await fetch(`/${apiBase}/batch/replay`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ uuids: this.selectedJobs }) });
                        this.selectedJobs = []; this.fetchJobs();
                    } catch (e) { this.error = 'Failed to replay jobs'; console.error('batchReplay error:', e); }
                },

                async batchDelete() {
                    if (this.selectedJobs.length === 0) return;
                    if (!confirm(`Delete ${this.selectedJobs.length} job(s)? This cannot be undone.`)) return;
                    try {
                        const apiBase = '{{ config("queue-monitor.api.prefix", "api/queue-monitor") }}';
                        await fetch(`/${apiBase}/batch/delete`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ uuids: this.selectedJobs }) });
                        this.selectedJobs = []; this.fetchJobs();
                    } catch (e) { this.error = 'Failed to delete jobs'; console.error('batchDelete error:', e); }
                },

                // ========== DRILL-DOWN ==========

                async openDrillDown(type, value, pushHistory = true) {
                    this.jobView = null; this.jobDetail = null;
                    this.drillDown = { type, value };
                    this.drillDownLoading = true;
                    this.drillDownData = null;
                    if (pushHistory) {
                        const base = this.drillDownUrlBase[type] || this.dashboardUrl;
                        history.pushState({ drillDown: { type, value } }, '', base + encodeURIComponent(value));
                    }
                    try {
                        const url = `{{ route('queue-monitor.dashboard.drill-down') }}?type=${type}&value=${encodeURIComponent(value)}`;
                        const data = await this.fetchWithRetry(url);
                        this.drillDownData = data;
                    } catch (e) { console.error('drill-down error:', e); } finally {
                        this.drillDownLoading = false;
                        this.$nextTick(() => { this.$nextTick(() => this.initDrillDownChart()); });
                    }
                },

                closeDrillDown() {
                    try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (e) {}
                    this.drillDownChart = null;
                    this.drillDown = null;
                    this.drillDownData = null;
                    this.pushTabState(this.activeTab);
                },

                drillDownToJobs(type, value) {
                    this.closeDrillDown();
                    this.activeTab = 'jobs';
                    this.clearFilters();
                    if (type === 'queue') this.filters.queue = value;
                    if (type === 'server') this.filters.server = value;
                    if (type === 'job_class') this.filters.jobClass = value;
                    this.$nextTick(() => this.fetchJobs());
                },

                filterJobsByException(exceptionClass) {
                    this.activeTab = 'jobs';
                    this.clearFilters();
                    this.filters.search = this.shortClass(exceptionClass);
                    this.filters.statuses = ['failed', 'timeout'];
                    this.$nextTick(() => this.fetchJobs());
                },

                initDrillDownChart() {
                    try {
                        const el = document.getElementById('drilldown-throughput-chart');
                        if (!el || el.offsetWidth === 0) return;
                        if (this.drillDownChart) this.drillDownChart.dispose();
                        this.drillDownChart = echarts.init(el);
                        const data = this.drillDownData?.throughput;
                        if (!data || !Array.isArray(data) || data.length === 0) return;
                        const labels = data.map(d => { const parts = (d.minute || '').split(' '); return parts.length > 1 ? parts[1] : d.minute; });
                        this.drillDownChart.setOption({
                            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => { let html = `<div style="font-size:12px;font-weight:600;margin-bottom:4px">${params[0]?.axisValue || ''}</div>`; params.forEach(p => { html += `<div style="font-size:11px">${p.marker} ${p.seriesName}: ${p.value}</div>`; }); return html; } },
                            grid: { top: 10, right: 10, bottom: 24, left: 36 },
                            xAxis: { type: 'category', data: labels, axisLine: { lineStyle: { color: '#e5e7eb' } }, axisLabel: { fontSize: 10, color: '#9ca3af', interval: Math.max(0, Math.floor(labels.length / 8) - 1) } },
                            yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#f3f4f6' } }, axisLabel: { fontSize: 10, color: '#9ca3af' } },
                            series: [
                                { name: 'Completed', type: 'bar', stack: 'throughput', data: data.map(d => d.completed || 0), itemStyle: { color: '#4f6df5' }, barMaxWidth: 16 },
                                { name: 'Failed', type: 'bar', stack: 'throughput', data: data.map(d => d.failed || 0), itemStyle: { color: '#ef4444', borderRadius: [3, 3, 0, 0] }, barMaxWidth: 16 },
                            ],
                        });
                    } catch (e) { console.warn('Drill-down chart error:', e.message); }
                },

                drillDownStatusClass(status) {
                    const classes = { 'completed': 'bg-emerald-500', 'failed': 'bg-red-500', 'timeout': 'bg-red-500', 'processing': 'bg-blue-500', 'queued': 'bg-amber-500' };
                    return classes[status] || 'bg-gray-400';
                },

                // ========== FILTERS & SORTING ==========

                hasActiveFilters() {
                    return this.filters.search || this.filters.statuses.length > 0 || this.filters.queue || this.filters.dateFrom || this.filters.dateTo || this.filters.jobClass || this.filters.server || this.filters.minAttempts || this.filters.minDuration;
                },

                clearFilters() {
                    this.filters = { search: '', statuses: [], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' };
                    this.resetPaginationAndFetch();
                },

                resetPaginationAndFetch() { this.pagination.offset = 0; this.selectedJobs = []; this.fetchJobs(); },

                toggleSort(field) {
                    if (this.sorting.field === field) this.sorting.direction = this.sorting.direction === 'asc' ? 'desc' : 'asc';
                    else { this.sorting.field = field; this.sorting.direction = 'desc'; }
                    this.fetchJobs();
                },

                sortIndicator(field) { if (this.sorting.field !== field) return ''; return this.sorting.direction === 'asc' ? '\u2191' : '\u2193'; },
                toggleAllJobs(event) { this.selectedJobs = event.target.checked ? this.jobs.data.map(j => j.uuid) : []; },
                prevPage() { this.pagination.offset = Math.max(0, this.pagination.offset - this.pagination.limit); this.selectedJobs = []; this.fetchJobs(); },
                nextPage() { this.pagination.offset += this.pagination.limit; this.selectedJobs = []; this.fetchJobs(); },

                // ========== CHARTS ==========

                initThroughputChart() {
                    const el = document.getElementById('throughput-chart');
                    if (!el || el.offsetWidth === 0 || this.throughputChart) return;
                    this.throughputChart = echarts.init(el);
                },

                updateThroughputChart(data) {
                    if (!this.throughputChart || !data || !Array.isArray(data) || data.length === 0) return;
                    try {
                        const labels = data.map(d => { const parts = (d.minute || '').split(' '); return parts.length > 1 ? parts[1] : d.minute; });
                        this.throughputChart.setOption({
                            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: (params) => { let html = `<div style="font-size:12px;font-weight:600;margin-bottom:4px">${params[0]?.axisValue || ''}</div>`; params.forEach(p => { html += `<div style="font-size:11px">${p.marker} ${p.seriesName}: ${p.value}</div>`; }); return html; } },
                            grid: { top: 10, right: 10, bottom: 24, left: 36 },
                            xAxis: { type: 'category', data: labels, axisLine: { lineStyle: { color: '#e5e7eb' } }, axisLabel: { fontSize: 10, color: '#9ca3af', interval: Math.max(0, Math.floor(labels.length / 8) - 1) } },
                            yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#f3f4f6' } }, axisLabel: { fontSize: 10, color: '#9ca3af' } },
                            series: [
                                { name: 'Completed', type: 'bar', stack: 'throughput', data: data.map(d => d.completed || 0), itemStyle: { color: '#4f6df5' }, barMaxWidth: 20 },
                                { name: 'Failed', type: 'bar', stack: 'throughput', data: data.map(d => d.failed || 0), itemStyle: { color: '#ef4444', borderRadius: [3, 3, 0, 0] }, barMaxWidth: 20 },
                            ],
                        });
                    } catch (e) { console.warn('Throughput chart error:', e.message); }
                },

                initDistributionChart() {
                    const el = document.getElementById('distribution-chart');
                    if (!el) return;
                    if (!this.distributionChart) {
                        this.distributionChart = echarts.init(el);
                        this.distributionChart.on('click', (params) => {
                            if (params.name) {
                                const match = (this.analytics.job_classes || []).find(jc => (jc.job_class || '').split('\\').pop() === params.name);
                                if (match) this.openDrillDown('job_class', match.job_class);
                            }
                        });
                    }
                },

                updateDistributionChart(data) {
                    if (!this.distributionChart || !data || !Array.isArray(data) || data.length === 0) return;
                    try {
                        const colors = ['#4f6df5', '#7c5bf5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316'];
                        const items = data.map(item => ({ value: item.total_jobs || 0, name: (item.job_class || '').split('\\').pop() })).filter(item => item.value > 0).sort((a, b) => b.value - a.value).slice(0, 10);
                        if (items.length === 0) return;
                        this.distributionChart.setOption({
                            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
                            legend: { type: 'scroll', orient: 'vertical', right: 0, top: 'middle', textStyle: { fontSize: 11, color: '#6b7280' }, itemWidth: 10, itemHeight: 10 },
                            color: colors,
                            series: [{ type: 'pie', radius: ['45%', '75%'], center: ['35%', '50%'], avoidLabelOverlap: false, itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 }, label: { show: false }, emphasis: { label: { show: true, fontSize: 13, fontWeight: 'bold' } }, data: items }],
                        });
                    } catch (e) { console.warn('Distribution chart error:', e.message); }
                },

                // ========== FORMATTING HELPERS ==========

                statusClass(status) {
                    const classes = { 'completed': 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20', 'failed': 'bg-red-50 text-red-700 ring-1 ring-red-500/20', 'timeout': 'bg-red-50 text-red-700 ring-1 ring-red-500/20', 'processing': 'bg-blue-50 text-blue-700 ring-1 ring-blue-500/20', 'queued': 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20' };
                    return classes[status] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-500/20';
                },

                formatNumber(num, decimals = 0) {
                    if (num === undefined || num === null) return '0';
                    return new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num);
                },

                formatDuration(ms) {
                    if (!ms && ms !== 0) return '-';
                    if (ms < 1000) return Math.round(ms) + 'ms';
                    if (ms < 60000) return (ms / 1000).toFixed(1) + 's';
                    return (ms / 60000).toFixed(1) + 'm';
                },

                formatTime(iso) {
                    if (!iso) return '-';
                    try {
                        const d = new Date(iso);
                        if (isNaN(d.getTime())) return iso;
                        const now = new Date();
                        const diffMs = now - d;
                        if (diffMs < 60000) return 'just now';
                        if (diffMs < 3600000) return Math.floor(diffMs / 60000) + 'm ago';
                        if (diffMs < 86400000) return Math.floor(diffMs / 3600000) + 'h ago';
                        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                    } catch (e) { return iso; }
                },

                formatDateTime(iso) {
                    if (!iso) return '-';
                    try {
                        const d = new Date(iso);
                        if (isNaN(d.getTime())) return iso;
                        return d.toLocaleString('en-GB', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                    } catch (e) { return iso; }
                },

                healthBadge() {
                    // Use health endpoint status if loaded, otherwise derive from success_rate
                    if (this.health.status) return this.health.status === 'healthy' ? 'healthy' : (this.health.status === 'degraded' ? 'degraded' : 'unhealthy');
                    const sr = this.overview.stats.success_rate;
                    if (sr == null || sr >= 95) return 'healthy';
                    if (sr >= 75) return 'degraded';
                    return 'unhealthy';
                },

                formatCpu(cpuTimeMs, durationMs) {
                    if (cpuTimeMs == null || durationMs == null || durationMs === 0) return '-';
                    const pct = (cpuTimeMs / durationMs) * 100;
                    return pct < 1 ? '<1%' : Math.round(pct) + '%';
                },

                formatMemory(peakMb, limitMb) {
                    if (peakMb == null) return '-';
                    const peak = parseFloat(peakMb).toFixed(0);
                    if (limitMb != null && limitMb > 0) {
                        const pct = Math.round((peakMb / limitMb) * 100);
                        return peak + ' / ' + parseFloat(limitMb).toFixed(0) + ' MB (' + pct + '%)';
                    }
                    return peak + ' MB';
                },

                formatMemoryShort(peakMb, limitMb) {
                    if (peakMb == null) return '-';
                    const peak = parseFloat(peakMb).toFixed(0);
                    if (limitMb != null && limitMb > 0) {
                        return Math.round((peakMb / limitMb) * 100) + '%';
                    }
                    return peak + ' MB';
                },

                shortClass(fqcn) { if (!fqcn) return '-'; return fqcn.split('\\').pop(); },

                copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.error = null;
                    }).catch(() => {});
                },

                formatStackTrace(trace) {
                    if (!trace) return '';
                    const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return trace.split('\n').map(line => {
                        line = esc(line.trim());
                        if (!line) return '';
                        // Match: #N /path/to/File.php(123): Class->method()
                        const m = line.match(/^(#\d+)\s+(.+?)\((\d+)\):\s+(.+)$/);
                        if (m) {
                            const [, num, file, lineNo, call] = m;
                            // Dim vendor paths, highlight app paths
                            const isVendor = file.includes('/vendor/');
                            const shortFile = file.replace(/^.*\/(app|src|vendor)\//i, '$1/');
                            const fileClass = isVendor ? 'text-gray-600' : 'text-amber-400';
                            const callHtml = call.replace(/^(.+?)(->|::)(.+)$/, '<span class="text-gray-500">$1</span><span class="text-gray-600">$2</span><span class="' + (isVendor ? 'text-gray-500' : 'text-emerald-400') + '">$3</span>');
                            return `<div class="flex gap-2 py-0.5 ${isVendor ? 'opacity-50 hover:opacity-100' : ''} transition-opacity">`
                                + `<span class="text-gray-600 flex-shrink-0 w-7 text-right select-none">${num}</span>`
                                + `<span class="flex-1 min-w-0"><span class="${fileClass}">${shortFile}</span><span class="text-gray-600">:</span><span class="text-cyan-400">${lineNo}</span> ${callHtml}</span>`
                                + `</div>`;
                        }
                        // Fallback: plain line
                        return `<div class="py-0.5 text-gray-500">${line}</div>`;
                    }).join('');
                },

                formatExceptionMarkdown(exception) {
                    if (!exception) return '';
                    let md = `## ${exception.short_class}\n\n`;
                    md += `**${exception.message}**\n\n`;
                    md += '```\n' + (exception.trace || '') + '\n```\n';
                    return md;
                },

                parsePayload(payload) {
                    if (!payload) return { properties: [], meta: {}, raw: null };
                    const meta = {};
                    if (payload.displayName) meta.displayName = payload.displayName;
                    if (payload.maxTries) meta.maxTries = payload.maxTries;
                    if (payload.timeout !== undefined && payload.timeout !== null) meta.timeout = payload.timeout;
                    if (payload.maxExceptions) meta.maxExceptions = payload.maxExceptions;
                    if (payload.backoff) meta.backoff = payload.backoff;
                    const properties = [];
                    const command = payload.data?.command;
                    if (typeof command === 'string') {
                        const skipProps = ['queue', 'connection', 'delay', 'middleware', 'chained', 'afterCommit', 'job', 'chainConnection', 'chainQueue', 'chainCatchCallbacks'];
                        const propRegex = /s:\d+:"([^"]+)";(?:s:\d+:"([^"]*)"|(i:(-?\d+))|(d:(-?[\d.]+(?:E[+-]?\d+)?))|(b:([01]))|(N;))/g;
                        let match;
                        while ((match = propRegex.exec(command)) !== null) {
                            const name = match[1];
                            if (skipProps.includes(name)) continue;
                            let value;
                            if (match[2] !== undefined) value = match[2];
                            else if (match[4] !== undefined) value = match[4];
                            else if (match[6] !== undefined) value = match[6];
                            else if (match[8] !== undefined) value = match[8] === '1' ? 'true' : 'false';
                            else if (match[9] !== undefined) value = 'null';
                            else value = '(complex)';
                            properties.push({ name, value });
                        }
                    }
                    return { properties, meta, raw: payload };
                },

                hasUnhandledQueues() { return this.getUnhandledQueues().length > 0; },

                getUnhandledQueues() {
                    const workload = this.infrastructure.workers?.workload || [];
                    const workerQueues = new Set(workload.filter(w => w.processes > 0).map(w => w.queue));
                    const workerTypes = this.infrastructure.worker_types?.per_queue || [];
                    const activeQueues = new Set(workerTypes.map(wt => wt.queue));
                    const queueHealth = this.overview.queues || [];
                    const unhandled = [];
                    for (const q of queueHealth) {
                        const hasWorker = workerQueues.has(q.queue) || activeQueues.has(q.queue);
                        const hasPendingOnly = (q.processing || 0) === 0 && q.total_last_hour > 0;
                        if (!hasWorker && hasPendingOnly) unhandled.push({ queue: q.queue, pending: q.total_last_hour });
                    }
                    return unhandled;
                },
            }
        }
    </script>
</body>
</html>
