<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Cbox\LaravelQueueMonitor\LaravelQueueMonitorServiceProvider;
use Illuminate\Support\HtmlString;
use ReflectionClass;

final class DashboardAssets
{
    /**
     * @var array{css: string, echarts: string, alpine: string}
     */
    private const DEFAULT_PATHS = [
        'css' => 'queue-monitor.css',
        'echarts' => 'echarts.min.js',
        'alpine' => 'alpine.min.js',
    ];

    public function styles(): HtmlString
    {
        return match ($this->mode()) {
            'none' => new HtmlString(''),
            'public' => new HtmlString('<link rel="stylesheet" href="'.$this->escape($this->assetUrl($this->paths()['css'])).'">'),
            default => new HtmlString('<style>'.$this->inlineAsset($this->paths()['css']).'</style>'),
        };
    }

    public function scripts(): HtmlString
    {
        return match ($this->mode()) {
            'none' => new HtmlString(''),
            'public' => new HtmlString(
                '<script src="'.$this->escape($this->assetUrl($this->paths()['echarts'])).'"></script>'."\n".
                '<script src="'.$this->escape($this->assetUrl($this->paths()['alpine'])).'"></script>'
            ),
            default => new HtmlString(
                '<script>'.$this->inlineAsset($this->paths()['echarts']).'</script>'."\n".
                '<script>'.$this->inlineAsset($this->paths()['alpine']).'</script>'
            ),
        };
    }

    private function mode(): string
    {
        /** @var mixed $mode */
        $mode = config('queue-monitor.ui.assets.mode', 'inline');
        $mode = is_string($mode) ? strtolower($mode) : 'inline';

        return in_array($mode, ['inline', 'public', 'none'], true) ? $mode : 'inline';
    }

    /**
     * @return array{css: string, echarts: string, alpine: string}
     */
    private function paths(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = config('queue-monitor.ui.assets.paths', []);

        return [
            'css' => $this->pathValue($configured['css'] ?? null, self::DEFAULT_PATHS['css']),
            'echarts' => $this->pathValue($configured['echarts'] ?? null, self::DEFAULT_PATHS['echarts']),
            'alpine' => $this->pathValue($configured['alpine'] ?? null, self::DEFAULT_PATHS['alpine']),
        ];
    }

    private function pathValue(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? ltrim($value, '/') : $fallback;
    }

    private function assetUrl(string $path): string
    {
        /** @var string|null $configuredUrl */
        $configuredUrl = config('queue-monitor.ui.assets.url');
        $baseUrl = $configuredUrl !== null && $configuredUrl !== ''
            ? $configuredUrl
            : asset('vendor/queue-monitor');

        return rtrim($baseUrl, '/').'/'.$path;
    }

    private function inlineAsset(string $path): string
    {
        $file = $this->packageRoot().'/resources/dist/'.$path;

        if (! is_file($file)) {
            return '';
        }

        $contents = file_get_contents($file);

        return $contents !== false ? $contents : '';
    }

    private function packageRoot(): string
    {
        $fileName = (new ReflectionClass(LaravelQueueMonitorServiceProvider::class))->getFileName();

        return $fileName !== false ? dirname($fileName, 2) : dirname(__DIR__, 2);
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
