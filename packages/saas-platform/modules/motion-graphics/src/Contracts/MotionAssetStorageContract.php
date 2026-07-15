<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts;

interface MotionAssetStorageContract
{
    public function put(string $path, string $contents, string $mime): array;

    public function get(string $path): string;

    public function exists(string $path): bool;

    public function deletePrefix(string $prefix): void;
}
