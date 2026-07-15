<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support;

use RuntimeException;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionAssetStorageContract;

final class InMemoryMotionAssetStorage implements MotionAssetStorageContract
{
    private array $files = [];

    public function put(string $path, string $contents, string $mime): array
    {
        $this->files[$path] = $contents;

        return ['path' => $path, 'url' => '/'.$path, 'mime' => $mime, 'size' => strlen($contents), 'sha256' => hash('sha256', $contents)];
    }

    public function get(string $path): string
    {
        return $this->files[$path] ?? throw new RuntimeException('Asset not found.');
    }

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function deletePrefix(string $prefix): void
    {
        foreach (array_keys($this->files) as $path) {
            if (str_starts_with($path, $prefix)) {
                unset($this->files[$path]);
            }
        }
    }
}
