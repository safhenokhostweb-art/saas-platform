<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Creates and reads immutable deployment manifests.
 *
 * Stored at: storage/app/releases/{uuid}.json
 * Each manifest captures the complete state of a release.
 */
class ReleaseManifestManager
{
    private string $storageDir;

    public function __construct(private ModuleVersionRegistry $moduleRegistry)
    {
        $this->storageDir = storage_path('app/releases');
    }

    public function create(string $uuid, array $options = []): array
    {
        $manifest = [
            'deployment_uuid'   => $uuid,
            'environment'       => config('app.env', 'production'),
            'git_commit'        => $this->resolveGitCommit(),
            'git_branch'        => $this->resolveGitBranch(),
            'package_versions'  => $this->collectPackageVersions(),
            'module_versions'   => $this->collectModuleVersions(),
            'migration_batch'   => $this->currentMigrationBatch(),
            'asset_checksum'    => $this->viteManifestChecksum(),
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'platform_version'  => config('saas-platform.platform_version', '1.0.0'),
            'deployed_by'       => $options['deployed_by'] ?? (gethostname() . ':' . getmypid()),
            'released_at'       => now()->toIso8601String(),
            'rollback_target'   => $options['rollback_target'] ?? $this->findPreviousManifestUuid(),
            'checksum'          => null, // computed after building
        ];

        // Self-checksum (excludes the checksum field itself)
        $manifest['checksum'] = hash('sha256', json_encode(array_except_key($manifest, 'checksum')));

        $this->write($uuid, $manifest);
        return $manifest;
    }

    public function read(string $uuid): ?array
    {
        $path = $this->path($uuid);
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function validate(string $uuid): array
    {
        $manifest = $this->read($uuid);

        if (!$manifest) {
            return ['valid' => false, 'reason' => 'Manifest file not found'];
        }

        $stored   = $manifest['checksum'] ?? null;
        $computed = hash('sha256', json_encode(array_filter($manifest, fn($k) => $k !== 'checksum', ARRAY_FILTER_USE_KEY)));

        if ($stored !== $computed) {
            return ['valid' => false, 'reason' => 'Checksum mismatch — manifest may be tampered'];
        }

        return ['valid' => true, 'manifest' => $manifest];
    }

    public function all(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $manifests = [];
        foreach (glob($this->storageDir . '/*.json') ?: [] as $file) {
            $uuid = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $manifests[$uuid] = $data;
            }
        }

        // Sort newest first
        uasort($manifests, fn($a, $b) => strcmp($b['released_at'] ?? '', $a['released_at'] ?? ''));
        return $manifests;
    }

    public function latest(): ?array
    {
        $all = $this->all();
        return !empty($all) ? reset($all) : null;
    }

    public function diff(string $uuidA, string $uuidB): array
    {
        $a = $this->read($uuidA);
        $b = $this->read($uuidB);

        if (!$a || !$b) {
            return ['error' => 'One or both manifests not found'];
        }

        return [
            'module_version_changes' => $this->diffArrays($a['module_versions'] ?? [], $b['module_versions'] ?? []),
            'migration_batch_change'  => [
                'from' => $a['migration_batch'] ?? null,
                'to'   => $b['migration_batch'] ?? null,
            ],
            'git_commit_change' => [
                'from' => $a['git_commit'] ?? null,
                'to'   => $b['git_commit'] ?? null,
            ],
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function write(string $uuid, array $manifest): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        file_put_contents(
            $this->path($uuid),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function path(string $uuid): string
    {
        return $this->storageDir . "/{$uuid}.json";
    }

    private function resolveGitCommit(): ?string
    {
        try {
            $result = shell_exec('git rev-parse HEAD 2>/dev/null');
            return $result ? trim($result) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveGitBranch(): ?string
    {
        try {
            $result = shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null');
            return $result ? trim($result) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function collectModuleVersions(): array
    {
        $versions = [];
        foreach ($this->moduleRegistry->all() as $slug => $data) {
            $versions[$slug] = $data['version'] ?? '0.0.0';
        }
        return $versions;
    }

    private function collectPackageVersions(): array
    {
        $lockFile = base_path('composer.lock');
        if (!file_exists($lockFile)) {
            return [];
        }

        $lock     = json_decode(file_get_contents($lockFile), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        $versions = [];

        foreach ($packages as $pkg) {
            if (isset($pkg['name'], $pkg['version'])) {
                $versions[$pkg['name']] = $pkg['version'];
            }
        }

        return $versions;
    }

    private function currentMigrationBatch(): ?int
    {
        try {
            return (int) \Illuminate\Support\Facades\DB::table('migrations')->max('batch');
        } catch (\Throwable) {
            return null;
        }
    }

    private function viteManifestChecksum(): ?string
    {
        $paths = [
            public_path('build/manifest.json'),
            public_path('build/.vite/manifest.json'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return hash_file('sha256', $path);
            }
        }

        return null;
    }

    private function findPreviousManifestUuid(): ?string
    {
        $all = $this->all();
        return !empty($all) ? array_key_first($all) : null;
    }

    private function diffArrays(array $a, array $b): array
    {
        $changes = [];
        $keys    = array_unique(array_merge(array_keys($a), array_keys($b)));

        foreach ($keys as $key) {
            $from = $a[$key] ?? null;
            $to   = $b[$key] ?? null;
            if ($from !== $to) {
                $changes[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }
}

// Tiny helper to exclude a key from an array without modifying the original
function array_except_key(array $array, string $key): array
{
    return array_filter($array, fn($k) => $k !== $key, ARRAY_FILTER_USE_KEY);
}
