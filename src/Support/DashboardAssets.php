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
            'inline' => new HtmlString('<style>'.$this->inlineAsset($this->paths()['css']).'</style>'),
            'public' => new HtmlString('<link rel="stylesheet" href="'.$this->escape($this->assetUrl($this->paths()['css'])).'">'),
            default => new HtmlString('<link rel="stylesheet" href="'.$this->escape($this->servedUrl('css')).'">'),
        };
    }

    public function scripts(): HtmlString
    {
        return match ($this->mode()) {
            'none' => new HtmlString(''),
            'inline' => new HtmlString(
                '<script>'.$this->inlineAsset($this->paths()['echarts']).'</script>'."\n".
                '<script>'.$this->inlineAsset($this->paths()['alpine']).'</script>'
            ),
            'public' => new HtmlString(
                '<script src="'.$this->escape($this->assetUrl($this->paths()['echarts'])).'"></script>'."\n".
                '<script src="'.$this->escape($this->assetUrl($this->paths()['alpine'])).'"></script>'
            ),
            default => new HtmlString(
                '<script src="'.$this->escape($this->servedUrl('echarts')).'"></script>'."\n".
                '<script src="'.$this->escape($this->servedUrl('alpine')).'"></script>'
            ),
        };
    }

    /**
     * Resolve a bundle file requested through the package asset route: the
     * contents and its content type, or null if the name is not a known bundle
     * file (so the route can 404 without exposing arbitrary paths).
     *
     * @return array{0: string, 1: string}|null
     */
    public function bundledAsset(string $file): ?array
    {
        $paths = $this->paths();
        $type = match ($file) {
            $paths['css'] => 'text/css',
            $paths['echarts'], $paths['alpine'] => 'text/javascript',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $contents = $this->inlineAsset($file);

        if ($contents === '') {
            return null;
        }

        return [$contents, $type];
    }

    private function mode(): string
    {
        /** @var mixed $mode */
        $mode = config('queue-monitor.ui.assets.mode', 'served');
        $mode = is_string($mode) ? strtolower($mode) : 'served';

        return in_array($mode, ['served', 'inline', 'public', 'none'], true) ? $mode : 'served';
    }

    /**
     * URL to the package asset route for a bundle key, with a content-hash
     * version so an upgraded bundle busts the immutable browser cache.
     */
    private function servedUrl(string $key): string
    {
        $file = $this->paths()[$key];

        return route('queue-monitor.asset', ['file' => $file]).'?v='.$this->assetVersion($file);
    }

    private function assetVersion(string $file): string
    {
        $path = $this->packageRoot().'/resources/dist/'.$file;

        $hash = is_file($path) ? md5_file($path) : false;

        return $hash !== false ? substr($hash, 0, 8) : 'dev';
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
