---
title: "Dashboard Assets"
description: "How to use, publish, override, and rebuild Queue Monitor dashboard assets"
weight: 17
---

# Dashboard Assets

Queue Monitor ships with precompiled dashboard CSS and JavaScript. No Node, Vite, Livewire, or Inertia setup is required in the host application.

## Default: Inline Package Assets

By default, the dashboard reads precompiled files from the installed package and inlines them into the Blade view:

```env
QUEUE_MONITOR_ASSET_MODE=inline
```

This is the zero-setup mode and works without publishing assets.

## Public Static Assets

If your Content Security Policy disallows inline CSS or JavaScript, publish the compiled assets:

```bash
php artisan vendor:publish --tag="queue-monitor-assets"
```

Then switch the dashboard to public asset mode:

```env
QUEUE_MONITOR_ASSET_MODE=public
QUEUE_MONITOR_ASSET_URL=/vendor/queue-monitor
```

You can also change individual file names if your deployment pipeline fingerprints them:

```php
// config/queue-monitor.php
'ui' => [
    'assets' => [
        'mode' => 'public',
        'url' => '/build/queue-monitor',
        'paths' => [
            'css' => 'queue-monitor.abc123.css',
            'echarts' => 'echarts.abc123.js',
            'alpine' => 'alpine.abc123.js',
        ],
    ],
],
```

## Full App-Level Override

For a fully custom dashboard build, publish the views and source asset files:

```bash
php artisan vendor:publish --tag="queue-monitor-views"
php artisan vendor:publish --tag="queue-monitor-asset-sources"
```

This publishes:

- Dashboard views to `resources/views/vendor/queue-monitor`
- Source CSS to `resources/vendor/queue-monitor/queue-monitor.css`
- A reference Tailwind config to `resources/vendor/queue-monitor/tailwind.config.js`

Add the published dashboard views to your application's Tailwind `content` paths:

```js
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/views/vendor/queue-monitor/**/*.blade.php',
  ],
};
```

Import the Queue Monitor source CSS from your app CSS entry:

```css
@import "../vendor/queue-monitor/queue-monitor.css";
```

Install and bundle the browser libraries in your app build if your published view depends on them:

```bash
npm install alpinejs echarts
```

Then edit the published dashboard view to load your app bundle, for example with Vite:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Finally, disable package asset rendering:

```env
QUEUE_MONITOR_ASSET_MODE=none
```

Published views are owned by your application after publication. Republish or manually port upstream view changes when upgrading Queue Monitor.
