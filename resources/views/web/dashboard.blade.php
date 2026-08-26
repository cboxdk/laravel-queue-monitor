<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue Monitor</title>
    {!! app(\Cbox\LaravelQueueMonitor\Support\DashboardAssets::class)->styles() !!}
    <style>
        [x-cloak] { display: none !important; }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            overflow-x: hidden;
        }

        :root {
            --qm-bg: #f5f6f8;
            --qm-surface: #ffffff;
            --qm-surface-subtle: #f0f3f6;
            --qm-border: #d9e0e8;
            --qm-border-strong: #c5ced9;
            --qm-text: #151a23;
            --qm-muted: #697386;
            --qm-soft: #8b95a5;
            --qm-blue: #2563eb;
            --qm-blue-soft: #e9efff;
            --qm-teal: #0f766e;
            --qm-teal-soft: #e4f5f2;
            --qm-amber: #b7791f;
            --qm-amber-soft: #fff4dc;
            --qm-red: #b42318;
            --qm-red-soft: #ffebe7;
            --qm-green: #16875b;
            --qm-green-soft: #e5f6ed;
            --qm-shadow: 0 10px 30px rgba(21, 26, 35, 0.08);
        }

        body.qm-modern {
            margin: 0;
            min-height: 100vh;
            background: var(--qm-bg);
            color: var(--qm-text);
            letter-spacing: 0;
            overflow-x: hidden;
        }

        body.qm-menu-lock {
            overflow: hidden;
        }

        .qm-app {
            display: grid;
            grid-template-columns: 248px minmax(0, 1fr);
            min-height: 100vh;
        }

        .qm-sidebar {
            background: #101722;
            color: #f8fafc;
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 50;
            transition: transform 0.22s ease;
        }

        .qm-mobile-overlay {
            display: none;
        }

        .qm-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 8px;
            min-height: 40px;
            color: inherit;
            text-decoration: none;
        }

        .qm-brand-mark {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: #f8fafc;
            color: #101722;
            font-weight: 800;
        }

        .qm-brand strong,
        .qm-brand span {
            display: block;
        }

        .qm-brand strong {
            font-size: 15px;
            line-height: 1.2;
        }

        .qm-brand span {
            color: #9aa6b6;
            font-size: 12px;
            margin-top: 2px;
        }

        .qm-nav-section {
            display: grid;
            gap: 6px;
        }

        .qm-nav-title {
            color: #738094;
            font-size: 11px;
            font-weight: 700;
            padding: 0 10px 6px;
            text-transform: uppercase;
        }

        .qm-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            border: 0;
            border-radius: 8px;
            padding: 0 10px;
            color: #c9d3df;
            background: transparent;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }

        .qm-nav-item:hover {
            background: #182231;
            color: #ffffff;
        }

        .qm-nav-item.active {
            background: #202b3a;
            color: #ffffff;
            box-shadow: inset 3px 0 0 var(--qm-blue);
        }

        .qm-icon,
        .qm-nav-item svg,
        .qm-search svg,
        .qm-icon-button svg,
        .qm-chip svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex: 0 0 auto;
        }

        .qm-sidebar-footer {
            margin-top: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.04);
        }

        .qm-sidebar-footer strong {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .qm-sidebar-footer span {
            display: block;
            color: #9aa6b6;
            font-size: 12px;
            line-height: 1.45;
        }

        .qm-main {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .qm-topbar {
            min-height: 72px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--qm-border);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .qm-menu-button {
            display: none;
            width: 40px;
            height: 40px;
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: var(--qm-surface);
            color: var(--qm-text);
            place-items: center;
            flex: 0 0 auto;
            cursor: pointer;
        }

        .qm-menu-button svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .qm-page-title {
            min-width: 190px;
            min-width: 0;
        }

        .qm-page-title h1 {
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
            font-weight: 800;
            color: var(--qm-text);
        }

        .qm-page-title p {
            margin: 4px 0 0;
            color: var(--qm-muted);
            font-size: 13px;
        }

        .qm-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            min-width: 0;
        }

        .qm-search {
            width: min(360px, 28vw);
            min-width: 220px;
            height: 40px;
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: var(--qm-surface-subtle);
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            color: var(--qm-muted);
        }

        .qm-search input {
            border: 0;
            outline: 0;
            background: transparent;
            min-width: 0;
            flex: 1;
            color: var(--qm-text);
            font-size: 14px;
        }

        .qm-select,
        .qm-chip,
        .qm-icon-button {
            height: 40px;
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: var(--qm-surface);
            color: var(--qm-text);
        }

        .qm-select {
            padding: 0 12px;
            min-width: 132px;
        }

        .qm-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            font-size: 13px;
            white-space: nowrap;
        }

        .qm-chip.live {
            color: var(--qm-green);
            background: var(--qm-green-soft);
            border-color: #bee7d0;
            font-weight: 700;
        }

        .qm-icon-button {
            width: 40px;
            display: grid;
            place-items: center;
            cursor: pointer;
        }

        .qm-content {
            padding: 24px 28px 34px;
            display: grid;
            gap: 18px;
            width: 100%;
            min-width: 0;
        }

        .qm-content > *,
        .qm-content [x-show],
        .qm-content .grid,
        .qm-content .flex {
            min-width: 0;
        }

        .qm-metrics {
            display: grid;
            grid-template-columns: repeat(5, minmax(160px, 1fr));
            gap: 12px;
        }

        .qm-metric,
        .qm-panel,
        .qm-health-panel {
            background: var(--qm-surface);
            border: 1px solid var(--qm-border);
            border-radius: 8px;
        }

        .qm-metric {
            padding: 14px;
            min-height: 112px;
            box-shadow: 0 1px 0 rgba(21, 26, 35, 0.03);
        }

        .qm-metric-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--qm-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            min-width: 0;
        }

        .qm-metric-head span:first-child,
        .qm-metric-foot span:last-child,
        .qm-panel-title,
        .qm-panel-title strong,
        .qm-panel-title span,
        .qm-health-item strong,
        .qm-health-item span {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .qm-metric-value {
            margin-top: 14px;
            font-size: 28px;
            font-weight: 780;
            line-height: 1;
        }

        .qm-metric-foot {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            color: var(--qm-muted);
            font-size: 13px;
        }

        .qm-delta,
        .qm-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            font-weight: 800;
        }

        .qm-delta {
            padding: 2px 7px;
            font-size: 12px;
        }

        .qm-status {
            min-height: 24px;
            padding: 0 8px;
            font-size: 12px;
        }

        .qm-good { color: var(--qm-green); background: var(--qm-green-soft); }
        .qm-warn { color: var(--qm-amber); background: var(--qm-amber-soft); }
        .qm-bad { color: var(--qm-red); background: var(--qm-red-soft); }
        .qm-info { color: var(--qm-blue); background: var(--qm-blue-soft); }

        .qm-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(360px, 0.72fr);
            gap: 18px;
            align-items: start;
        }

        .qm-stack {
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .qm-panel,
        .qm-health-panel {
            box-shadow: var(--qm-shadow);
            min-width: 0;
        }

        .qm-panel-head {
            min-height: 58px;
            border-bottom: 1px solid var(--qm-border);
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
        }

        .qm-panel-title strong {
            display: block;
            font-size: 15px;
            line-height: 1.25;
            color: var(--qm-text);
        }

        .qm-panel-title span {
            display: block;
            margin-top: 2px;
            color: var(--qm-muted);
            font-size: 12px;
        }

        .qm-segmented {
            display: inline-grid;
            grid-auto-flow: column;
            gap: 2px;
            padding: 3px;
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: var(--qm-surface-subtle);
        }

        .qm-segment {
            border: 0;
            border-radius: 6px;
            min-width: 58px;
            height: 30px;
            padding: 0 10px;
            color: var(--qm-muted);
            background: transparent;
            font-size: 12px;
            font-weight: 700;
        }

        .qm-segment.active {
            color: var(--qm-text);
            background: var(--qm-surface);
            box-shadow: 0 1px 2px rgba(21, 26, 35, 0.1);
        }

        .qm-chart-area {
            padding: 18px 16px 16px;
        }

        .qm-chart {
            height: 232px;
        }

        .qm-queue-bars {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 16px;
        }

        .qm-queue-bar {
            display: grid;
            gap: 8px;
        }

        .qm-queue-bar span {
            color: var(--qm-muted);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .qm-bar-track {
            height: 8px;
            border-radius: 999px;
            background: var(--qm-surface-subtle);
            overflow: hidden;
        }

        .qm-bar-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--qm-teal);
        }

        .qm-bar-fill.warn { background: var(--qm-amber); }
        .qm-bar-fill.bad { background: var(--qm-red); }

        .qm-table-wrap {
            overflow-x: auto;
            max-width: 100%;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
        }

        .qm-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .qm-table th,
        .qm-table td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--qm-border);
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        .qm-table th {
            color: var(--qm-muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            background: #fbfcfd;
        }

        .qm-table tr.selected td {
            background: #f7faff;
        }

        .qm-table td strong,
        .qm-table td span {
            display: block;
        }

        .qm-table td strong {
            color: var(--qm-text);
            font-size: 13px;
        }

        .qm-table td span {
            margin-top: 3px;
            color: var(--qm-muted);
            font-size: 12px;
        }

        .qm-inspector {
            position: sticky;
            top: 90px;
        }

        .qm-detail {
            padding: 16px;
            display: grid;
            gap: 16px;
        }

        .qm-detail-hero {
            border-bottom: 1px solid var(--qm-border);
            padding-bottom: 16px;
        }

        .qm-detail-hero strong {
            display: block;
            font-size: 16px;
            line-height: 1.3;
            word-break: break-word;
        }

        .qm-detail-hero span {
            display: block;
            margin-top: 5px;
            color: var(--qm-muted);
            font-size: 13px;
            word-break: break-word;
        }

        .qm-kv {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .qm-kv div {
            border-bottom: 1px solid var(--qm-border);
            padding-bottom: 10px;
        }

        .qm-kv span {
            display: block;
            color: var(--qm-muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .qm-kv strong {
            display: block;
            margin-top: 5px;
            font-size: 13px;
        }

        .qm-trace {
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: #fbfcfd;
            padding: 12px;
            color: #475569;
            font-family: "JetBrains Mono", Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .qm-action-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .qm-action-button {
            height: 36px;
            border: 1px solid var(--qm-border);
            border-radius: 8px;
            background: var(--qm-surface);
            color: var(--qm-text);
            font-size: 13px;
            font-weight: 700;
        }

        .qm-action-button.primary {
            color: #ffffff;
            background: var(--qm-blue);
            border-color: var(--qm-blue);
        }

        .qm-health-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .qm-health-panel {
            min-height: 210px;
            box-shadow: 0 8px 26px rgba(21, 26, 35, 0.06);
        }

        .qm-health-list {
            padding: 12px 16px 16px;
            display: grid;
            gap: 10px;
        }

        .qm-health-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            min-height: 34px;
            min-width: 0;
        }

        .qm-health-item > div {
            min-width: 0;
        }

        .qm-health-item strong {
            display: block;
            font-size: 13px;
            font-weight: 700;
        }

        .qm-health-item span {
            display: block;
            color: var(--qm-muted);
            font-size: 12px;
        }

        /*
         * Legacy tab normalization.
         * The package dashboard is intentionally a single Blade/Alpine surface. These
         * rules bring the older Jobs, Analytics, Health, Infrastructure, Autoscale,
         * job-detail, and drill-down sections onto the same visual system as Overview.
         */
        .qm-content .bg-white {
            background: var(--qm-surface) !important;
        }

        .qm-content .rounded-xl {
            border-radius: 8px !important;
        }

        .qm-content .rounded-lg {
            border-radius: 8px !important;
        }

        .qm-content .shadow-sm {
            box-shadow: var(--qm-shadow) !important;
        }

        .qm-content .border-gray-200\/80,
        .qm-content .border-gray-200,
        .qm-content .border-gray-100 {
            border-color: var(--qm-border) !important;
        }

        .qm-content .bg-gray-50\/60,
        .qm-content .bg-gray-50\/80,
        .qm-content .bg-gray-50\/40,
        .qm-content .bg-gray-50 {
            background: #fbfcfd !important;
        }

        .qm-content .text-gray-900 {
            color: var(--qm-text) !important;
        }

        .qm-content .text-gray-500,
        .qm-content .text-gray-400 {
            color: var(--qm-muted) !important;
        }

        .qm-content h3,
        .qm-content h4 {
            letter-spacing: 0;
        }

        .qm-content table:not(.qm-table) {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        .qm-content table:not(.qm-table) th {
            color: var(--qm-muted) !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            background: #fbfcfd !important;
        }

        .qm-content table:not(.qm-table) th,
        .qm-content table:not(.qm-table) td {
            border-bottom: 1px solid var(--qm-border) !important;
        }

        .qm-content .overflow-x-auto {
            max-width: 100%;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
        }

        .qm-content pre,
        .qm-content code {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .qm-content table:not(.qm-table) tbody tr:hover td {
            background: #f7faff;
        }

        .qm-content input[type="text"],
        .qm-content input[type="datetime-local"],
        .qm-content select {
            border-color: var(--qm-border) !important;
            background: #fbfcfd;
            border-radius: 8px !important;
            color: var(--qm-text);
        }

        .qm-content input[type="checkbox"] {
            border-color: var(--qm-border-strong);
        }

        .qm-content button {
            border-radius: 8px;
        }

        .qm-content .bg-brand-faint,
        .qm-content .bg-brand-faint\/40 {
            background: #f7faff !important;
        }

        .qm-content .text-brand {
            color: var(--qm-blue) !important;
        }

        .qm-content .border-brand,
        .qm-content .border-brand\/20 {
            border-color: rgba(37, 99, 235, 0.28) !important;
        }

        .qm-content .bg-brand,
        .qm-content .bg-brand\/50 {
            background-color: var(--qm-blue) !important;
        }

        .qm-content .bg-brand\/10 {
            background-color: var(--qm-blue-soft) !important;
        }

        @media (max-width: 1180px) {
            .qm-app {
                grid-template-columns: 86px minmax(0, 1fr);
            }

            .qm-brand div:last-child,
            .qm-nav-title,
            .qm-nav-item span,
            .qm-sidebar-footer {
                display: none;
            }

            .qm-nav-item {
                justify-content: center;
                padding: 0;
            }

            .qm-metrics {
                grid-template-columns: repeat(3, minmax(160px, 1fr));
            }

            .qm-dashboard-grid {
                grid-template-columns: 1fr;
            }

            .qm-inspector {
                position: static;
            }

            .qm-topbar {
                min-height: auto;
                flex-wrap: wrap;
                align-items: flex-start;
                padding: 14px 20px;
            }

            .qm-page-title {
                flex: 1 1 220px;
                padding-top: 2px;
            }

            .qm-toolbar {
                flex: 1 1 100%;
                width: 100%;
                margin-left: 0;
                justify-content: flex-end;
            }

            .qm-search {
                flex: 1 1 280px;
                width: auto;
                min-width: 220px;
            }

            .qm-select {
                flex: 0 0 132px;
            }
        }

        @media (max-width: 900px) {
            .qm-app {
                grid-template-columns: 1fr;
            }

            .qm-menu-button {
                display: grid;
            }

            .qm-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(304px, calc(100vw - 48px));
                max-width: 304px;
                height: 100dvh;
                transform: translateX(-105%);
                box-shadow: 18px 0 40px rgba(16, 23, 34, 0.22);
            }

            .qm-sidebar.open {
                transform: translateX(0);
            }

            .qm-sidebar .qm-brand div:last-child,
            .qm-sidebar .qm-nav-title,
            .qm-sidebar .qm-nav-item span,
            .qm-sidebar .qm-sidebar-footer {
                display: block;
            }

            .qm-sidebar .qm-nav-item {
                justify-content: flex-start;
                padding: 0 10px;
            }

            .qm-mobile-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(16, 23, 34, 0.42);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
                z-index: 45;
            }

            .qm-mobile-overlay.open {
                opacity: 1;
                pointer-events: auto;
            }

            .qm-topbar {
                position: sticky;
                top: 0;
            }

            .qm-content {
                padding: 18px 20px 28px;
            }

            .qm-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .qm-health-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .qm-topbar {
                flex-wrap: wrap;
                padding: 16px;
            }

            .qm-page-title {
                flex: 1 1 calc(100% - 56px);
            }

            .qm-page-title h1 {
                font-size: 18px;
            }

            .qm-page-title p {
                font-size: 12px;
                line-height: 1.35;
            }

            .qm-toolbar {
                width: 100%;
                flex-wrap: wrap;
                margin-left: 0;
                gap: 8px;
            }

            .qm-search {
                flex: 1 1 100%;
                width: 100%;
            }

            .qm-select,
            .qm-chip {
                flex: 1 1 calc(50% - 4px);
                min-width: 0;
            }

            .qm-icon-button {
                flex: 0 0 40px;
            }

            .qm-content {
                padding: 16px;
                gap: 14px;
            }

            .qm-metrics,
            .qm-health-grid {
                grid-template-columns: 1fr;
            }

            .qm-metric {
                min-height: auto;
                padding: 13px;
            }

            .qm-metric-value {
                margin-top: 10px;
                font-size: 24px;
            }

            .qm-panel-head {
                align-items: flex-start;
                flex-direction: column;
                padding: 12px 14px;
            }

            .qm-panel-head > .qm-chip,
            .qm-panel-head > .qm-status,
            .qm-panel-head > .qm-segmented {
                align-self: stretch;
                justify-content: center;
            }

            .qm-segmented {
                width: 100%;
            }

            .qm-segment {
                min-width: 0;
            }

            .qm-chart-area {
                padding: 14px;
            }

            .qm-chart {
                height: 190px;
            }

            .qm-queue-bars {
                grid-template-columns: repeat(2, 1fr);
            }

            .qm-action-row,
            .qm-kv {
                grid-template-columns: 1fr;
            }

            .qm-table {
                min-width: 760px;
            }

            .qm-content table:not(.qm-table) {
                min-width: 640px;
            }

            .qm-content .grid-cols-2,
            .qm-content .grid-cols-3 {
                grid-template-columns: minmax(0, 1fr) !important;
            }

            .qm-content .justify-between {
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .qm-sidebar {
                width: min(292px, calc(100vw - 32px));
            }

            .qm-topbar {
                padding: 12px;
            }

            .qm-content {
                padding: 12px;
            }

            .qm-icon-button {
                flex: 0 0 40px;
            }

            .qm-queue-bars {
                grid-template-columns: 1fr;
            }

            .qm-health-list,
            .qm-detail {
                padding: 12px;
            }

            .qm-action-button {
                min-height: 38px;
            }
        }

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
<body class="qm-modern" :class="{ 'qm-menu-lock': mobileMenuOpen }" x-data="dashboard()">
    <div class="qm-app">
        <div class="qm-mobile-overlay" :class="{ 'open': mobileMenuOpen }" @click="mobileMenuOpen = false" aria-hidden="true"></div>

        <aside class="qm-sidebar" :class="{ 'open': mobileMenuOpen }">
            <a :href="dashboardUrl" class="qm-brand">
                <div class="qm-brand-mark">Q</div>
                <div>
                    <strong>Queue Monitor</strong>
                    <span>Production</span>
                </div>
            </a>

            <nav class="qm-nav-section" aria-label="Primary navigation">
                <div class="qm-nav-title">Monitor</div>
                <template x-for="tab in sidebarTabs" :key="tab.id">
                    <button type="button" class="qm-nav-item" :class="{ 'active': activeTab === tab.id && !jobView && !drillDown }" x-show="!tab.horizon || horizonAvailable" @click="navigateTo(tab.id)">
                        <svg viewBox="0 0 24 24" x-html="tab.svg"></svg>
                        <span x-text="tab.label"></span>
                    </button>
                </template>
            </nav>

            <nav class="qm-nav-section" aria-label="Administration navigation">
                <div class="qm-nav-title">Admin</div>
                <button type="button" class="qm-nav-item" @click="navigateTo('health')">
                    <svg viewBox="0 0 24 24"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.37a1.7 1.7 0 0 0-1 .28 1.7 1.7 0 0 0-.8 1.45V21a2 2 0 0 1-4 0v-.09a1.7 1.7 0 0 0-.8-1.45 1.7 1.7 0 0 0-1-.28 1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.63 15a1.7 1.7 0 0 0-.28-1 1.7 1.7 0 0 0-1.45-.8H3a2 2 0 0 1 0-4h.09a1.7 1.7 0 0 0 1.45-.8 1.7 1.7 0 0 0 .28-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.63a1.7 1.7 0 0 0 1-.28 1.7 1.7 0 0 0 .8-1.45V3a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 .8 1.45 1.7 1.7 0 0 0 1 .28 1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.37 9c0 .36.1.7.28 1 .3.5.84.8 1.45.8H21a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.45.8 1.7 1.7 0 0 0-.06.4z"></path></svg>
                    <span>Settings</span>
                </button>
            </nav>

            <div class="qm-sidebar-footer">
                <strong>Refresh contract</strong>
                <span>Live views update one active surface at a time. Paused mode freezes all panels consistently.</span>
            </div>
        </aside>

        <div class="qm-main">
            <header class="qm-topbar">
                <button type="button" class="qm-menu-button" aria-label="Open navigation" @click="mobileMenuOpen = true">
                    <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
                </button>

                <div class="qm-page-title">
                    <h1 x-text="pageTitle()">Overview</h1>
                    <p x-text="pageSubtitle()">Live queue operations across workers</p>
                </div>

                <div class="qm-toolbar">
                    <label class="qm-search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3"></path><circle cx="11" cy="11" r="7"></circle></svg>
                        <input type="text" x-model="filters.search" @keydown.enter.prevent="navigateTo('jobs'); resetPaginationAndFetch()" placeholder="Search job, queue, UUID">
                    </label>

                    <select class="qm-select" aria-label="Environment">
                        <option>Production</option>
                    </select>

                    <button type="button" class="qm-chip" :class="{ live: isLive }" :aria-pressed="isLive" @click="toggleLive()">
                        <svg viewBox="0 0 24 24"><path d="M5 12h4l3-8 3 16 3-8h1"></path></svg>
                        <span x-text="isLive ? 'Live' : 'Paused'">Live</span>
                    </button>

                    <span class="qm-chip" x-text="lastRefreshLabel()">Last refresh pending</span>

                    <button type="button" class="qm-icon-button" :title="isLive ? 'Pause refresh' : 'Resume refresh'" :aria-label="isLive ? 'Pause refresh' : 'Resume refresh'" :aria-pressed="isLive" @click="toggleLive()">
                        <svg x-show="isLive" viewBox="0 0 24 24"><path d="M8 5v14"></path><path d="M16 5v14"></path></svg>
                        <svg x-show="!isLive" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"></path></svg>
                    </button>
                    <button type="button" class="qm-icon-button" title="Refresh now" aria-label="Refresh now" @click="refreshCurrentView(true)">
                        <svg viewBox="0 0 24 24" :class="{ 'animate-spin': anyLoading() || anyRefreshing() }"><path d="M21 12a9 9 0 0 1-15.5 6.2"></path><path d="M3 12A9 9 0 0 1 18.5 5.8"></path><path d="M18 2v4h4"></path><path d="M6 22v-4H2"></path></svg>
                    </button>
                </div>
            </header>

            <main class="qm-content">

            {{-- ==================== TAB CONTENT (hidden when viewing job detail or drill-down) ==================== --}}
            <div x-show="!jobView && !drillDown">

            {{-- ==================== OVERVIEW TAB ==================== --}}
            <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="qm-metrics">
                    <article class="qm-metric">
                        <div class="qm-metric-head"><span>Failed jobs</span><span class="qm-status qm-bad" x-text="formatNumber(overview.stats.failed || 0)">0</span></div>
                        <div class="qm-metric-value" x-text="failureRateLabel()">0%</div>
                        <div class="qm-metric-foot"><span class="qm-delta" :class="(overview.stats.failed || 0) > 0 ? 'qm-bad' : 'qm-good'" x-text="(overview.stats.failed || 0) > 0 ? 'Watch' : 'Clean'">Clean</span><span>last 24h failure share</span></div>
                    </article>
                    <article class="qm-metric">
                        <div class="qm-metric-head"><span>Backlog</span><span class="qm-status" :class="queueBacklogSeverity()" x-text="queueBacklogLabel()">OK</span></div>
                        <div class="qm-metric-value" x-text="formatNumber(overview.stats.queue_backlog || 0)">0</div>
                        <div class="qm-metric-foot"><span class="qm-delta" :class="queueBacklogSeverity()" x-text="queueBacklogTrendLabel()">Stable</span><span>queued across tracked queues</span></div>
                    </article>
                    <article class="qm-metric">
                        <div class="qm-metric-head"><span>Queue latency</span><span class="qm-status qm-info">Avg</span></div>
                        <div class="qm-metric-value" x-text="formatDuration(overview.stats.avg_duration_ms)">0ms</div>
                        <div class="qm-metric-foot"><span class="qm-delta" :class="(overview.stats.avg_duration_ms || 0) > 8000 ? 'qm-warn' : 'qm-good'" x-text="(overview.stats.avg_duration_ms || 0) > 8000 ? 'Above target' : 'On target'">On target</span><span>average runtime</span></div>
                    </article>
                    <article class="qm-metric">
                        <div class="qm-metric-head"><span>Workers</span><span class="qm-status qm-good" x-text="workerStatusLabel()">Healthy</span></div>
                        <div class="qm-metric-value" x-text="workerCountLabel()">-</div>
                        <div class="qm-metric-foot"><span class="qm-delta qm-good" x-text="autoscaleStatusLabel()">Ready</span><span>autoscale signal</span></div>
                    </article>
                    <article class="qm-metric">
                        <div class="qm-metric-head"><span>Throughput</span><span class="qm-status qm-good">Stable</span></div>
                        <div class="qm-metric-value" x-text="formatCompact(throughputTotal())">0</div>
                        <div class="qm-metric-foot"><span class="qm-delta qm-good" x-text="formatNumber(overview.stats.total || 0)">0</span><span>jobs in current window</span></div>
                    </article>
                </div>

                <div class="qm-dashboard-grid">
                    <div class="qm-stack">
                        <section class="qm-panel">
                            <div class="qm-panel-head">
                                <div class="qm-panel-title">
                                    <strong>Queue latency and backlog</strong>
                                    <span>Processing trend over the last 60 minutes</span>
                                </div>
                                <div class="qm-segmented" aria-label="Time range">
                                    <button type="button" class="qm-segment">15m</button>
                                    <button type="button" class="qm-segment active">1h</button>
                                    <button type="button" class="qm-segment">6h</button>
                                    <button type="button" class="qm-segment">24h</button>
                                </div>
                            </div>
                            <div class="qm-chart-area">
                                <div id="throughput-chart" class="qm-chart"></div>
                                <div class="qm-queue-bars">
                                    <template x-for="q in overview.queues.slice(0, 6)" :key="q.queue">
                                        <button type="button" class="qm-queue-bar" @click="openDrillDown('queue', q.queue)">
                                            <span x-text="q.queue"></span>
                                            <div class="qm-bar-track"><i class="qm-bar-fill" :class="queueFillClass(q)" :style="'width: ' + queueFillWidth(q) + '%'"></i></div>
                                        </button>
                                    </template>
                                    <template x-if="!loading.overview && overview.queues.length === 0">
                                        <div class="qm-queue-bar"><span>default</span><div class="qm-bar-track"><i class="qm-bar-fill" style="width: 12%"></i></div></div>
                                    </template>
                                </div>
                            </div>
                        </section>

                        <section class="qm-panel">
                            <div class="qm-panel-head">
                                <div class="qm-panel-title">
                                    <strong>Jobs requiring attention</strong>
                                    <span>Sorted by failure state, attempts, and newest activity</span>
                                </div>
                                <button type="button" class="qm-chip" @click="navigateTo('jobs')" x-text="formatNumber(attentionJobs().length) + ' visible'">0 visible</button>
                            </div>
                            <div class="qm-table-wrap">
                                <table class="qm-table">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Job</th>
                                            <th>Queue</th>
                                            <th>Attempts</th>
                                            <th>Latency</th>
                                            <th>Runtime</th>
                                            <th>Worker</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="job in attentionJobs().slice(0, 6)" :key="job.uuid">
                                            <tr class="cursor-pointer" :class="{ 'selected': selectedOverviewJob()?.uuid === job.uuid }" @click="openJobView(job.uuid)">
                                                <td><span class="qm-status" :class="statusTone(job.status?.value)" x-text="job.status?.label || job.status?.value || 'Unknown'"></span></td>
                                                <td><strong x-text="job.job_class"></strong><span x-text="job.uuid ? 'uuid ' + job.uuid.slice(0, 8) : ''"></span></td>
                                                <td x-text="job.queue || '-'"></td>
                                                <td x-text="attemptLabel(job)"></td>
                                                <td x-text="formatDuration(job.pickup_time_ms || job.duration_ms)"></td>
                                                <td x-text="formatDuration(job.duration_ms)"></td>
                                                <td x-text="job.server || job.server_name || '-'"></td>
                                                <td x-text="job.queued_at || '-'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div x-show="!loading.overview && attentionJobs().length === 0" class="px-6 py-10 text-center text-sm text-gray-500">No jobs require attention.</div>
                        </section>
                    </div>

                    <aside class="qm-panel qm-inspector">
                        <div class="qm-panel-head">
                            <div class="qm-panel-title">
                                <strong>Selected job</strong>
                                <span>Current execution context</span>
                            </div>
                            <span class="qm-status" :class="statusTone(selectedOverviewJob()?.status?.value)" x-text="selectedOverviewJob()?.status?.label || 'None'">None</span>
                        </div>
                        <div class="qm-detail" x-show="selectedOverviewJob()">
                            <div class="qm-detail-hero">
                                <strong x-text="selectedOverviewJob()?.job_class"></strong>
                                <span x-text="selectedOverviewJob()?.full_job_class || selectedOverviewJob()?.uuid"></span>
                            </div>

                            <div class="qm-kv">
                                <div><span>Queue</span><strong x-text="selectedOverviewJob()?.queue || '-'"></strong></div>
                                <div><span>Attempts</span><strong x-text="attemptLabel(selectedOverviewJob())"></strong></div>
                                <div><span>Server</span><strong x-text="selectedOverviewJob()?.server || selectedOverviewJob()?.server_name || '-'"></strong></div>
                                <div><span>Updated</span><strong x-text="selectedOverviewJob()?.queued_at || '-'"></strong></div>
                                <div><span>Runtime</span><strong x-text="formatDuration(selectedOverviewJob()?.duration_ms)"></strong></div>
                                <div><span>Impact</span><strong x-text="selectedOverviewJob()?.is_failed ? 'Retry required' : 'Monitor'"></strong></div>
                            </div>

                            <div class="qm-panel-title">
                                <strong>Recommended action</strong>
                                <span x-text="selectedOverviewJob()?.is_failed ? 'Inspect the exception and retry after correction.' : 'Continue monitoring current throughput.'"></span>
                            </div>

                            <div class="qm-trace" x-text="selectedOverviewJob()?.error || selectedOverviewJob()?.uuid || 'No exception captured for this job.'"></div>

                            <div class="qm-action-row">
                                <button type="button" class="qm-action-button primary" @click="replayJob(selectedOverviewJob()?.uuid)">Retry</button>
                                <button type="button" class="qm-action-button" @click="openJobView(selectedOverviewJob()?.uuid)">Inspect</button>
                                <button type="button" class="qm-action-button" @click="confirmDeleteJob(selectedOverviewJob()?.uuid)">Ignore</button>
                            </div>
                        </div>
                        <div class="qm-detail" x-show="!selectedOverviewJob()">
                            <div class="qm-trace">No jobs are available yet.</div>
                        </div>
                    </aside>
                </div>

                <section class="qm-health-grid">
                    <article class="qm-health-panel">
                        <div class="qm-panel-head">
                            <div class="qm-panel-title">
                                <strong>Autoscale</strong>
                                <span>Worker allocation by queue group</span>
                            </div>
                            <span class="qm-status qm-good" x-text="autoscaleStatusLabel()">Ready</span>
                        </div>
                        <div class="qm-health-list">
                            <template x-for="q in overview.queues.slice(0, 3)" :key="'auto-' + q.queue">
                                <div class="qm-health-item">
                                    <div><strong x-text="q.queue"></strong><span x-text="(q.processing || 0) + ' processing, ' + (q.total_last_hour || 0) + '/hr'"></span></div>
                                    <span class="qm-status" :class="queueStatusTone(q)" x-text="queueShortStatus(q)">OK</span>
                                </div>
                            </template>
                        </div>
                    </article>

                    <article class="qm-health-panel">
                        <div class="qm-panel-head">
                            <div class="qm-panel-title">
                                <strong>Infrastructure health</strong>
                                <span>Signals that affect queue throughput</span>
                            </div>
                            <span class="qm-status" :class="healthTone()" x-text="healthBadgeLabel()">Unknown</span>
                        </div>
                        <div class="qm-health-list">
                            <template x-for="check in healthChecksList().slice(0, 3)" :key="check.name">
                                <div class="qm-health-item">
                                    <div><strong x-text="check.name || check.label"></strong><span x-text="check.message || check.description || 'No details'"></span></div>
                                    <span class="qm-status" :class="check.healthy ? 'qm-good' : 'qm-bad'" x-text="check.healthy ? 'OK' : 'Fail'">OK</span>
                                </div>
                            </template>
                            <div class="qm-health-item" x-show="healthChecksList().length === 0">
                                <div><strong>Health checks</strong><span>No health check data loaded yet</span></div>
                                <span class="qm-status qm-info">Info</span>
                            </div>
                        </div>
                    </article>

                    <article class="qm-health-panel">
                        <div class="qm-panel-head">
                            <div class="qm-panel-title">
                                <strong>Alerts</strong>
                                <span>Recent operational events</span>
                            </div>
                            <span class="qm-status qm-info" x-text="formatNumber((overview.alerts?.active || []).length) + ' open'">0 open</span>
                        </div>
                        <div class="qm-health-list">
                            <template x-for="alert in (overview.alerts?.active || []).slice(0, 3)" :key="alert.message">
                                <div class="qm-health-item">
                                    <div><strong x-text="alert.message"></strong><span x-text="alert.type || 'queue monitor'"></span></div>
                                    <span class="qm-status" :class="alert.severity === 'critical' ? 'qm-bad' : (alert.severity === 'warning' ? 'qm-warn' : 'qm-info')" x-text="alert.severity || 'info'"></span>
                                </div>
                            </template>
                            <div class="qm-health-item" x-show="!(overview.alerts?.active || []).length">
                                <div><strong>No active alerts</strong><span>Current queue signals are within thresholds</span></div>
                                <span class="qm-status qm-good">OK</span>
                            </div>
                        </div>
                    </article>
                </section>
            </div>

            {{-- ==================== JOBS TAB ==================== --}}
            <div x-show="activeTab === 'jobs'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Filter Bar --}}
                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 mb-4">
                    <div class="flex flex-wrap gap-3 items-center">
                        <div class="relative flex-1 min-w-[200px]">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                            <input type="text" x-model="filters.search" @input.debounce.300ms="resetPaginationAndFetch()" placeholder="Search jobs..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition">
                        </div>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <span class="text-gray-700">Status</span>
                                <span x-show="filters.statuses.length" class="inline-flex items-center justify-center h-5 min-w-[20px] px-1 text-[10px] font-bold bg-brand text-white rounded-full" x-text="filters.statuses.length"></span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                                <template x-for="s in ['queued','processing','completed','failed','timeout','debounced']" :key="s">
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
                        <button @click="fetchJobs()" class="inline-flex items-center gap-1.5 px-3 py-2 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                            <svg class="h-3.5 w-3.5" :class="(loading.jobs || refreshing.jobs) && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                            Refresh
                        </button>
                    </div>

                    {{-- Advanced filters --}}
                    <div x-show="filters.showAdvanced" x-transition class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Job Class</label>
                            <input type="text" x-model="filters.jobClass" @input.debounce.300ms="resetPaginationAndFetch()" placeholder="e.g. SendEmail" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Server</label>
                            <input type="text" x-model="filters.server" @input.debounce.300ms="resetPaginationAndFetch()" placeholder="hostname" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
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
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-right font-mono tabular-nums" :class="cpuColor(job.cpu_time_ms, job.duration_ms)" x-text="formatCpu(job.cpu_time_ms, job.duration_ms)"></td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-sm text-right font-mono tabular-nums" :class="memoryColor(job.memory_peak_mb, job.worker_memory_limit_mb)" x-text="formatMemoryShort(job.memory_peak_mb, job.worker_memory_limit_mb)"></td>
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
                        <svg class="h-3.5 w-3.5" :class="(loading.analytics || refreshing.analytics) && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
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
                        <svg class="h-3.5 w-3.5" :class="(loading.health || refreshing.health) && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
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
                                                <div class="flex items-center justify-end gap-2 mb-1">
                                                    <button @click="resolveAllStuckJobs('retry')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 transition" title="Retry all stuck jobs">
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                                                        Retry All
                                                    </button>
                                                    <button @click="resolveAllStuckJobs('delete')" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 transition" title="Delete all stuck jobs">
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                                        Clear All
                                                    </button>
                                                </div>
                                                <template x-for="sj in health.checks[name].details.stuck_jobs" :key="sj.uuid">
                                                    <div class="flex items-center gap-2 text-[11px] bg-red-50 border border-red-100 rounded-lg px-3 py-1.5">
                                                        <span class="font-mono font-medium text-red-800 cursor-pointer hover:underline" x-text="shortClass(sj.job_class)" @click="openJobView(sj.uuid)"></span>
                                                        <span class="text-red-600" x-text="'-> ' + sj.queue"></span>
                                                        <span class="text-red-400" x-text="'on ' + sj.server"></span>
                                                        <span class="text-red-400 ml-auto" x-text="'since ' + formatTime(sj.stuck_since)"></span>
                                                        <button @click.stop="resolveStuckJob(sj.uuid, 'retry')" class="p-1 text-amber-600 hover:text-amber-800 hover:bg-amber-100 rounded transition" title="Retry this job">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                                                        </button>
                                                        <button @click.stop="resolveStuckJob(sj.uuid, 'delete')" class="p-1 text-red-600 hover:text-red-800 hover:bg-red-100 rounded transition" title="Delete this job">
                                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                                        </button>
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
                    <h3 class="text-sm font-semibold text-gray-900">Horizon</h3>
                    <button @click="fetchInfrastructure()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        <svg class="h-3.5 w-3.5" :class="(loading.infrastructure || refreshing.infrastructure) && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
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

                        {{-- Cluster Status Banner (v3 only) --}}
                        <template x-if="infrastructure.cluster?.has_cluster">
                            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-indigo-100/60">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></div>
                                            <h4 class="text-sm font-semibold text-indigo-900">Cluster Orchestration</h4>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-indigo-100 text-indigo-700" x-text="'v' + (infrastructure.cluster?.autoscale_version ?? '3')"></span>
                                        </div>
                                        <span class="text-[10px] text-indigo-400" x-text="'Cluster: ' + (infrastructure.cluster?.topology?.cluster_id ?? 'unknown')"></span>
                                    </div>
                                </div>
                                <div class="p-5">
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Leader</div>
                                            <div class="text-sm font-semibold text-indigo-900 truncate" x-text="infrastructure.cluster?.topology?.leader_id ?? 'electing...'"></div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Hosts</div>
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-2xl font-bold tabular-nums" :class="(infrastructure.cluster?.scaling_signal?.current_hosts ?? 0) >= (infrastructure.cluster?.scaling_signal?.recommended_hosts ?? 1) ? 'text-emerald-600' : (infrastructure.cluster?.scaling_signal?.current_hosts ?? 0) >= (infrastructure.cluster?.scaling_signal?.recommended_hosts ?? 1) * 0.6 ? 'text-amber-700' : 'text-red-600'" x-text="infrastructure.cluster?.scaling_signal?.current_hosts ?? infrastructure.cluster?.topology?.host_count ?? 0"></span>
                                                <span class="text-sm text-gray-400">/ <span x-text="infrastructure.cluster?.scaling_signal?.recommended_hosts ?? '?'"></span> recommended</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Capacity</div>
                                            <div class="text-sm text-gray-700"><span class="font-semibold" x-text="infrastructure.cluster?.scaling_signal?.current_capacity ?? '-'"></span> workers <span class="text-gray-400">/ <span x-text="infrastructure.cluster?.scaling_signal?.required_workers ?? '-'"></span> required</span></div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Action</div>
                                            <div>
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="{
                                                    'bg-emerald-100 text-emerald-700': infrastructure.cluster?.scaling_signal?.action === 'scale_up',
                                                    'bg-blue-100 text-blue-700': infrastructure.cluster?.scaling_signal?.action === 'scale_down',
                                                    'bg-gray-100 text-gray-600': infrastructure.cluster?.scaling_signal?.action === 'hold' || !infrastructure.cluster?.scaling_signal?.action
                                                }" x-text="(infrastructure.cluster?.scaling_signal?.action ?? 'hold').replace('_', ' ').toUpperCase()"></span>
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-0.5 truncate" x-text="infrastructure.cluster?.scaling_signal?.reason ?? ''"></p>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-3 border-t border-indigo-100/60" x-show="(infrastructure.cluster?.topology?.active_managers ?? []).length > 0">
                                        <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2">Active Managers</div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="mgr in (infrastructure.cluster?.topology?.active_managers ?? [])" :key="mgr.manager_id">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] rounded-md border" :class="mgr.manager_id === infrastructure.cluster?.topology?.leader_id ? 'bg-indigo-100 border-indigo-300 text-indigo-800 font-semibold' : 'bg-white border-gray-200 text-gray-700'">
                                                    <span x-show="mgr.manager_id === infrastructure.cluster?.topology?.leader_id" class="text-[10px]" title="Leader">&#9733;</span>
                                                    <span x-text="mgr.host ?? mgr.manager_id"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Scaling Signal Sparkline (v3 only) --}}
                        <template x-if="infrastructure.cluster?.has_cluster && (infrastructure.cluster?.signal_history ?? []).length > 1">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100">
                                    <h4 class="text-sm font-semibold text-gray-900">Host Scaling Trend <span class="text-[11px] font-normal text-gray-400">(Last Hour)</span></h4>
                                </div>
                                <div class="p-5">
                                    <svg class="w-full h-24" viewBox="0 0 400 80" preserveAspectRatio="none" x-data="{
                                        points() {
                                            const history = infrastructure.cluster?.signal_history ?? [];
                                            if (history.length < 2) return { current: '', recommended: '' };
                                            const maxVal = Math.max(...history.map(h => Math.max(h.current_hosts ?? 0, h.recommended_hosts ?? 0)), 1);
                                            const step = 400 / (history.length - 1);
                                            let currentPath = '';
                                            let recommendedPath = '';
                                            history.forEach((h, i) => {
                                                const x = i * step;
                                                const yC = 75 - ((h.current_hosts ?? 0) / maxVal) * 70;
                                                const yR = 75 - ((h.recommended_hosts ?? 0) / maxVal) * 70;
                                                currentPath += (i === 0 ? 'M' : 'L') + x + ',' + yC;
                                                recommendedPath += (i === 0 ? 'M' : 'L') + x + ',' + yR;
                                            });
                                            return { current: currentPath, recommended: recommendedPath };
                                        }
                                    }">
                                        <path :d="points().recommended" fill="none" stroke="#c7d2fe" stroke-width="2" stroke-dasharray="6,4" />
                                        <path :d="points().current" fill="none" stroke="#6366f1" stroke-width="2.5" />
                                    </svg>
                                    <div class="flex items-center gap-4 mt-2">
                                        <div class="flex items-center gap-1.5"><span class="h-0.5 w-4 bg-indigo-500 rounded"></span><span class="text-[10px] text-gray-500">Current Hosts</span></div>
                                        <div class="flex items-center gap-1.5"><span class="h-0.5 w-4 bg-indigo-200 rounded" style="background: repeating-linear-gradient(90deg, #c7d2fe 0, #c7d2fe 4px, transparent 4px, transparent 8px)"></span><span class="text-[10px] text-gray-500">Recommended</span></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Cluster Event Timeline (v3 only) --}}
                        <template x-if="infrastructure.cluster?.has_cluster && ((infrastructure.cluster?.leader_history ?? []).length > 0 || (infrastructure.cluster?.manager_events ?? []).length > 0)">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Cluster Events</h4></div>
                                <div class="max-h-64 overflow-y-auto custom-scroll divide-y divide-gray-50">
                                    <template x-for="(evt, idx) in (infrastructure.cluster?.manager_events ?? [])" :key="'mgr-' + idx">
                                        <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                            <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full" :class="evt.event_type === 'manager_started' ? 'bg-emerald-500' : 'bg-red-400'"></span></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="evt.event_type === 'manager_started' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" x-text="evt.event_type === 'manager_started' ? 'STARTED' : 'STOPPED'"></span>
                                                    <span class="text-sm font-medium text-gray-900" x-text="evt.host ?? evt.manager_id"></span>
                                                    <span x-show="evt.reason" class="text-[11px] text-gray-500" x-text="evt.reason"></span>
                                                    <span x-show="evt.meta?.uptime_seconds" class="text-[10px] text-gray-400" x-text="'uptime: ' + Math.round(evt.meta?.uptime_seconds / 60) + 'm'"></span>
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0"><span class="text-[10px] text-gray-400" x-text="evt.time_human"></span></div>
                                        </div>
                                    </template>
                                    <template x-for="(evt, idx) in (infrastructure.cluster?.leader_history ?? [])" :key="'ldr-' + idx">
                                        <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                            <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full bg-indigo-500"></span></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-indigo-50 text-indigo-700">LEADER CHANGE</span>
                                                    <span class="text-[11px] text-gray-500" x-text="(evt.previous_leader_id ?? '?') + ' → ' + (evt.leader_id ?? '?')"></span>
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0"><span class="text-[10px] text-gray-400" x-text="evt.time_human"></span></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

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
                                                <template x-for="sup in (infrastructure.workers?.supervisors || [])" :key="sup.name">
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
                                        <div x-show="infrastructure.scaling?.breach_severity" class="mt-1.5 text-[10px] text-red-400" x-text="'avg ' + (infrastructure.scaling?.breach_severity?.avg_breach_seconds ?? 0) + 's over · max ' + (infrastructure.scaling?.breach_severity?.max_breach_percentage ?? 0) + '% over'"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Horizon Workload --}}
                        <div x-show="infrastructure.workers?.available && (infrastructure.workers?.workload || []).length > 0" class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Horizon Workload</h4></div>
                                <div x-show="(infrastructure.workers?.workload || []).every(w => w.length === 0 && w.wait === 0)" class="py-10 text-center">
                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 mb-2"><svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                                    <p class="text-sm text-emerald-600 font-medium">All queues clear</p>
                                    <p class="text-[11px] text-gray-400 mt-1">No pending jobs across <span x-text="(infrastructure.workers?.workload || []).length"></span> queues</p>
                                </div>
                                <div x-show="!(infrastructure.workers?.workload || []).every(w => w.length === 0 && w.wait === 0)" class="divide-y divide-gray-50">
                                    <template x-for="w in (infrastructure.workers?.workload || [])" :key="w.queue">
                                        <div class="px-5 py-3">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="text-sm font-medium text-gray-900" x-text="w.queue"></span>
                                                <div class="flex items-center gap-4 text-[11px] text-gray-500"><span x-text="w.length + ' pending'"></span><span x-text="w.wait + 's wait'"></span><span x-text="w.processes + ' workers'"></span></div>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-2"><div class="h-2 rounded-full transition-all duration-500" :class="w.length > 100 ? 'bg-red-500' : w.length > 50 ? 'bg-amber-500' : 'bg-brand'" :style="'width: ' + Math.min(100, (w.length / Math.max(1, ...(infrastructure.workers?.workload || []).map(x => x.length))) * 100) + '%'"></div></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- ==================== AUTOSCALE TAB ==================== --}}
            <div x-show="activeTab === 'autoscale'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Autoscale</h3>
                    <div class="flex items-center gap-2">
                        <button @click="fetchAutoscale()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                            <svg class="h-3.5 w-3.5" :class="(loading.autoscale || refreshing.autoscale) && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                            Refresh
                        </button>
                    </div>
                </div>
                <template x-if="loading.autoscale">
                    <div class="flex items-center justify-center py-16"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand"></div></div>
                </template>
                <template x-if="!loading.autoscale">
                    <div class="space-y-6">

                        {{-- Not Available State --}}
                        <template x-if="!autoscale.available">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-12 text-center">
                                <svg class="h-12 w-12 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>
                                <h3 class="text-sm font-semibold text-gray-900 mb-1">No Autoscale Data</h3>
                                <p class="text-sm text-gray-500">Install <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">cboxdk/laravel-queue-autoscale</code> to enable autoscaling and cluster orchestration monitoring.</p>
                            </div>
                        </template>

                        {{-- Open Failure Fuses.
                             State, not transition: the timeline below shows the
                             moment a fuse tripped, and a fuse that tripped an
                             hour ago has scrolled off it while still holding the
                             queue at zero workers. Without this the dashboard
                             says a queue has no workers and offers no reason. --}}
                        <template x-if="autoscale.available && (autoscale.scaling?.open_fuses ?? []).length > 0">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-amber-200/60">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <svg aria-hidden="true" class="h-4 w-4 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                            <h4 class="text-sm font-semibold text-amber-900">Failure Fuse Holding</h4>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-800" x-text="(autoscale.scaling?.open_fuses ?? []).length + ' ' + ((autoscale.scaling?.open_fuses ?? []).length === 1 ? 'queue' : 'queues')"></span>
                                        </div>
                                        <span class="text-[10px] text-amber-700 flex-shrink-0 hidden sm:block">Scaling up a queue whose jobs all fail just burns capacity faster</span>
                                    </div>
                                </div>
                                <div class="divide-y divide-amber-200/50">
                                    <template x-for="(fuse, idx) in (autoscale.scaling?.open_fuses ?? [])" :key="'fuse-' + idx">
                                        <div class="flex items-start gap-3 px-5 py-3">
                                            <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold flex-shrink-0" :class="fuse.state === 'probing' ? 'bg-amber-100 text-amber-700' : 'bg-amber-200 text-amber-900'" x-text="fuse.state.toUpperCase()"></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-sm font-semibold text-amber-900" x-text="fuse.queue"></span>
                                                    <span class="text-[11px] text-amber-700" x-text="fuse.connection"></span>
                                                    <span class="text-[11px] font-medium text-amber-800" x-text="'held at ' + fuse.held_at_workers + ' worker' + (fuse.held_at_workers === 1 ? '' : 's')"></span>
                                                </div>
                                                <p class="text-[11px] text-amber-700 mt-0.5" x-text="fuse.reason"></p>
                                            </div>
                                            <span class="text-[10px] text-amber-700 flex-shrink-0 whitespace-nowrap" x-text="fuse.since_human"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Cluster Topology Banner --}}
                        <template x-if="autoscale.available && autoscale.cluster?.has_cluster">
                            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-indigo-100/60">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></div>
                                            <h4 class="text-sm font-semibold text-indigo-900">Cluster Topology</h4>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-indigo-100 text-indigo-700" x-text="'v' + (autoscale.cluster?.autoscale_version ?? '3')"></span>
                                        </div>
                                        <span class="text-[10px] text-indigo-400" x-text="'Cluster: ' + (autoscale.cluster?.topology?.cluster_id ?? 'unknown')"></span>
                                    </div>
                                </div>
                                <div class="p-5">
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Leader</div>
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="text-sm font-semibold text-indigo-900 truncate" x-text="autoscale.cluster?.topology?.leader_id ?? 'electing...'"></span>
                                                <template x-if="autoscale.cluster?.leadership?.unstable">
                                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold bg-amber-100 text-amber-800 flex-shrink-0">UNSTABLE</span>
                                                </template>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Hosts</div>
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-2xl font-bold tabular-nums" :class="(autoscale.cluster?.scaling_signal?.current_hosts ?? 0) >= (autoscale.cluster?.scaling_signal?.recommended_hosts ?? 1) ? 'text-emerald-600' : (autoscale.cluster?.scaling_signal?.current_hosts ?? 0) >= (autoscale.cluster?.scaling_signal?.recommended_hosts ?? 1) * 0.6 ? 'text-amber-700' : 'text-red-600'" x-text="autoscale.cluster?.scaling_signal?.current_hosts ?? autoscale.cluster?.topology?.host_count ?? 0"></span>
                                                <span class="text-sm text-gray-400">/ <span x-text="autoscale.cluster?.scaling_signal?.recommended_hosts ?? '?'"></span> recommended</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Capacity</div>
                                            <div class="text-sm text-gray-700"><span class="font-semibold" x-text="autoscale.cluster?.scaling_signal?.current_capacity ?? autoscale.live?.total_workers ?? '-'"></span> workers <span class="text-gray-400">/ <span x-text="autoscale.cluster?.scaling_signal?.required_workers ?? autoscale.live?.total_worker_capacity ?? '-'"></span> <span x-text="autoscale.cluster?.scaling_signal ? 'required' : (autoscale.live ? 'capacity' : 'required')"></span></span></div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Action</div>
                                            <div>
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="{ 'bg-emerald-100 text-emerald-700': autoscale.cluster?.scaling_signal?.action === 'scale_up', 'bg-blue-100 text-blue-700': autoscale.cluster?.scaling_signal?.action === 'scale_down', 'bg-gray-100 text-gray-600': autoscale.cluster?.scaling_signal?.action === 'hold' || !autoscale.cluster?.scaling_signal?.action }" x-text="(autoscale.cluster?.scaling_signal?.action ?? 'hold').replace('_', ' ').toUpperCase()"></span>
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-0.5 truncate" x-text="autoscale.cluster?.scaling_signal?.reason ?? ''"></p>
                                        </div>
                                    </div>
                                    {{-- What an unstable lease actually costs, because the
                                         symptom is a fleet that scales but never settles, and
                                         that reads as a scaling bug rather than a lease one. --}}
                                    <template x-if="autoscale.cluster?.leadership?.unstable">
                                        <div class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
                                            <svg aria-hidden="true" class="h-4 w-4 text-amber-600 flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-semibold text-amber-900">
                                                    <span x-text="autoscale.cluster?.leadership?.changes_in_window ?? 0"></span> leadership changes in <span x-text="autoscale.cluster?.leadership?.window_seconds ?? 60"></span>s
                                                </p>
                                                <p class="text-[11px] text-amber-700 mt-0.5">Worker placement, anti-flapping damping and fair-share rotation each restart on every change, so none of them completes. Check the lease duration, network partitions between managers, and clock drift.</p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Scaling Stats Cards --}}
                        <template x-if="autoscale.available">
                            <div class="grid grid-cols-2 lg:grid-cols-7 gap-4 stagger-in">
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Decisions</div><div class="mt-auto text-2xl font-bold text-gray-900 tabular-nums" x-text="autoscale.scaling?.summary?.total_decisions ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Scale Ups</div><div class="mt-auto text-2xl font-bold text-emerald-600 tabular-nums" x-text="autoscale.scaling?.summary?.scale_ups ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Scale Downs</div><div class="mt-auto text-2xl font-bold text-blue-600 tabular-nums" x-text="autoscale.scaling?.summary?.scale_downs ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">SLA Breaches</div><div class="mt-auto text-2xl font-bold text-red-600 tabular-nums" x-text="autoscale.scaling?.summary?.sla_breaches ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">SLA Recoveries</div><div class="mt-auto text-2xl font-bold text-emerald-600 tabular-nums" x-text="autoscale.scaling?.summary?.sla_recoveries ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Predictions</div><div class="mt-auto text-2xl font-bold text-orange-600 tabular-nums" x-text="autoscale.scaling?.summary?.sla_breach_predictions ?? 0"></div></div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm p-4 flex flex-col"><div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Fuse Trips</div><div class="mt-auto text-2xl font-bold tabular-nums" :class="(autoscale.scaling?.summary?.fuse_trips ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900'" x-text="autoscale.scaling?.summary?.fuse_trips ?? 0"></div></div>
                            </div>
                        </template>

                        {{-- Live Hosts Table --}}
                        <template x-if="autoscale.live && (autoscale.live?.hosts ?? []).length > 0">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-semibold text-gray-900">Hosts</h4>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700">LIVE</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-[11px] text-gray-400">
                                            <span><span class="font-semibold text-gray-700" x-text="autoscale.live?.total_workers ?? 0"></span> / <span x-text="autoscale.live?.total_worker_capacity ?? 0"></span> workers</span>
                                            <span><span class="font-semibold" :class="(autoscale.live?.utilization_percent ?? 0) > 85 ? 'text-red-600' : (autoscale.live?.utilization_percent ?? 0) > 60 ? 'text-amber-600' : 'text-gray-700'" x-text="Math.round(autoscale.live?.utilization_percent ?? 0) + '%'"></span> utilization</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50/80 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                                <th class="px-4 py-2.5 text-left">Host</th>
                                                <th class="px-4 py-2.5 text-right">Workers</th>
                                                <th class="px-4 py-2.5 text-left">CPU</th>
                                                <th class="px-4 py-2.5 text-left">Memory</th>
                                                <th class="px-4 py-2.5 text-left">Limiter</th>
                                                <th class="px-4 py-2.5 text-left">Queues</th>
                                                <th class="px-4 py-2.5 text-right">Seen</th>
                                            </tr>
                                        </thead>
                                        <template x-for="host in (autoscale.live?.hosts ?? [])" :key="host.manager_id">
                                            <tbody class="divide-y divide-gray-50" x-data="{ expanded: false }">
                                                <tr @click="expanded = !expanded" class="hover:bg-gray-50/60 transition-colors cursor-pointer">
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="h-3.5 w-3.5 text-gray-400 transition-transform" :class="expanded && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                                            <span x-show="host.is_leader" class="text-[10px] text-indigo-500" title="Leader">&#9733;</span>
                                                            <span class="font-medium text-gray-900" x-text="(host.manager_id ?? host.host ?? '').replace(/\.localdomain$/, '')"></span>
                                                            <span x-show="host.is_leader" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold bg-indigo-50 text-indigo-600">LEADER</span>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 mt-0.5 ml-5" x-text="[host.host, host.package_version].filter(Boolean).join(' · ')"></div>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                                                <div class="h-1.5 rounded-full transition-all" :class="host.max_workers > 0 && (host.total_workers / host.max_workers) > 0.85 ? 'bg-red-500' : (host.max_workers > 0 && (host.total_workers / host.max_workers) > 0.6) ? 'bg-amber-500' : 'bg-emerald-500'" :style="'width: ' + (host.max_workers > 0 ? Math.min(host.total_workers / host.max_workers * 100, 100) : 0) + '%'"></div>
                                                            </div>
                                                            <span class="font-semibold tabular-nums text-gray-900" x-text="host.total_workers"></span>
                                                            <span class="text-gray-400">/</span>
                                                            <span class="tabular-nums text-gray-500" x-text="host.max_workers"></span>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 mt-0.5 text-right" x-text="host.available_worker_capacity + ' available'"></div>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-12 bg-gray-100 rounded-full h-1.5">
                                                                <div class="h-1.5 rounded-full transition-all" :class="(host.cpu_percent ?? 0) > 85 ? 'bg-red-500' : (host.cpu_percent ?? 0) > 60 ? 'bg-amber-500' : 'bg-emerald-500'" :style="'width: ' + Math.min(host.cpu_percent ?? 0, 100) + '%'"></div>
                                                            </div>
                                                            <span class="tabular-nums font-medium" :class="(host.cpu_percent ?? 0) > 85 ? 'text-red-600' : (host.cpu_percent ?? 0) > 60 ? 'text-amber-600' : 'text-gray-700'" x-text="Math.round(host.cpu_percent ?? 0) + '%'"></span>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 mt-0.5" x-show="host.cpu_cores" x-text="host.cpu_cores + ' cores'"></div>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-12 bg-gray-100 rounded-full h-1.5">
                                                                <div class="h-1.5 rounded-full transition-all" :class="(host.memory_percent ?? 0) > 85 ? 'bg-red-500' : (host.memory_percent ?? 0) > 60 ? 'bg-amber-500' : 'bg-emerald-500'" :style="'width: ' + Math.min(host.memory_percent ?? 0, 100) + '%'"></div>
                                                            </div>
                                                            <span class="tabular-nums font-medium" :class="(host.memory_percent ?? 0) > 85 ? 'text-red-600' : (host.memory_percent ?? 0) > 60 ? 'text-amber-600' : 'text-gray-700'" x-text="Math.round(host.memory_percent ?? 0) + '%'"></span>
                                                        </div>
                                                        <div class="text-[10px] text-gray-400 mt-0.5" x-text="Math.round(host.memory_used_mb ?? 0) + ' / ' + Math.round(host.memory_total_mb ?? 0) + ' MB'"></div>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="{ 'bg-blue-50 text-blue-700': host.capacity_limiter === 'cpu', 'bg-purple-50 text-purple-700': host.capacity_limiter === 'memory', 'bg-gray-100 text-gray-600': host.capacity_limiter === 'config' || !host.capacity_limiter }" x-text="(host.capacity_limiter ?? 'config').toUpperCase()"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5">
                                                        <span class="text-[11px] text-gray-500" x-text="Object.keys(host.queue_workers ?? {}).length + ' queues'"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <span class="text-[11px] text-gray-400" x-text="host.last_seen_human ?? '-'"></span>
                                                    </td>
                                                </tr>
                                                <tr x-show="expanded" x-transition.opacity>
                                                    <td colspan="7" class="px-0 py-0">
                                                        <div class="bg-gray-50/80 px-6 py-4 border-t border-gray-100">
                                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Queue Workers on <span class="text-gray-600" x-text="host.host ?? host.manager_id"></span></div>
                                                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                                                <template x-for="(count, queue) in (host.queue_workers ?? {})" :key="'detail-' + queue">
                                                                    <div class="bg-white rounded-lg border border-gray-200/80 px-3 py-2.5 flex items-center justify-between">
                                                                        <div>
                                                                            <div class="text-[11px] font-medium text-gray-900" x-text="queue.split(':').pop()"></div>
                                                                            <div class="text-[10px] text-gray-400" x-text="queue.includes(':') ? queue.split(':').slice(0, -1).join(':') : 'default'"></div>
                                                                        </div>
                                                                        <div class="flex items-center gap-1.5">
                                                                            <div class="flex gap-0.5">
                                                                                <template x-for="i in Math.min(count, 10)" :key="'dot-' + i">
                                                                                    <span class="block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                                                </template>
                                                                                <span x-show="count > 10" class="text-[9px] text-gray-400 ml-0.5" x-text="'+' + (count - 10)"></span>
                                                                            </div>
                                                                            <span class="text-lg font-bold tabular-nums text-gray-900" x-text="count"></span>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                            <div class="mt-3 flex items-center gap-4 text-[10px] text-gray-400">
                                                                <span>Manager ID: <span class="font-mono text-gray-600" x-text="host.manager_id"></span></span>
                                                                <span x-show="host.group_count > 0" x-text="host.group_count + ' groups'"></span>
                                                                <span x-text="host.memory_free_mb ? (Math.round(host.memory_free_mb) + ' MB free') : ''"></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </template>
                                    </table>
                                </div>
                            </div>
                        </template>

                        {{-- Fallback Hosts Table (from DB active managers, when live data unavailable) --}}
                        <template x-if="(!autoscale.live || (autoscale.live?.hosts ?? []).length === 0) && (autoscale.cluster?.topology?.active_managers ?? []).length > 0">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-sm font-semibold text-gray-900">Hosts</h4>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-500">FROM EVENTS</span>
                                        </div>
                                        <span class="text-[11px] text-gray-400">Live host metrics unavailable</span>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50/80 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                                <th class="px-4 py-2.5 text-left">Host</th>
                                                <th class="px-4 py-2.5 text-left">Manager ID</th>
                                                <th class="px-4 py-2.5 text-left">Role</th>
                                                <th class="px-4 py-2.5 text-right">Started</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <template x-for="mgr in (autoscale.cluster?.topology?.active_managers ?? [])" :key="mgr.manager_id">
                                                <tr class="hover:bg-gray-50/60 transition-colors">
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span class="font-medium text-gray-900" x-text="(mgr.host ?? mgr.manager_id ?? '').replace(/\.localdomain$/, '')"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span class="font-mono text-[11px] text-gray-500" x-text="mgr.manager_id"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span x-show="mgr.manager_id === autoscale.cluster?.topology?.leader_id" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold bg-indigo-50 text-indigo-600">LEADER</span>
                                                        <span x-show="mgr.manager_id !== autoscale.cluster?.topology?.leader_id" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold bg-gray-100 text-gray-500">FOLLOWER</span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <span class="text-[11px] text-gray-400" x-text="mgr.started_at_human ?? mgr.started_at ?? '-'"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        {{-- Workloads Table --}}
                        <template x-if="autoscale.live && (autoscale.live?.workloads ?? []).length > 0">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100">
                                    <h4 class="text-sm font-semibold text-gray-900">Workloads</h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50/80 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                                <th class="px-4 py-2.5 text-left">Queue</th>
                                                <th class="px-4 py-2.5 text-right">Workers</th>
                                                <th class="px-4 py-2.5 text-right">Pending</th>
                                                <th class="px-4 py-2.5 text-right">Jobs/min</th>
                                                <th class="px-4 py-2.5 text-right">Oldest Job</th>
                                                <th class="px-4 py-2.5 text-right">SLA</th>
                                                <th class="px-4 py-2.5 text-right">Utilization</th>
                                                <th class="px-4 py-2.5 text-left">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <template x-for="(wl, idx) in (autoscale.live?.workloads ?? [])" :key="'wl-' + idx">
                                                <tr class="hover:bg-gray-50/60 transition-colors">
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-medium text-gray-900" x-text="wl.name"></span>
                                                            <span class="text-[10px] text-gray-400" x-text="wl.connection"></span>
                                                            <span x-show="wl.type === 'group'" class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold bg-violet-50 text-violet-600">GROUP</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                        <span class="font-semibold text-gray-900" x-text="wl.current_workers"></span>
                                                        <span class="text-gray-400"> / </span>
                                                        <span class="text-gray-500" x-text="wl.target_workers"></span>
                                                        <div class="text-[10px] text-gray-400" x-text="wl.worker_min + '–' + wl.worker_max + ' range'"></div>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums" :class="(wl.pending ?? 0) > 0 ? 'font-semibold text-amber-600' : 'text-gray-500'" x-text="wl.pending"></td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums text-gray-700" x-text="(wl.throughput_per_minute ?? 0).toFixed(1)"></td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <span class="tabular-nums font-medium" :class="{ 'text-red-600': wl.oldest_job_age_status === 'breached', 'text-amber-600': wl.oldest_job_age_status === 'warning', 'text-gray-700': wl.oldest_job_age_status === 'normal' }" x-text="(wl.oldest_job_age ?? 0) + 's'"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums text-gray-500" x-text="(wl.sla_target_seconds ?? '-') + 's'"></td>
                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <div class="w-12 bg-gray-100 rounded-full h-1.5">
                                                                <div class="h-1.5 rounded-full transition-all" :class="(wl.utilization_percent ?? 0) > 85 ? 'bg-red-500' : (wl.utilization_percent ?? 0) > 60 ? 'bg-amber-500' : 'bg-emerald-500'" :style="'width: ' + Math.min(wl.utilization_percent ?? 0, 100) + '%'"></div>
                                                            </div>
                                                            <span class="tabular-nums font-medium text-gray-700" x-text="Math.round(wl.utilization_percent ?? 0) + '%'"></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="{ 'bg-emerald-50 text-emerald-700': wl.action === 'scale_up', 'bg-blue-50 text-blue-700': wl.action === 'scale_down', 'bg-gray-100 text-gray-600': wl.action === 'hold' }" x-text="(wl.action ?? 'hold').replace('_', ' ').toUpperCase()"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        {{-- SLA Compliance + Breach Severity --}}
                        <template x-if="autoscale.available && (autoscale.sla?.per_queue || []).length > 0">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">SLA Compliance by Queue</h4></div>
                                    <div class="divide-y divide-gray-50">
                                        <template x-for="(sla, idx) in (autoscale.sla?.per_queue ?? [])" :key="'sla-q-' + idx">
                                            <div class="px-5 py-3 flex items-center justify-between">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <span class="text-sm font-medium text-gray-900 truncate" x-text="sla.queue"></span>
                                                    <span class="text-[10px] text-gray-400" x-text="sla.target_seconds + 's target'"></span>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div class="w-24 bg-gray-100 rounded-full h-1.5">
                                                        <div class="h-1.5 rounded-full transition-all duration-500" :class="sla.compliance >= 99 ? 'bg-emerald-500' : sla.compliance >= 95 ? 'bg-amber-500' : 'bg-red-500'" :style="'width: ' + Math.min(sla.compliance, 100) + '%'"></div>
                                                    </div>
                                                    <span class="text-sm font-bold tabular-nums w-14 text-right" :class="sla.compliance >= 99 ? 'text-emerald-600' : sla.compliance >= 95 ? 'text-amber-600' : 'text-red-600'" x-text="sla.compliance.toFixed(1) + '%'"></span>
                                                    <span x-show="sla.breached > 0" class="text-[10px] text-red-500 font-semibold" x-text="sla.breached + ' breached'"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden" x-show="autoscale.scaling?.breach_severity">
                                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Breach Severity <span class="text-[11px] font-normal text-gray-400">(Last Hour)</span></h4></div>
                                    <div class="p-5 space-y-4">
                                        <div>
                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Avg Breach Duration</div>
                                            <div class="text-2xl font-bold text-red-600 tabular-nums"><span x-text="autoscale.scaling?.breach_severity?.avg_breach_seconds ?? 0"></span><span class="text-sm font-normal text-gray-400">s over SLA</span></div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Max Breach %</div>
                                            <div class="text-2xl font-bold text-red-600 tabular-nums"><span x-text="autoscale.scaling?.breach_severity?.max_breach_percentage ?? 0"></span><span class="text-sm font-normal text-gray-400">% over target</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Scaling Decision Timeline --}}
                        <template x-if="autoscale.available && (autoscale.scaling?.history ?? []).length > 0">
                            <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Scaling Decisions <span class="text-[11px] font-normal text-gray-400">(Last Hour)</span></h4></div>
                                <div class="max-h-80 overflow-y-auto custom-scroll divide-y divide-gray-50">
                                    <template x-for="(event, idx) in (autoscale.scaling?.history ?? [])" :key="'as-ev-' + idx">
                                        <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                            <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full" :class="{ 'bg-emerald-500': event.action === 'scale_up', 'bg-blue-500': event.action === 'scale_down', 'bg-red-500': event.action === 'sla_breach', 'bg-emerald-400': event.action === 'sla_recovered', 'bg-orange-500': event.action === 'sla_breach_predicted', 'bg-amber-500': event.action === 'fuse_tripped', 'bg-amber-300': event.action === 'fuse_probing', 'bg-emerald-400': event.action === 'fuse_recovered', 'bg-gray-400': event.action === 'hold' }"></span></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="{ 'bg-emerald-50 text-emerald-700': event.action === 'scale_up', 'bg-blue-50 text-blue-700': event.action === 'scale_down', 'bg-red-50 text-red-700': event.action === 'sla_breach', 'bg-emerald-50 text-emerald-600': event.action === 'sla_recovered', 'bg-orange-50 text-orange-700': event.action === 'sla_breach_predicted', 'bg-amber-50 text-amber-800': event.action === 'fuse_tripped' || event.action === 'fuse_probing', 'bg-emerald-50 text-emerald-600': event.action === 'fuse_recovered', 'bg-gray-100 text-gray-600': event.action === 'hold' }" x-text="event.action.replace(/_/g, ' ').toUpperCase()"></span>
                                                    <span class="text-[11px] text-gray-500" x-text="event.queue"></span>
                                                    <span class="text-[11px] font-medium text-gray-700" x-text="event.current_workers + ' → ' + event.target_workers + ' workers'"></span>
                                                </div>
                                                <p class="text-[11px] text-gray-400 mt-0.5 truncate" x-text="event.reason"></p>
                                            </div>
                                            <div class="flex-shrink-0"><span class="text-[10px] text-gray-400" x-text="event.time_human"></span></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Cluster Events (Leader Changes + Manager Lifecycle) --}}
                        <template x-if="autoscale.available && autoscale.cluster?.has_cluster && ((autoscale.cluster?.leader_history ?? []).length > 0 || (autoscale.cluster?.manager_events ?? []).length > 0)">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {{-- Leader Election History --}}
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Leader Election History</h4></div>
                                    <div class="max-h-64 overflow-y-auto custom-scroll divide-y divide-gray-50">
                                        <template x-for="(evt, idx) in (autoscale.cluster?.leader_history ?? [])" :key="'as-ldr-' + idx">
                                            <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                                <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full bg-indigo-500"></span></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="text-sm font-medium text-gray-900" x-text="evt.leader_id ?? '?'"></span>
                                                        <span class="text-[10px] text-gray-400" x-text="'from ' + (evt.previous_leader_id ?? '?')"></span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-400 mt-0.5" x-text="'Observed by: ' + (evt.observed_by ?? 'unknown')"></p>
                                                </div>
                                                <div class="flex-shrink-0"><span class="text-[10px] text-gray-400" x-text="evt.time_human"></span></div>
                                            </div>
                                        </template>
                                        <template x-if="(autoscale.cluster?.leader_history ?? []).length === 0">
                                            <div class="px-5 py-6 text-center text-[11px] text-gray-400">No leader changes in the last 24 hours</div>
                                        </template>
                                    </div>
                                </div>
                                {{-- Manager Lifecycle --}}
                                <div class="bg-white border border-gray-200/80 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-gray-100"><h4 class="text-sm font-semibold text-gray-900">Manager Lifecycle</h4></div>
                                    <div class="max-h-64 overflow-y-auto custom-scroll divide-y divide-gray-50">
                                        <template x-for="(evt, idx) in (autoscale.cluster?.manager_events ?? [])" :key="'as-mgr-' + idx">
                                            <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50/60 transition-colors">
                                                <div class="mt-1.5 flex-shrink-0"><span class="block h-2.5 w-2.5 rounded-full" :class="evt.event_type === 'manager_started' ? 'bg-emerald-500' : 'bg-red-400'"></span></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold" :class="evt.event_type === 'manager_started' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" x-text="evt.event_type === 'manager_started' ? 'STARTED' : 'STOPPED'"></span>
                                                        <span class="text-sm font-medium text-gray-900" x-text="evt.host ?? evt.manager_id"></span>
                                                    </div>
                                                    <div class="flex items-center gap-3 mt-0.5">
                                                        <span x-show="evt.reason" class="text-[10px] text-gray-500" x-text="evt.reason"></span>
                                                        <span x-show="evt.meta?.uptime_seconds" class="text-[10px] text-gray-400" x-text="'uptime: ' + Math.round((evt.meta?.uptime_seconds ?? 0) / 60) + 'm'"></span>
                                                        <span x-show="evt.meta?.worker_count" class="text-[10px] text-gray-400" x-text="(evt.meta?.worker_count ?? 0) + ' workers'"></span>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0"><span class="text-[10px] text-gray-400" x-text="evt.time_human"></span></div>
                                            </div>
                                        </template>
                                        <template x-if="(autoscale.cluster?.manager_events ?? []).length === 0">
                                            <div class="px-5 py-6 text-center text-[11px] text-gray-400">No manager events in the last 24 hours</div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

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
                                        title="Copy exception as markdown">
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
                    <div class="flex items-center justify-between mb-4">
                        <button @click="closeDrillDown()" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition group">
                            <svg class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            Back
                        </button>
                        <button @click="refreshDrillDown()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                            <svg class="h-3.5 w-3.5" :class="drillDownLoading && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                            Refresh
                        </button>
                    </div>

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
                mobileMenuOpen: false,

                // Job detail view (replaces slide-over)
                jobView: null,
                jobDetail: null,

                // Data stores
                overview: { stats: {}, queues: [], alerts: {}, recentJobs: [], charts: {} },
                jobs: { data: [], meta: { total: 0, limit: 50, offset: 0 } },
                analytics: {},
                health: {},
                infrastructure: {},
                autoscale: {},
                horizonAvailable: false,

                // Jobs tab state
                filters: { search: '', statuses: [], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' },
                availableQueues: [],
                selectedJobs: [],
                sorting: { field: 'queued_at', direction: 'desc' },
                pagination: { offset: 0, limit: 50, total: 0 },

                // Auto-refresh
                refreshInterval: null,
                isLive: true,
                inFlight: { overview: false, jobs: false, analytics: false, health: false, infrastructure: false, autoscale: false, detail: false, drillDown: false },
                pendingRefresh: { overview: false, jobs: false, analytics: false, health: false, infrastructure: false, autoscale: false, detail: false, drillDown: false },
                refreshing: { overview: false, jobs: false, analytics: false, health: false, infrastructure: false, autoscale: false, detail: false, drillDown: false },
                loading: { overview: true, jobs: false, analytics: false, health: false, infrastructure: false, autoscale: false, detail: false },
                lastMetaRefreshAt: 0,
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
                lastRefreshAt: null,
                clock: Date.now(),
                clockInterval: null,
                sidebarTabs: [
                    { id: 'overview', label: 'Overview', svg: '<path d="M3 13h8V3H3z"></path><path d="M13 21h8V11h-8z"></path><path d="M13 9h8V3h-8z"></path><path d="M3 21h8v-6H3z"></path>' },
                    { id: 'jobs', label: 'Jobs', svg: '<path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h10"></path>' },
                    { id: 'analytics', label: 'Analytics', svg: '<path d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5z"></path><path d="M13.5 3v7.5H21A7.5 7.5 0 0 0 13.5 3z"></path>' },
                    { id: 'health', label: 'Health', svg: '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3z"></path><path d="m9 12 2 2 4-5"></path>' },
                    { id: 'autoscale', label: 'Autoscale', svg: '<path d="M4 17V7"></path><path d="M20 17V7"></path><path d="M12 21V3"></path><path d="m8 7 4-4 4 4"></path><path d="m8 17 4 4 4-4"></path>' },
                    { id: 'infrastructure', label: 'Infrastructure', svg: '<path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path><path d="M8 6v12"></path><path d="M16 6v12"></path>', horizon: true },
                ],

                // Metric color thresholds (configurable via queue-monitor.ui)
                cpuWarning: {{ config('queue-monitor.ui.cpu_thresholds.warning', 50) }},
                cpuCritical: {{ config('queue-monitor.ui.cpu_thresholds.critical', 80) }},
                memWarning: {{ config('queue-monitor.ui.memory_thresholds.warning', 60) }},
                memCritical: {{ config('queue-monitor.ui.memory_thresholds.critical', 80) }},

                // URLs
                dashboardUrl: '{{ route("queue-monitor.dashboard") }}',
                jobViewUrlBase: '{{ route("queue-monitor.job.view", ["uuid" => "__PH__"]) }}'.replace('__PH__', ''),
                drillDownUrlBase: {
                    queue: '{{ route("queue-monitor.queue.view", ["queue" => "__PH__"]) }}'.replace('__PH__', ''),
                    server: '{{ route("queue-monitor.server.view", ["server" => "__PH__"]) }}'.replace('__PH__', ''),
                    job_class: '{{ route("queue-monitor.class.view", ["jobClass" => "__PH__"]) }}'.replace('__PH__', ''),
                },

                init() {
                    try { this.isLive = localStorage.getItem('qm.live') !== '0'; } catch (e) {}
                    this.clockInterval = setInterval(() => { this.clock = Date.now(); }, 1000);
                    this.lastMetaRefreshAt = Date.now();
                    this.fetchOverview();
                    this.fetchHealth();
                    this.fetchInfrastructure();
                    this.fetchAutoscale();
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
                        // Restore filters from URL query params first (before navigating)
                        const hasFilters = this.restoreFiltersFromUrl();

                        // Restore tab from hash fragment (#jobs, #analytics, etc.)
                        const hash = window.location.hash.replace('#', '');
                        if (hasFilters && this.activeTab !== 'jobs') {
                            // Filters present — switch to jobs tab (navigateTo will fetch with correct filters)
                            this.navigateTo('jobs');
                        } else if (['jobs', 'analytics', 'health', 'infrastructure', 'autoscale'].includes(hash)) {
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
                            // Restore filters from URL on back/forward
                            this.filters = { search: '', statuses: [], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' };
                            this.restoreFiltersFromUrl();
                            this.refreshCurrentView(true);
                        }
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this.refreshCurrentView();
                    });

                    window.addEventListener('resize', () => {
                        if (window.innerWidth > 900) this.mobileMenuOpen = false;
                        this.throughputChart?.resize();
                        this.distributionChart?.resize();
                        this.drillDownChart?.resize();
                    });

                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            if (this.mobileMenuOpen) this.mobileMenuOpen = false;
                            else if (this.drillDown) this.closeDrillDown();
                            else if (this.jobView) this.closeJobView();
                        }
                    });
                },

                startAutoRefresh() {
                    if (this.refreshInterval) clearInterval(this.refreshInterval);
                    this.refreshInterval = setInterval(() => {
                        if (!this.isLive || document.hidden) return;
                        this.refreshCurrentView();
                        // The overview's Workers card reads autoscale/infrastructure
                        // data; keep those stores fresh at a slower cadence.
                        if (this.activeTab === 'overview' && Date.now() - this.lastMetaRefreshAt > 30000) {
                            this.lastMetaRefreshAt = Date.now();
                            this.fetchInfrastructure();
                            this.fetchAutoscale();
                        }
                    }, {{ config('queue-monitor.ui.refresh_interval', 3000) }});
                },

                toggleLive() {
                    this.isLive = !this.isLive;
                    try { localStorage.setItem('qm.live', this.isLive ? '1' : '0'); } catch (e) {}
                    if (this.isLive) this.refreshCurrentView(true);
                },

                refreshCurrentView(force = false) {
                    if (!force && !this.isLive) return;
                    if (this.jobView) { this.fetchJobDetail(this.jobView); return; }
                    if (this.drillDown) { this.refreshDrillDown(); return; }
                    if (this.activeTab === 'overview') { this.fetchOverview(); this.fetchHealth(); }
                    else if (this.activeTab === 'jobs') this.fetchJobs();
                    else if (this.activeTab === 'analytics') this.fetchAnalytics();
                    else if (this.activeTab === 'health') this.fetchHealth();
                    else if (this.activeTab === 'infrastructure') this.fetchInfrastructure();
                    else if (this.activeTab === 'autoscale') this.fetchAutoscale();
                },

                pageTitle() {
                    if (this.jobView) return 'Selected job';
                    if (this.drillDown) return this.drillDown?.type === 'job_class' ? this.shortClass(this.drillDown?.value) : (this.drillDown?.value || 'Drill-down');
                    const labels = { overview: 'Overview', jobs: 'Jobs', analytics: 'Analytics', health: 'Health', autoscale: 'Autoscale', infrastructure: 'Infrastructure' };
                    return labels[this.activeTab] || 'Overview';
                },

                pageSubtitle() {
                    if (this.jobView) return 'Current failure context and payload';
                    if (this.drillDown) return 'Focused queue monitor breakdown';
                    const subtitles = {
                        overview: 'Live queue operations across workers',
                        jobs: 'Search, filter, replay, and inspect jobs',
                        analytics: 'Queue, server, and failure pattern analysis',
                        health: 'Health checks and stuck job signals',
                        autoscale: 'Worker capacity and scaling decisions',
                        infrastructure: 'Worker utilization and SLA signals',
                    };
                    return subtitles[this.activeTab] || subtitles.overview;
                },

                lastRefreshLabel() {
                    if (!this.lastRefreshAt) return 'Last refresh pending';
                    const seconds = Math.max(0, Math.floor((this.clock - this.lastRefreshAt) / 1000));
                    if (seconds < 2) return 'Last refresh now';
                    if (seconds < 60) return `Last refresh ${seconds}s ago`;
                    return `Last refresh ${Math.floor(seconds / 60)}m ago`;
                },

                anyLoading() {
                    return Object.values(this.loading).some(Boolean) || this.drillDownLoading;
                },

                anyRefreshing() {
                    return Object.values(this.refreshing).some(Boolean);
                },

                healthChecksList() {
                    return Object.entries(this.health.checks || {}).map(([name, check]) => ({
                        name: name.replace(/_/g, ' '),
                        healthy: Boolean(check?.healthy),
                        message: check?.message || '',
                    }));
                },

                attentionJobs() {
                    const jobs = this.overview.recentJobs || [];
                    const weighted = [...jobs].sort((a, b) => {
                        const aScore = (a.is_failed ? 100 : 0) + ((a.status?.value === 'timeout') ? 80 : 0) + ((a.attempt || 0) * 3);
                        const bScore = (b.is_failed ? 100 : 0) + ((b.status?.value === 'timeout') ? 80 : 0) + ((b.attempt || 0) * 3);
                        return bScore - aScore;
                    });
                    return weighted;
                },

                selectedOverviewJob() {
                    return this.attentionJobs()[0] || null;
                },

                failureRateLabel() {
                    const total = Number(this.overview.stats.total || 0);
                    const failed = Number(this.overview.stats.failed || 0);
                    if (total <= 0) return '0%';
                    return `${this.formatNumber((failed / total) * 100, 2)}%`;
                },

                queueBacklogSeverity() {
                    const backlog = Number(this.overview.stats.queue_backlog || 0);
                    if (backlog >= 100) return 'qm-bad';
                    if (backlog >= 25) return 'qm-warn';
                    return 'qm-good';
                },

                queueBacklogLabel() {
                    const backlog = Number(this.overview.stats.queue_backlog || 0);
                    if (backlog >= 100) return 'High';
                    if (backlog >= 25) return 'Hot';
                    return 'OK';
                },

                queueBacklogTrendLabel() {
                    const backlog = Number(this.overview.stats.queue_backlog || 0);
                    if (backlog >= 100) return 'Action';
                    if (backlog >= 25) return 'Rising';
                    return 'Stable';
                },

                workerCountLabel() {
                    return this.autoscale.live?.total_workers
                        ?? this.autoscale.cluster?.scaling_signal?.current_capacity
                        ?? this.infrastructure.scaling?.utilization?.total_workers
                        ?? '-';
                },

                workerStatusLabel() {
                    const utilization = this.autoscale.live?.utilization_percent ?? this.infrastructure.scaling?.utilization?.percentage;
                    if (utilization == null) return 'Ready';
                    if (utilization >= 90) return 'Hot';
                    if (utilization >= 70) return 'Busy';
                    return 'Healthy';
                },

                autoscaleStatusLabel() {
                    if (this.autoscale.cluster?.scaling_signal?.action) {
                        return this.autoscale.cluster.scaling_signal.action.replace('_', ' ');
                    }
                    if (this.autoscale.available === false) return 'Optional';
                    return this.autoscale.available ? 'Active' : 'Ready';
                },

                throughputTotal() {
                    const points = this.overview.charts?.throughput || [];
                    return points.reduce((sum, item) => sum + Number(item.completed || 0) + Number(item.failed || 0), 0);
                },

                queueFillWidth(queue) {
                    const queues = this.overview.queues || [];
                    const max = Math.max(1, ...queues.map(q => Number(q.total_last_hour || 0) + Number(q.processing || 0) + Number(q.failed || 0)));
                    const value = Number(queue.total_last_hour || 0) + Number(queue.processing || 0) + Number(queue.failed || 0);
                    return Math.min(100, Math.max(4, Math.round((value / max) * 100)));
                },

                queueFillClass(queue) {
                    if ((queue.failed || 0) > 0) return 'bad';
                    if ((queue.processing || 0) > 0 || (queue.total_last_hour || 0) > 50) return 'warn';
                    return '';
                },

                queueStatusTone(queue) {
                    if ((queue.failed || 0) > 0 || queue.status === 'unhealthy') return 'qm-bad';
                    if ((queue.processing || 0) > 0 || queue.status === 'degraded') return 'qm-warn';
                    return 'qm-good';
                },

                queueShortStatus(queue) {
                    if ((queue.failed || 0) > 0 || queue.status === 'unhealthy') return 'High';
                    if ((queue.processing || 0) > 0 || queue.status === 'degraded') return 'Hot';
                    return 'OK';
                },

                healthTone() {
                    const status = this.healthBadge();
                    if (status === 'healthy') return 'qm-good';
                    if (status === 'degraded') return 'qm-warn';
                    return 'qm-bad';
                },

                healthBadgeLabel() {
                    const status = this.healthBadge();
                    return status.charAt(0).toUpperCase() + status.slice(1);
                },

                attemptLabel(job) {
                    if (!job) return '-';
                    return `${job.attempt || 1}${job.max_attempts ? ' / ' + job.max_attempts : ''}`;
                },

                statusTone(status) {
                    const classes = {
                        completed: 'qm-good',
                        failed: 'qm-bad',
                        timeout: 'qm-bad',
                        processing: 'qm-info',
                        queued: 'qm-warn',
                        debounced: 'qm-info',
                    };
                    return classes[status] || 'qm-info';
                },

                async runLatest(scope, callback) {
                    if (this.inFlight[scope]) {
                        this.pendingRefresh[scope] = true;
                        return;
                    }

                    this.inFlight[scope] = true;
                    this.pendingRefresh[scope] = false;
                    this.refreshing[scope] = true;

                    try {
                        await callback();
                    } finally {
                        this.inFlight[scope] = false;
                        this.refreshing[scope] = false;

                        if (this.pendingRefresh[scope] && this.isLive) {
                            this.pendingRefresh[scope] = false;
                            this.refreshScope(scope);
                        }
                    }
                },

                refreshScope(scope) {
                    if (scope === 'overview') this.fetchOverview();
                    else if (scope === 'jobs') this.fetchJobs();
                    else if (scope === 'analytics') this.fetchAnalytics();
                    else if (scope === 'health') this.fetchHealth();
                    else if (scope === 'infrastructure') this.fetchInfrastructure();
                    else if (scope === 'autoscale') this.fetchAutoscale();
                    else if (scope === 'detail' && this.jobView) this.fetchJobDetail(this.jobView);
                    else if (scope === 'drillDown') this.refreshDrillDown();
                },

                // ========== NAVIGATION ==========

                navigateTo(tab) {
                    this.mobileMenuOpen = false;

                    // Close sub-views without pushing their own state
                    if (this.jobView) { this.jobView = null; this.jobDetail = null; }
                    if (this.drillDown) {
                        try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (e) {}
                        this.drillDownChart = null;
                        this.drillDown = null; this.drillDownData = null;
                    }
                    this.activeTab = tab;
                    this.pushTabState(tab);
                    if (tab === 'overview') this.fetchOverview();
                    else if (tab === 'jobs') this.fetchJobs();
                    else if (tab === 'analytics') this.fetchAnalytics();
                    else if (tab === 'health') this.fetchHealth();
                    else if (tab === 'infrastructure') this.fetchInfrastructure();
                    else if (tab === 'autoscale') this.fetchAutoscale();
                    this.$nextTick(() => {
                        if (tab === 'overview') this.initThroughputChart();
                        if (tab === 'analytics') this.initDistributionChart();
                    });
                },

                pushTabState(tab) {
                    const hash = tab === 'overview' ? '' : '#' + tab;
                    const qs = window.location.search;
                    history.pushState({ tab }, '', this.dashboardUrl + qs + hash);
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
                        const response = await fetch(url, {
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json', ...(options.headers || {}) },
                            ...options,
                        });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        this.error = null;
                        this.retryCount = 0;
                        this.lastRefreshAt = Date.now();
                        this.clock = this.lastRefreshAt;
                        return await response.json();
                    } catch (err) {
                        if (retries < this.maxRetries && this.isLive) {
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
                    return this.runLatest('overview', async () => {
                        try {
                        const data = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.metrics") }}');
                        this.overview.stats = data.stats || {};
                        this.overview.queues = data.queues || [];
                        this.overview.alerts = data.alerts || {};
                        this.overview.recentJobs = data.recent_jobs || [];
                        this.overview.charts = data.charts || {};
                        const queueNames = [...new Set((data.queues || []).map(q => q.queue).filter(Boolean))].sort();
                        if (this.availableQueues.length === 0 && queueNames.length > 0) this.availableQueues = queueNames;
                        if (data.horizon_available !== undefined) this.horizonAvailable = data.horizon_available;
                        this.loading.overview = false;
                        this.$nextTick(() => { this.initThroughputChart(); this.updateThroughputChart(data.charts?.throughput); });
                        } catch (e) { this.loading.overview = false; console.error('fetchOverview error:', e); }
                    });
                },

                async fetchJobs() {
                    return this.runLatest('jobs', async () => {
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
                        if (Array.isArray(metaQueues)) this.availableQueues = metaQueues;
                        else if (this.availableQueues.length === 0 && this.jobs.data.length > 0) this.availableQueues = [...new Set(this.jobs.data.map(j => j.queue).filter(Boolean))].sort();
                        } catch (e) { console.error('fetchJobs error:', e); } finally { this.loading.jobs = false; }
                    });
                },

                async fetchJobDetail(uuid) {
                    return this.runLatest('detail', async () => {
                        this.loading.detail = true;
                        try {
                        const url = '{{ route("queue-monitor.dashboard.job.detail", ["uuid" => "_UUID_"]) }}'.replace('_UUID_', uuid);
                        const data = await this.fetchWithRetry(url);
                        if (this.jobView !== uuid) return;
                        this.jobDetail = data;
                        } catch (e) { console.error('fetchJobDetail error:', e); } finally { this.loading.detail = false; }
                    });
                },

                async fetchAnalytics() {
                    return this.runLatest('analytics', async () => {
                        if (Object.keys(this.analytics).length === 0) this.loading.analytics = true;
                        try {
                        const data = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.analytics") }}');
                        this.analytics = data;
                        this.$nextTick(() => { this.initDistributionChart(); this.updateDistributionChart(data.job_classes); });
                        } catch (e) { console.error('fetchAnalytics error:', e); } finally { this.loading.analytics = false; }
                    });
                },

                async fetchHealth() {
                    return this.runLatest('health', async () => {
                        if (Object.keys(this.health).length === 0) this.loading.health = true;
                        try { this.health = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.health") }}'); } catch (e) { console.error('fetchHealth error:', e); } finally { this.loading.health = false; }
                    });
                },

                async fetchInfrastructure() {
                    return this.runLatest('infrastructure', async () => {
                        if (Object.keys(this.infrastructure).length === 0) this.loading.infrastructure = true;
                        try { this.infrastructure = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.infrastructure") }}'); } catch (e) { console.error('fetchInfrastructure error:', e); } finally { this.loading.infrastructure = false; }
                    });
                },

                async fetchAutoscale() {
                    return this.runLatest('autoscale', async () => {
                        if (Object.keys(this.autoscale).length === 0) this.loading.autoscale = true;
                        try { this.autoscale = await this.fetchWithRetry('{{ route("queue-monitor.dashboard.autoscale") }}'); } catch (e) { console.error('fetchAutoscale error:', e); } finally { this.loading.autoscale = false; }
                    });
                },

                // ========== ACTIONS ==========

                async replayJob(uuid) {
                    if (!uuid) return;
                    try {
                        await fetch(this.dashboardUrl + '/jobs/' + uuid + '/replay', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
                        if (this.activeTab === 'overview') this.fetchOverview();
                        if (this.activeTab === 'jobs') this.fetchJobs();
                    } catch (e) { this.error = 'Failed to replay job'; console.error('replayJob error:', e); }
                },

                confirmDeleteJob(uuid) { this.confirmDelete = uuid; },

                async deleteJob(uuid) {
                    if (!uuid) return;
                    this.confirmDelete = null;
                    try {
                        const res = await fetch(this.dashboardUrl + '/jobs/' + uuid + '/delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } });
                        if (!res.ok) { const err = await res.json().catch(() => ({})); this.error = err.message || 'Failed to delete job (HTTP ' + res.status + ')'; return; }
                        if (this.jobView) this.closeJobView();
                        if (this.activeTab === 'overview') this.fetchOverview();
                        if (this.activeTab === 'jobs') this.fetchJobs();
                    } catch (e) { this.error = 'Failed to delete job'; console.error('deleteJob error:', e); }
                },

                async batchReplay() {
                    if (this.selectedJobs.length === 0) return;
                    try {
                        await fetch(this.dashboardUrl + '/batch/replay', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ uuids: this.selectedJobs }) });
                        this.selectedJobs = []; this.fetchJobs();
                    } catch (e) { this.error = 'Failed to replay jobs'; console.error('batchReplay error:', e); }
                },

                async resolveStuckJob(uuid, action) {
                    if (!uuid) return;
                    if (action === 'delete' && !confirm('Delete this stuck job? This cannot be undone.')) return;
                    try {
                        const res = await fetch(this.dashboardUrl + '/stuck-jobs/resolve', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ uuids: [uuid], action }) });
                        if (!res.ok) throw new Error('Request failed');
                        this.fetchHealth();
                    } catch (e) { this.error = `Failed to ${action} stuck job`; console.error('resolveStuckJob error:', e); }
                },

                async resolveAllStuckJobs(action) {
                    const label = action === 'delete' ? 'delete' : 'retry';
                    if (!confirm(`${label.charAt(0).toUpperCase() + label.slice(1)} all stuck jobs?`)) return;
                    try {
                        const res = await fetch(this.dashboardUrl + '/stuck-jobs/resolve-all', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ action }) });
                        if (!res.ok) throw new Error('Request failed');
                        this.fetchHealth();
                    } catch (e) { this.error = `Failed to ${label} stuck jobs`; console.error('resolveAllStuckJobs error:', e); }
                },

                async batchDelete() {
                    if (this.selectedJobs.length === 0) return;
                    if (!confirm(`Delete ${this.selectedJobs.length} job(s)? This cannot be undone.`)) return;
                    try {
                        await fetch(this.dashboardUrl + '/batch/delete', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: JSON.stringify({ uuids: this.selectedJobs }) });
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
                        this.$nextTick(() => requestAnimationFrame(() => this.initDrillDownChart()));
                    }
                },

                closeDrillDown() {
                    try { if (this.drillDownChart) { this.drillDownChart.dispose(); } } catch (e) {}
                    this.drillDownChart = null;
                    this.drillDown = null;
                    this.drillDownData = null;
                    this.pushTabState(this.activeTab);
                },

                async refreshDrillDown() {
                    if (!this.drillDown) return;
                    return this.runLatest('drillDown', async () => {
                        const { type, value } = this.drillDown;
                        try {
                        const url = `{{ route('queue-monitor.dashboard.drill-down') }}?type=${type}&value=${encodeURIComponent(value)}`;
                        const data = await this.fetchWithRetry(url);
                        this.drillDownData = data;
                        this.updateDrillDownChart(data?.throughput);
                        } catch (e) { console.error('refreshDrillDown error:', e); }
                    });
                },

                drillDownToJobs(type, value) {
                    this.closeDrillDown();
                    this.filters = { search: '', statuses: [], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' };
                    this.pagination.offset = 0;
                    this.selectedJobs = [];
                    if (type === 'queue') this.filters.queue = value;
                    if (type === 'server') this.filters.server = value;
                    if (type === 'job_class') this.filters.jobClass = value;
                    this.syncFiltersToUrl();
                    this.navigateTo('jobs');
                },

                filterJobsByException(exceptionClass) {
                    this.filters = { search: exceptionClass, statuses: ['failed', 'timeout'], queue: '', dateFrom: '', dateTo: '', showAdvanced: false, jobClass: '', server: '', minAttempts: '', minDuration: '' };
                    this.pagination.offset = 0;
                    this.selectedJobs = [];
                    this.syncFiltersToUrl();
                    this.navigateTo('jobs');
                },

                initDrillDownChart(retries = 0) {
                    try {
                        const el = document.getElementById('drilldown-throughput-chart');
                        if (!el) return;
                        if (el.offsetWidth === 0) {
                            if (retries < 10) requestAnimationFrame(() => this.initDrillDownChart(retries + 1));
                            return;
                        }
                        if (!this.drillDownChart) {
                            this.drillDownChart = echarts.init(el);
                        }
                        this.updateDrillDownChart(this.drillDownData?.throughput);
                    } catch (e) { console.warn('Drill-down chart init error:', e.message); }
                },

                updateDrillDownChart(data) {
                    if (!this.drillDownChart || !data || !Array.isArray(data) || data.length === 0) return;
                    try {
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
                    } catch (e) { console.warn('Drill-down chart update error:', e.message); }
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
                    this.syncFiltersToUrl();
                    this.resetPaginationAndFetch();
                },

                resetPaginationAndFetch() { this.pagination.offset = 0; this.selectedJobs = []; this.syncFiltersToUrl(); this.fetchJobs(); },

                toggleSort(field) {
                    if (this.sorting.field === field) this.sorting.direction = this.sorting.direction === 'asc' ? 'desc' : 'asc';
                    else { this.sorting.field = field; this.sorting.direction = 'desc'; }
                    this.syncFiltersToUrl();
                    this.fetchJobs();
                },

                // ========== URL FILTER PERSISTENCE ==========

                syncFiltersToUrl() {
                    const params = new URLSearchParams();
                    if (this.filters.search) params.set('search', this.filters.search);
                    this.filters.statuses.forEach(s => params.append('status', s));
                    if (this.filters.queue) params.set('queue', this.filters.queue);
                    if (this.filters.dateFrom) params.set('from', this.filters.dateFrom);
                    if (this.filters.dateTo) params.set('to', this.filters.dateTo);
                    if (this.filters.jobClass) params.set('class', this.filters.jobClass);
                    if (this.filters.server) params.set('server', this.filters.server);
                    if (this.filters.minAttempts) params.set('attempts', this.filters.minAttempts);
                    if (this.filters.minDuration) params.set('duration', this.filters.minDuration);
                    if (this.sorting.field !== 'queued_at') params.set('sort', this.sorting.field);
                    if (this.sorting.direction !== 'desc') params.set('dir', this.sorting.direction);
                    if (this.pagination.offset > 0) params.set('offset', this.pagination.offset);

                    const hash = this.activeTab === 'overview' ? '' : '#' + this.activeTab;
                    const qs = params.toString();
                    const url = this.dashboardUrl + (qs ? '?' + qs : '') + hash;
                    history.replaceState({ tab: this.activeTab, filters: true }, '', url);
                },

                restoreFiltersFromUrl() {
                    const params = new URLSearchParams(window.location.search);
                    if (!params.toString()) return false;

                    let restored = false;
                    if (params.has('search')) { this.filters.search = params.get('search'); restored = true; }
                    const statuses = params.getAll('status');
                    if (statuses.length > 0) { this.filters.statuses = statuses; restored = true; }
                    if (params.has('queue')) { this.filters.queue = params.get('queue'); restored = true; }
                    if (params.has('from')) { this.filters.dateFrom = params.get('from'); restored = true; }
                    if (params.has('to')) { this.filters.dateTo = params.get('to'); restored = true; }
                    if (params.has('class')) { this.filters.jobClass = params.get('class'); restored = true; }
                    if (params.has('server')) { this.filters.server = params.get('server'); restored = true; }
                    if (params.has('attempts')) { this.filters.minAttempts = params.get('attempts'); restored = true; }
                    if (params.has('duration')) { this.filters.minDuration = params.get('duration'); restored = true; }
                    if (params.has('sort')) { this.sorting.field = params.get('sort'); restored = true; }
                    if (params.has('dir')) { this.sorting.direction = params.get('dir'); restored = true; }
                    if (params.has('offset')) { this.pagination.offset = parseInt(params.get('offset')) || 0; restored = true; }

                    // Show advanced filters panel if any advanced filter is active
                    if (this.filters.jobClass || this.filters.server || this.filters.minAttempts || this.filters.minDuration) {
                        this.filters.showAdvanced = true;
                    }

                    return restored;
                },

                sortIndicator(field) { if (this.sorting.field !== field) return ''; return this.sorting.direction === 'asc' ? '\u2191' : '\u2193'; },
                toggleAllJobs(event) { this.selectedJobs = event.target.checked ? this.jobs.data.map(j => j.uuid) : []; },
                prevPage() { this.pagination.offset = Math.max(0, this.pagination.offset - this.pagination.limit); this.selectedJobs = []; this.syncFiltersToUrl(); this.fetchJobs(); },
                nextPage() { this.pagination.offset += this.pagination.limit; this.selectedJobs = []; this.syncFiltersToUrl(); this.fetchJobs(); },

                // ========== CHARTS ==========

                initThroughputChart(retries = 0) {
                    const el = document.getElementById('throughput-chart');
                    if (!el) return;
                    if (el.offsetWidth === 0) {
                        if (retries < 10) requestAnimationFrame(() => this.initThroughputChart(retries + 1));
                        return;
                    }
                    if (this.throughputChart) return;
                    this.throughputChart = echarts.init(el);
                },

                updateThroughputChart(data) {
                    if (!this.throughputChart || !data || !Array.isArray(data) || data.length === 0) return;
                    try {
                        const labels = data.map(d => { const parts = (d.minute || '').split(' '); return parts.length > 1 ? parts[1] : d.minute; });
                        this.throughputChart.setOption({
                            tooltip: { trigger: 'axis', axisPointer: { type: 'line' }, formatter: (params) => { let html = `<div style="font-size:12px;font-weight:600;margin-bottom:4px">${params[0]?.axisValue || ''}</div>`; params.forEach(p => { html += `<div style="font-size:11px">${p.marker} ${p.seriesName}: ${p.value}</div>`; }); return html; } },
                            grid: { top: 12, right: 10, bottom: 24, left: 42 },
                            xAxis: { type: 'category', data: labels, boundaryGap: false, axisLine: { lineStyle: { color: '#d9e0e8' } }, axisTick: { show: false }, axisLabel: { fontSize: 11, color: '#697386', interval: Math.max(0, Math.floor(labels.length / 8) - 1) } },
                            yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#d9e0e8' } }, axisLabel: { fontSize: 11, color: '#697386' } },
                            series: [
                                { name: 'Completed', type: 'line', smooth: true, showSymbol: false, data: data.map(d => d.completed || 0), lineStyle: { color: '#2563eb', width: 4 }, areaStyle: { color: 'rgba(37, 99, 235, 0.08)' } },
                                { name: 'Failed', type: 'line', smooth: true, showSymbol: false, data: data.map(d => d.failed || 0), lineStyle: { color: '#0f766e', width: 4 }, areaStyle: { color: 'rgba(15, 118, 110, 0.04)' } },
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
                    const classes = { 'completed': 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20', 'failed': 'bg-red-50 text-red-700 ring-1 ring-red-500/20', 'timeout': 'bg-red-50 text-red-700 ring-1 ring-red-500/20', 'processing': 'bg-blue-50 text-blue-700 ring-1 ring-blue-500/20', 'queued': 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20', 'debounced': 'bg-slate-50 text-slate-600 ring-1 ring-slate-400/20' };
                    return classes[status] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-500/20';
                },

                formatNumber(num, decimals = 0) {
                    if (num === undefined || num === null) return '0';
                    return new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num);
                },

                formatCompact(num) {
                    if (num === undefined || num === null) return '0';
                    return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(num);
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

                cpuColor(cpuTimeMs, durationMs) {
                    if (cpuTimeMs == null || durationMs == null || durationMs === 0) return 'text-gray-500';
                    const pct = (cpuTimeMs / durationMs) * 100;
                    if (pct >= this.cpuCritical) return 'text-red-600';
                    if (pct >= this.cpuWarning) return 'text-amber-600';
                    return 'text-emerald-600';
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

                memoryColor(peakMb, limitMb) {
                    if (peakMb == null || limitMb == null || limitMb <= 0) return 'text-gray-500';
                    const pct = (peakMb / limitMb) * 100;
                    if (pct >= this.memCritical) return 'text-red-600';
                    if (pct >= this.memWarning) return 'text-amber-600';
                    return 'text-emerald-600';
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
    {!! app(\Cbox\LaravelQueueMonitor\Support\DashboardAssets::class)->scripts() !!}
</body>
</html>
