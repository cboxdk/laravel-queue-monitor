# Dashboard Redesign Spec

## Summary

Full redesign of the web dashboard and TUI dashboard. The web dashboard goes from a minimal auto-refresh view (~20% of API features) to a full-featured tabbed monitoring hub. The TUI dashboard goes from a static render with fake keyboard shortcuts to a working interactive terminal UI.

## Design Decisions

- **Layout**: Tabbed Hub (Overview, Jobs, Analytics, Health)
- **Theme**: Light, Cortex-inspired — gradient background (#e8edf5 → #e6e0f3), white cards, blue/purple accents (#4f6df5, #7c5bf5)
- **Stack**: Alpine.js + Tailwind CSS (no build step, single Blade template per view)
- **Responsive**: Desktop and mobile equally prioritized. Stats grid collapses to 2-col on mobile, sidebar stacks below main content, slide-over becomes full-screen on mobile, table scrolls horizontally or switches to card layout on small screens.
- **Job filtering**: Combined — inline bar (search, status, queue, date range) + "More filters" expandable panel
- **Job detail**: Slide-over panel from right side
- **TUI**: Interactive terminal with keyboard navigation

## Web Dashboard

### Tab Structure

**4 tabs**, each a separate Blade partial loaded via Alpine.js tab switching (no page reload):

#### 1. Overview Tab (default)
- **Stats grid** (5 cards): Total jobs (24h), success rate, failed count, avg duration, queue backlog
- **Recent jobs table** (per_page config, default 35): Status badge, job class (monospace), queue, duration, time ago, replay button on failed jobs. "View all →" links to Jobs tab.
- **Sidebar** (right, 380px):
  - Queue health panel: Queue name, jobs/min, stuck count, health dot
  - Active alerts panel: Severity badge, message text
  - Throughput chart (1h): Bar chart, jobs per minute, failed bars in red

#### 2. Jobs Tab
- **Filter bar** (always visible):
  - Search input (searches UUID, job class, exception message)
  - Status dropdown (multi-select: queued, processing, completed, failed, timeout, cancelled)
  - Queue dropdown (populated from data)
  - Date range picker (queued_after / queued_before)
  - "More filters" button → expandable panel with: job class, server name, worker type, tags, duration range, sort options
  - "Clear filters" button when filters active
- **Bulk actions bar** (appears when checkboxes selected): "Replay selected", "Delete selected", count indicator
- **Jobs table**: Checkbox, status, job class, queue, duration, server, attempt, time. Sortable columns (click header). Paginated (offset-based, matching API).
- **Click row** → slide-over panel

#### 3. Analytics Tab
- **Job class distribution**: Pie/donut chart (top 10 job classes)
- **Per-queue stats**: Table with queue name, total, completed, failed, avg duration, success rate
- **Per-server stats**: Table with server name, job count, avg duration, worker type
- **Failure patterns**: Table with exception class, count, last occurrence, affected queues
- **Tag analytics**: Table with tag, count, success rate

#### 4. Health Tab
- **Health score**: Large number (0-100) with color indicator
- **Health checks**: List of checks (database, recent_activity, stuck_jobs, error_rate, queue_backlog, storage) with healthy/unhealthy status and details
- **Active alerts**: Full alert list with severity, message, count
- **System info**: Server name, database connection, table size, row count

### Slide-over Panel (Job Detail)

Cortex-style slide-in from right side. 480px wide, white background, close button top-right, `Esc` to close. Left edge has a vertical icon navigation strip (like Cortex's Overview/Deploy/Containers/Config tabs) for switching between sections within the panel.

**Vertical icon nav sections:**
1. **Overview** (grid icon) — Job class, UUID (click to copy), status badge, queue, connection, attempt/max_attempts, server, worker type, timestamps (queued, started, completed, duration), metrics (memory, CPU, file descriptors), tags as pill badges
2. **Payload** (code icon) — Full JSON tree with syntax highlighting, redacted sensitive keys marked as `*****`, expand/collapse nested objects
3. **Exception** (warning icon, only shown if failed) — Exception class, message, full stack trace in scrollable monospace block
4. **Retry Chain** (link icon, only shown if retried) — Timeline of retry attempts with status, duration, and click to navigate

**Actions bar** (bottom of panel, always visible): Replay button (failed only), Delete button (with confirmation dialog)

### Data Flow

All data fetched via internal routes (not the public API). The existing `/metrics` endpoint is expanded, and the existing `/jobs/{uuid}/payload` endpoint is reused. New endpoints added for tab-specific data:

- `GET /queue-monitor/metrics` — Overview tab data (stats, recent jobs, queue health, alerts, throughput)
- `GET /queue-monitor/jobs` — Jobs tab data (uses JobFilterData, returns paginated)
- `GET /queue-monitor/jobs/{uuid}` — Slide-over detail data (full job with payload, exception, retry chain)
- `GET /queue-monitor/analytics` — Analytics tab data (distribution, per-queue, per-server, failures, tags)
- `GET /queue-monitor/health` — Health tab data (score, checks, alerts, system info)

These are UI routes (web middleware), not API routes. They return JSON for Alpine.js consumption.

### Auto-refresh

- Overview tab: Polls every `config('queue-monitor.ui.refresh_interval')` ms (default 3000)
- Jobs tab: Polls when no filters active, pauses when user is filtering
- Analytics/Health: Manual refresh button, no auto-poll
- Visual indicator: Green pulsing dot in header when live, grey when paused

### Error Handling

- Failed fetch: Show inline error banner "Failed to load data. Retrying..." with automatic retry (3 attempts, exponential backoff)
- Empty states: Descriptive message per section ("No jobs recorded yet", "No failed jobs in the last 24 hours")
- Loading states: Skeleton/shimmer placeholders on initial load

## TUI Dashboard

### Approach

The TUI uses Laravel's `QueueMonitorDashboardCommand` with Termwind for rendering. The command runs a loop that:

1. Clears the screen
2. Renders the current view
3. Listens for keyboard input (non-blocking via `stty` raw mode)
4. Updates on keypress or every N seconds

The `--once` flag renders a single snapshot and exits (for CI/scripting).

### Layout

Single-screen terminal layout:

```
┌─ Queue Monitor ─────────────────────────── Healthy ── Live 3s ─┐
│ Jobs: 2,847 │ Success: 97.2% │ Failed: 47 │ Avg: 842ms │ Q: 23│
├────────────────────────────────────────────────────────────────-┤
│ STATUS  │ JOB CLASS           │ QUEUE    │ DURATION │ TIME      │
│ ✓ Done  │ SendWelcomeEmail    │ emails   │   342ms  │ 1m ago    │
│ ✗ Fail  │ ProcessPayment      │ payments │  1204ms  │ 2m ago    │
│ ● Run   │ GenerateReport      │ reports  │      —   │ 12s ago   │
│ ✓ Done  │ SyncInventory       │ default  │  2891ms  │ 1m ago    │
│ …       │                     │          │          │           │
├─────────────────────────────────────────────────────────────────┤
│ [R] Replay  [D] Delete  [↑↓] Navigate  [F] Filter  [Q] Quit   │
└─────────────────────────────────────────────────────────────────┘
```

### Keyboard Controls

- `↑`/`↓` or `j`/`k`: Navigate job list (highlighted row)
- `Enter`: Show job detail (replaces table with detail view, `Esc` to go back)
- `r`: Replay selected failed job (with confirmation prompt)
- `d`: Delete selected job (with confirmation prompt)
- `f`: Toggle filter mode (type to search, `Esc` to cancel)
- `s`: Cycle status filter (all → failed → processing → completed)
- `1`-`4`: Switch views (1=jobs, 2=stats, 3=queues, 4=health)
- `q` or `Ctrl+C`: Quit

### Views

1. **Jobs view** (default): Scrollable job list with status, class, queue, duration, time
2. **Stats view**: Global statistics, top job classes, failure rate
3. **Queues view**: Per-queue health with job rate and stuck count
4. **Health view**: Health checks with status indicators

### Non-interactive Mode

`php artisan queue-monitor:dashboard --once` renders a single snapshot and exits with appropriate exit code (0 = healthy, 1 = degraded). Useful for CI health checks.

## Shared Components

### Color Palette (CSS custom properties)

```css
--bg-gradient-start: #e8edf5;
--bg-gradient-end: #e6e0f3;
--card-bg: #ffffff;
--card-border: #e5e7eb;
--card-shadow: 0 1px 3px rgba(0,0,0,0.04);
--text-primary: #111827;
--text-secondary: #6b7280;
--text-muted: #9ca3af;
--accent-primary: #4f6df5;
--accent-secondary: #7c5bf5;
--success: #059669;
--danger: #ef4444;
--warning: #d97706;
--info: #4f6df5;
```

### Status Badge Colors

| Status | Background | Text |
|--------|-----------|------|
| Completed | #ecfdf5 | #059669 |
| Failed | #fef2f2 | #ef4444 |
| Processing | #eef2ff | #4f6df5 |
| Queued | #fffbeb | #d97706 |
| Timeout | #fef2f2 | #ef4444 |
| Cancelled | #f3f4f6 | #6b7280 |

## Files Changed

### New Files
- `resources/views/queue-monitor/dashboard.blade.php` — Main layout with tabs
- `resources/views/queue-monitor/partials/overview.blade.php` — Overview tab
- `resources/views/queue-monitor/partials/jobs.blade.php` — Jobs tab
- `resources/views/queue-monitor/partials/analytics.blade.php` — Analytics tab
- `resources/views/queue-monitor/partials/health.blade.php` — Health tab
- `resources/views/queue-monitor/partials/slide-over.blade.php` — Job detail panel

### Modified Files
- `resources/views/web/dashboard.blade.php` — Full rewrite
- `resources/views/tui/dashboard.blade.php` — Full rewrite
- `src/Http/Controllers/DashboardController.php` — New endpoints for tabs
- `src/Commands/QueueMonitorDashboardCommand.php` — Interactive loop, keyboard input
- `routes/ui.php` — New UI data routes

### Deleted Files
None — old views are overwritten, not deleted.

## Out of Scope

- Dark mode toggle (can be added later)
- WebSocket real-time updates (polling is sufficient)
- Custom dashboard widgets/plugins
- User preferences storage
- Native mobile app

## Testing

- Feature tests for all new UI data endpoints (metrics, jobs, analytics, health)
- Tests for DashboardController new methods
- TUI command test with `--once` flag (verifiable output)
- Middleware tests already exist and cover auth
