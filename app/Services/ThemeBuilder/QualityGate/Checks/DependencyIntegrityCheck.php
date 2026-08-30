<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Module;
use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;

/**
 * Validates the dependency set a release would carry — structurally (shape
 * is already enforced by ThemeReleaseService::assertValidDependencies() at
 * write time, so this check does not repeat that; it goes further, into
 * resolvability) and, where the architecture actually supports it,
 * existence/compatibility.
 *
 * Resolution boundary, stated honestly rather than assumed:
 *   - `module`: resolvable today — Module is a platform-level catalog model
 *     with no tenant/site scoping, so existence can be checked directly.
 *   - `motion_asset`/`motion_pack`: NOT resolved here. The only existing
 *     resolver (MotionAssetResolverAdapter::resolveCanonical()) requires a
 *     site/actor authorization context that a platform-level release gate
 *     does not have and must not fabricate — forcing a fake site context
 *     through a security-authorization boundary to satisfy this check would
 *     be a worse defect than not checking at all. Reported as `unverified`,
 *     never silently upgraded to a pass.
 *   - any other/future type (icon_asset, icon_pack, block_pack,
 *     design_system, ...): `unverified` — no resolver exists yet. This is
 *     the intended extension point: a future phase adds one more branch
 *     here (or, better, a small resolver registry) without touching the
 *     rest of the gate.
 */
class DependencyIntegrityCheck implements ThemeQualityCheck
{
    private const CATEGORY = 'dependency_integrity';

    public function run(Theme $theme, string $version, ?array $dependencies): array
    {
        if (empty($dependencies)) {
            return [ThemeQualityCheckResult::pass(self::CATEGORY, 'dependency.none_declared', 'No dependencies declared for this release.')];
        }

        $results = [];

        foreach ($dependencies as $index => $dependency) {
            $type = $dependency['type'] ?? '';
            $ref = $dependency['ref'] ?? '';
            $isRequired = (bool) ($dependency['is_required'] ?? false);

            if ($type === '' || $ref === '') {
                $results[] = ThemeQualityCheckResult::fail(
                    self::CATEGORY,
                    'dependency.malformed',
                    "Dependency entry #{$index} is missing a type or ref.",
                    ['index' => $index]
                );
                continue;
            }

            match ($type) {
                'module' => $results[] = $this->checkModule($ref, $isRequired, $index),
                default => $results[] = ThemeQualityCheckResult::unverified(
                    self::CATEGORY,
                    'dependency.resolution_unavailable',
                    "No existence resolver is wired for dependency type \"{$type}\" yet — structural shape is valid, existence is not verified.",
                    ['index' => $index, 'type' => $type, 'ref' => $ref]
                ),
            };
        }

        return $results;
    }

    private function checkModule(string $slug, bool $isRequired, int $index): ThemeQualityCheckResult
    {
        $exists = Module::where('slug', $slug)->exists();

        if ($exists) {
            return ThemeQualityCheckResult::pass(
                self::CATEGORY,
                'dependency.module_resolved',
                "Module \"{$slug}\" exists in the platform catalog.",
                ['index' => $index, 'ref' => $slug]
            );
        }

        $message = "Module \"{$slug}\" does not exist in the platform catalog.";

        return $isRequired
            ? ThemeQualityCheckResult::fail(self::CATEGORY, 'dependency.required_module_missing', $message, ['index' => $index, 'ref' => $slug])
            : ThemeQualityCheckResult::warn(self::CATEGORY, 'dependency.optional_module_missing', $message, ['index' => $index, 'ref' => $slug]);
    }
}
