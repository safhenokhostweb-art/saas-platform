<?php

namespace App\Services\Modules;

use App\Models\Module;
use App\Models\ModuleRelease;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use ZipArchive;

/**
 * Phase 17-F — Part 3: validates a built module package ZIP before it is
 * allowed to become deployable. Invoked exactly once, from
 * ModulePackageBuilderService::build() — right after the ZIP is written,
 * before package_sha256/package_path are persisted onto the module_releases
 * row. A release that fails validation never gets those columns set, and
 * ModuleDeploymentRequestService already refuses to deploy any release with
 * an empty package_sha256 — so gating here is sufficient; nothing re-opens
 * the ZIP again at deploy time on every single install/upgrade call.
 *
 * Deliberately does not duplicate ModuleManifestSchema's field rules — it
 * delegates manifest-shape checking there and only adds the checks that
 * genuinely require the ZIP and the database: does the package actually
 * contain module.json, does its content match the module_releases row it's
 * being attached to, does the expected folder structure exist, is the
 * checksum on disk what module_releases claims.
 */
class ModulePackageValidator
{
    /** Folders a distributable module may have; module.json + src/ are the only universal requirements. */
    protected const OPTIONAL_DIRS = ['database', 'resources', 'routes'];

    public function __construct(
        private readonly ModuleManifestSchema $manifestSchema,
    ) {}

    /**
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    public function validate(ModuleRelease $release): array
    {
        $errors = [];
        $warnings = [];

        if (empty($release->package_path) || !is_file($release->package_path)) {
            $errors[] = "Package file not found at [{$release->package_path}].";
            return $this->result($errors, $warnings);
        }

        $zip = new ZipArchive();
        if ($zip->open($release->package_path) !== true) {
            $errors[] = "Unable to open package ZIP at [{$release->package_path}].";
            return $this->result($errors, $warnings);
        }

        try {
            $manifestJson = $this->readEntry($zip, 'module.json');
            if ($manifestJson === null) {
                $errors[] = 'Package does not contain a module.json manifest at its root.';
                return $this->result($errors, $warnings);
            }

            $manifest = json_decode($manifestJson, true);
            if (!is_array($manifest)) {
                $errors[] = 'module.json inside the package is not valid JSON.';
                return $this->result($errors, $warnings);
            }

            $schemaResult = $this->manifestSchema->validate($manifest);
            array_push($errors, ...$schemaResult['errors']);
            array_push($warnings, ...$schemaResult['warnings']);

            if (!empty($errors)) {
                // Cross-checks below all assume module_slug/version/type exist.
                return $this->result($errors, $warnings);
            }

            $this->checkMatchesRelease($manifest, $release, $errors);
            $this->checkStructure($zip, $manifest, $warnings);
            $this->checkDependencyReferences($manifest, $errors);
            $this->checkChecksum($release, $errors);
        } finally {
            $zip->close();
        }

        return $this->result($errors, $warnings);
    }

    private function checkMatchesRelease(array $manifest, ModuleRelease $release, array &$errors): void
    {
        $module = $release->module ?? Module::find($release->module_id);
        if (!$module) {
            $errors[] = "module_releases row references module_id [{$release->module_id}] which no longer exists.";
            return;
        }

        if ((string) $manifest['module_slug'] !== (string) $module->slug) {
            $errors[] = "module.json module_slug ({$manifest['module_slug']}) does not match the module this release belongs to ({$module->slug}).";
        }

        if ((string) $manifest['version'] !== (string) $release->version) {
            $errors[] = "module.json version ({$manifest['version']}) does not match module_releases.version ({$release->version}).";
        }

        if (strtolower((string) $manifest['type']) !== strtolower((string) $module->type)) {
            $errors[] = "module.json type ({$manifest['type']}) does not match modules.type ({$module->type}).";
        }
    }

    /**
     * Case-insensitive, capability-based per Part 7: module.json + src/ are
     * required; database/resources/routes are only ever a warning, never a
     * hard failure — education/tickets use PascalCase (Database/Resources/
     * Routes), forms/motion-graphics use lowercase, and a module with no
     * migrations/no UI legitimately has no reason to carry an empty folder.
     */
    private function checkStructure(ZipArchive $zip, array $manifest, array &$warnings): void
    {
        if (!$this->hasTopLevelDir($zip, 'src')) {
            $warnings[] = 'Package has no src/ directory — module has no application code.';
        }

        $publishTags = array_merge(
            $manifest['install']['publishes'] ?? [],
            $manifest['publish_tags'] ?? []
        );

        foreach (self::OPTIONAL_DIRS as $dir) {
            $declaresCapability = (bool) array_filter(
                $publishTags,
                fn ($tag) => str_contains(strtolower((string) $tag), $dir === 'database' ? 'migration' : $dir)
            );

            if ($declaresCapability && !$this->hasTopLevelDir($zip, $dir)) {
                $warnings[] = "module.json declares a {$dir}-related publish tag but the package has no {$dir}/ directory.";
            }
        }
    }

    /**
     * Site-agnostic sanity check only: does the declared module dependency
     * refer to a module that actually exists at all. Whether it's
     * installed on a specific site is
     * ModuleDeploymentDependencyResolver's job at deploy time.
     */
    private function checkDependencyReferences(array $manifest, array &$errors): void
    {
        $moduleDeps = $manifest['dependencies']['modules'] ?? [];

        foreach ($moduleDeps as $entry) {
            $slug = preg_split('/\s*(>=|<=|>|<|=|\^|~)\s*/', trim((string) $entry))[0];
            if ($slug === '') {
                continue;
            }
            if (!Module::where('slug', $slug)->exists()) {
                $errors[] = "module.json declares a dependency on [{$slug}] which does not exist as a module.";
            }
        }
    }

    private function checkChecksum(ModuleRelease $release, array &$errors): void
    {
        if (empty($release->package_sha256)) {
            return;
        }

        $actual = hash_file('sha256', $release->package_path);
        if (!hash_equals(strtolower($release->package_sha256), strtolower((string) $actual))) {
            $errors[] = 'Package on disk does not match module_releases.package_sha256.';
        }
    }

    private function hasTopLevelDir(ZipArchive $zip, string $dir): bool
    {
        $needle = strtolower($dir) . '/';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = strtolower((string) $zip->getNameIndex($i));
            if (str_starts_with($name, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function readEntry(ZipArchive $zip, string $name): ?string
    {
        $content = $zip->getFromName($name);
        return $content === false ? null : $content;
    }

    /**
     * @return array{valid: bool, errors: string[], warnings: string[]}
     */
    private function result(array $errors, array $warnings): array
    {
        return ['valid' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }
}
