import { copyFile, mkdir } from 'node:fs/promises';

await mkdir(new URL('../resources/dist/', import.meta.url), { recursive: true });

await Promise.all([
  copyFile(
    new URL('../node_modules/alpinejs/dist/cdn.min.js', import.meta.url),
    new URL('../resources/dist/alpine.min.js', import.meta.url),
  ),
  copyFile(
    new URL('../node_modules/echarts/dist/echarts.min.js', import.meta.url),
    new URL('../resources/dist/echarts.min.js', import.meta.url),
  ),
]);
