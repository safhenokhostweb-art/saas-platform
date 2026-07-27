<?php

namespace App\Services\Modules;

use App\Models\Module;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Phase 17-F — Part 5: site-level dependency resolution. Reads a module's
 * declared dependencies from module.json (via ModuleVersionRegistry — the
 * existing build-time source, no parallel manifest storage) and checks
 * them against what's actually active on a specific site (site_modules) —
 * something ModuleVersionRegistry/PlatformCompatibilityService never do,
 * since they only see the platform's own filesystem, not a customer site.
 *
 * Rules (Phase 17-F — Part 5):
 * 1. A dependency must already be installed and active on the site before
 *    the dependent module can be deployed — this resolver never cascades
 *    an automatic install of the missing dependency, it only rejects.
 * 2. A dependency of type=internal is a platform capability, not
 *    something a site "has installed" via site_modules — it's always
 *    considered satisfied, and is never looked up there.
 * 3. Circular dependencies are rejected.
 * 4. A declared version constraint (e.g. "core-users>=1.0") must be
 *    satisfied by the dependency's site_modules.installed_version.
 */
class ModuleDeploymentDependencyResolver
{
    public function __construct(
        private readonly ModuleVersionRegistry $registry,
    ) {}

    /**
     * @return array{valid: bool, errors: string[]}
     */
    public function resolve(Site $site, Module $module): array
    {
        $errors = [];
        $this->checkModule($site, $module, [], $errors);

        return ['valid' => empty($errors), 'errors' => array_values(array_unique($errors))];
    }

    /**
     * @param  string[]  $visiting  slugs currently being resolved in this call chain (circular-dependency guard)
     * @param  string[]  $errors
     */
    private function checkModule(Site $site, Module $module, array $visiting, array &$errors): void
    {
        if (in_array($module->slug, $visiting, true)) {
            $errors[] = "Circular dependency detected involving module [{$module->slug}].";
            return;
        }
        $visiting[] = $module->slug;

        $deps = $this->registry->dependencies($module->slug)['modules'] ?? [];

        foreach ($deps as $entry) {
            [$depSlug, $constraint] = $this->parseDependency((string) $entry);
            if ($depSlug === '') {
                continue;
            }

            $depModule = Module::where('slug', $depSlug)->first();
            if (!$depModule) {
                $errors[] = "Module [{$module->slug}] depends on [{$depSlug}] which does not exist as a module.";
                continue;
            }

            // Rule 2 — internal modules are platform capabilities, always
            // present; they never get a site_modules row and must never be
            // resolved as if they came through the marketplace/site-install
            // path.
            if ($depModule->type === Module::TYPE_INTERNAL) {
                continue;
            }

            // Rule 3 — walk the manifest-declared graph before the
            // site-installation check below: a true cycle (A needs B, B
            // needs A) can never have either side genuinely installed
            // first (rule 1), so detecting it must not be gated behind
            // "is the dependency already active on this site" — that would
            // make the missing-dependency error mask the cycle it's part
            // of instead of reporting both.
            $this->checkModule($site, $depModule, $visiting, $errors);

            $row = DB::table('site_modules')
                ->where('site_id', $site->id)
                ->where('module_id', $depModule->id)
                ->where('is_active', true)
                ->first();

            if (!$row) {
                $errors[] = "Missing dependency: module [{$module->slug}] requires [{$depSlug}] to already be installed and active on this site.";
                continue;
            }

            if ($constraint !== null && $row->installed_version !== null
                && !$this->satisfiesConstraint($row->installed_version, $constraint)) {
                $errors[] = "Version conflict: [{$module->slug}] requires [{$depSlug}] {$constraint}, but site has [{$row->installed_version}] installed.";
            }
        }
    }

    /**
     * @return array{0: string, 1: ?string} [slug, constraint-or-null]
     */
    private function parseDependency(string $entry): array
    {
        $entry = trim($entry);
        if (preg_match('/^([a-z0-9\-]+)\s*(>=|<=|>|<|=)\s*(.+)$/i', $entry, $m)) {
            return [$m[1], $m[2] . trim($m[3])];
        }

        return [$entry, null];
    }

    private function satisfiesConstraint(string $version, string $constraint): bool
    {
        if (!preg_match('/^(>=|<=|>|<|=)\s*(.+)$/', $constraint, $m)) {
            return true;
        }

        $operator = $m[1] === '=' ? '==' : $m[1];

        return version_compare($version, trim($m[2]), $operator);
    }
}
