<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ModuleFeatureService
{
    private const CACHE_TTL = 3600; // 60 minutes

    /**
     * Get module features from JSON file with caching.
     */
    public function getModuleFeatures(string $moduleSlug): ?array
    {
        $cacheKey = "module_features_{$moduleSlug}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($moduleSlug) {
            $path = base_path("module-features/{$moduleSlug}.json");

            if (!File::exists($path)) {
                return null;
            }

            $content = File::get($path);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $data;
        });
    }

    /**
     * Get all module slugs with their JSON files.
     */
    public function getAllModuleSlugs(): array
    {
        $files = File::files(base_path('module-features'));

        return collect($files)
            ->filter(fn ($file) => $file->getExtension() === 'json' && $file->getFilename() !== 'context-backup.json')
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->values()
            ->toArray();
    }

    /**
     * Clear cache for a specific module.
     */
    public function clearCache(string $moduleSlug): void
    {
        Cache::forget("module_features_{$moduleSlug}");
    }

    /**
     * Clear all module features caches.
     */
    public function clearAllCache(): void
    {
        foreach ($this->getAllModuleSlugs() as $slug) {
            $this->clearCache($slug);
        }
    }
}