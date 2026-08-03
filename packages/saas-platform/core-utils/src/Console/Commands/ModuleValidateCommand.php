<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Phase 17-G â€” Part 4: validates a module's source tree against the
 * official Module Package Contract (Phase 17-F,
 * docs/architecture/module-package-contract.md) â€” module.json content via
 * ModuleManifestSchema, plus a folder-structure check.
 *
 * Deliberately does NOT reuse App\Services\Modules\ModulePackageValidator:
 * that class validates a built ZIP against an existing module_releases DB
 * row (App\Models, host-app-only) â€” at module:validate time there is
 * usually no release and no ZIP yet, only source on disk. The folder check
 * here mirrors ModulePackageValidator::checkStructure()'s rules (case-
 * insensitive, non-blocking for optional dirs) so a module that passes
 * here is expected to also pass the real package validator once built.
 *
 * Also distinct from module:build (Phase 6.98): that command enforces
 * platform-governance rules (authority/tenant-segregation regex scans,
 * admin/site view split) unrelated to the packaging contract. The two are
 * complementary, not redundant â€” this phase does not merge them.
 */
class ModuleValidateCommand extends Command
{
    protected $signature = 'module:validate {slug : The module slug to validate}';

    protected $description = 'Validate a module against the official Module Package Contract';

    protected const OPTIONAL_DIRS = ['database', 'resources', 'routes'];

    public function handle(ModuleManifestSchema $schema, ModuleVersionRegistry $registry): int
    {
        $slug = $this->argument('slug');
        $path = base_path("packages/saas-platform/modules/{$slug}");

        if (!is_dir($path)) {
            $this->error("Module [{$slug}] not found at packages/saas-platform/modules/{$slug}.");
            return self::FAILURE;
        }

        $manifestPath = "{$path}/module.json";
        if (!file_exists($manifestPath)) {
            $this->reportValidationErrors(['module.json does not exist.']);
            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            $this->reportValidationErrors(['module.json is not valid JSON.']);
            return self::FAILURE;
        }

        $result = $schema->validate($manifest);
        $structureWarnings = $this->checkStructure($path, $manifest);

        if (!$result['valid']) {
            $this->reportValidationErrors($result['errors']);
            return self::FAILURE;
        }

        $registry->flush(); // pick up a module.json written moments ago in this same process

        $this->info("SUCCESS: Module {$slug} is valid.");
        foreach (array_merge($result['warnings'], $structureWarnings) as $warning) {
            $this->warn("  âڑ  {$warning}");
        }

        return self::SUCCESS;
    }

    /**
     * @return string[] warnings â€” never blocks (matches
     * ModulePackageValidator::checkStructure()'s exact policy from 17-F)
     */
    private function checkStructure(string $path, array $manifest): array
    {
        $warnings = [];

        if (!is_dir("{$path}/src")) {
            $warnings[] = 'No src/ directory â€” module has no application code.';
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

            if ($declaresCapability && !$this->hasDirCaseInsensitive($path, $dir)) {
                $warnings[] = "module.json declares a {$dir}-related publish tag but no {$dir}/ directory exists.";
            }
        }

        return $warnings;
    }

    private function hasDirCaseInsensitive(string $path, string $dir): bool
    {
        foreach (glob("{$path}/*", GLOB_ONLYDIR) ?: [] as $found) {
            if (strtolower(basename($found)) === strtolower($dir)) {
                return true;
            }
        }
        return false;
    }

    private function reportValidationErrors(array $errors): void
    {
        $this->error('FAIL:');
        foreach ($errors as $error) {
            $this->line("  {$error}");
        }
    }
}

