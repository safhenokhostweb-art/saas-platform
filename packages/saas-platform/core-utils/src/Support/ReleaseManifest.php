<?php

namespace SaaSPlatform\CoreUtils\Support;

/**
 * PHASE 6.98 — TASK 3: Canonical Release Manifest System
 *
 * Generates, stores, and reads canonical release manifests at:
 *   storage/app/modules/releases/{module}.json
 *
 * Each manifest is the single source of truth for a released
 * module version, consumed by ModuleUpdateOrchestrator and
 * PlatformCompatibilityService during update validation.
 */
class ReleaseManifest
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('app/modules/releases');
    }

    /**
     * Generate and persist a release manifest for a module.
     *
     * @param  string $module   Module slug
     * @param  array  $moduleData  Data from ModuleVersionRegistry::get()
     * @return array  The generated manifest
     */
    public function generate(string $module, array $moduleData): array
    {
        $manifest = $this->buildManifest($module, $moduleData);
        $this->write($module, $manifest);
        return $manifest;
    }

    /**
     * Read the stored manifest for a module.
     * Returns null if no manifest exists for that module.
     */
    public function read(string $module): ?array
    {
        $path = $this->manifestPath($module);
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Check whether a release manifest exists for a module.
     */
    public function exists(string $module): bool
    {
        return file_exists($this->manifestPath($module));
    }

    /**
     * List all modules that have a release manifest.
     *
     * @return string[]
     */
    public function all(): array
    {
        if (!is_dir($this->storagePath)) {
            return [];
        }

        $slugs = [];
        foreach (glob($this->storagePath . '/*.json') ?: [] as $file) {
            $slugs[] = basename($file, '.json');
        }
        return $slugs;
    }

    /**
     * Delete the manifest for a module (used during rollback cleanup).
     */
    public function delete(string $module): void
    {
        $path = $this->manifestPath($module);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    protected function buildManifest(string $module, array $data): array
    {
        $modulePath = $data['_path'] ?? null;

        return [
            'module'         => $module,
            'version'        => $data['version']        ?? '0.0.0',
            'schema_version' => $data['schema_version'] ?? '1',
            'released_at'    => now()->toIso8601String(),
            'compatibility'  => $data['compatibility']  ?? [
                'php'          => '^8.1',
                'laravel'      => '^10.0',
                'saas-platform' => '>=1.0.0',
            ],
            'migrations'     => $this->discoverMigrations($modulePath),
            'publish_tags'   => $this->discoverPublishTags($modulePath),
            'checksums'      => $this->computeChecksums($modulePath),
            'checksum'       => $this->computeModuleChecksum($modulePath),
            'requires'       => $data['dependencies']['packages'] ?? [],
            'replaces'       => [],
            'conflicts'      => [],
            'source'         => $data['_source'] ?? 'unknown',
        ];
    }

    protected function discoverMigrations(?string $modulePath): array
    {
        if (!$modulePath) {
            return [];
        }

        $dirs = [
            $modulePath . '/database/migrations',
            $modulePath . '/Database/Migrations',
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.php') ?: [];
                return array_map('basename', $files);
            }
        }

        return [];
    }

    protected function discoverPublishTags(?string $modulePath): array
    {
        if (!$modulePath) {
            return [];
        }

        $providerFiles = glob($modulePath . '/src/Providers/*.php') ?: [];
        if (empty($providerFiles)) {
            return [];
        }

        $content = file_get_contents($providerFiles[0]);
        preg_match_all('/\$this->publishes\(.*?\[.*?\'([^\']+)\'\s*\]/ms', $content, $matches);

        // Also capture tag strings from publishes([...], 'tag-name') pattern
        preg_match_all('/\$this->publishes\([^,]+,\s*[\'"]([^\'"]+)[\'"]\)/s', $content, $tagMatches);

        return array_unique(array_filter(array_merge(
            $matches[1]  ?? [],
            $tagMatches[1] ?? []
        )));
    }

    protected function computeChecksums(?string $modulePath): array
    {
        if (!$modulePath) {
            return [];
        }

        $checksums = [];
        $keyFiles  = [
            'composer.json',
            'module.json',
            'src/Providers/' . $this->guessProviderName($modulePath) . '.php',
        ];

        foreach ($keyFiles as $relative) {
            $full = $modulePath . '/' . $relative;
            if (file_exists($full)) {
                $checksums[$relative] = hash_file('sha256', $full);
            }
        }

        return $checksums;
    }

    protected function computeModuleChecksum(?string $modulePath): string
    {
        if (!$modulePath || !is_dir($modulePath)) {
            return '';
        }

        // Hash composite of composer.json + module.json
        $composite = '';
        foreach (['composer.json', 'module.json'] as $file) {
            $path = $modulePath . '/' . $file;
            if (file_exists($path)) {
                $composite .= file_get_contents($path);
            }
        }

        return hash('sha256', $composite);
    }

    protected function guessProviderName(string $modulePath): string
    {
        $providers = glob($modulePath . '/src/Providers/*.php') ?: [];
        if (!empty($providers)) {
            return basename($providers[0], '.php');
        }
        return 'ServiceProvider';
    }

    protected function write(string $module, array $manifest): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        file_put_contents(
            $this->manifestPath($module),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    protected function manifestPath(string $module): string
    {
        return $this->storagePath . "/{$module}.json";
    }
}
