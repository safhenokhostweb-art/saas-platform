<?php
namespace App\Services\ThemeBuilder\QualityGate\Checks;

use App\Models\Extension;
use App\Models\Module;
use App\Models\Theme;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityCheckResult;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use Throwable;

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
 *   - `extension` (Phase 3G-B-5, ADR-045/046/047): resolvable — verifies the
 *     product identity is well-formed, the constraint is exactly "=X.Y.Z"
 *     (the only truthful V1 Extension constraint — ADR-047), the named
 *     Extension product exists, a release for exactly that version exists
 *     (regardless of published/superseded/deprecated status — historical
 *     resolvability, ADR-046/047), and that release's manifest is a valid
 *     ExtensionManifest. Never consults `current_release_id` — the
 *     dependency's own exact version is the sole truth, per ADR-047.
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

    /** The only truthful V1 Extension dependency constraint shape (ADR-047) — never a range/wildcard. */
    private const EXACT_VERSION_CONSTRAINT_PATTERN = '/^=(\d+\.\d+\.\d+)$/';

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
            $versionConstraint = $dependency['version_constraint'] ?? null;

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
                ThemeReleaseDependency::TYPE_EXTENSION => $results[] = $this->checkExtension($ref, $isRequired, $index, $versionConstraint),
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

    private function checkExtension(string $productIdentity, bool $isRequired, int $index, ?string $versionConstraint): ThemeQualityCheckResult
    {
        $fail = fn (string $code, string $message) => $isRequired
            ? ThemeQualityCheckResult::fail(self::CATEGORY, $code, $message, ['index' => $index, 'ref' => $productIdentity])
            : ThemeQualityCheckResult::warn(self::CATEGORY, $code, $message, ['index' => $index, 'ref' => $productIdentity]);

        if (!ExtensionProductIdentity::isValidIdentifier($productIdentity)) {
            return $fail(
                'dependency.extension_malformed_identity',
                "Extension dependency \"{$productIdentity}\" is not a valid \"{vendor-slug}/{package-slug}\" product identity."
            );
        }

        if (!is_string($versionConstraint) || !preg_match(self::EXACT_VERSION_CONSTRAINT_PATTERN, $versionConstraint, $matches)) {
            $shown = $versionConstraint ?? 'null';
            return $fail(
                'dependency.extension_non_exact_constraint',
                "Extension dependency \"{$productIdentity}\" requires an exact version constraint (\"=X.Y.Z\"); got \"{$shown}\"."
            );
        }

        $version = $matches[1];
        $identity = ExtensionProductIdentity::fromString($productIdentity);

        $extension = Extension::where('vendor_slug', $identity->vendorSlug())
            ->where('package_slug', $identity->packageSlug())
            ->first();

        if ($extension === null) {
            return $fail(
                $isRequired ? 'dependency.required_extension_missing' : 'dependency.optional_extension_missing',
                "Extension product \"{$productIdentity}\" does not exist."
            );
        }

        // Any status (published/superseded/deprecated) is a valid historical
        // target — ADR-046/047 historical resolvability, never gated on
        // Extension.current_release_id.
        $release = $extension->releases()->where('version', $version)->first();

        if ($release === null) {
            return $fail(
                $isRequired ? 'dependency.required_extension_release_missing' : 'dependency.optional_extension_release_missing',
                "Extension \"{$productIdentity}\" has no release version \"{$version}\"."
            );
        }

        try {
            ExtensionManifest::fromArray($release->manifest);
        } catch (Throwable) {
            return $fail(
                'dependency.extension_invalid_manifest',
                "Extension \"{$productIdentity}\" release \"{$version}\" has an invalid manifest."
            );
        }

        return ThemeQualityCheckResult::pass(
            self::CATEGORY,
            'dependency.extension_resolved',
            "Extension \"{$productIdentity}\" release \"{$version}\" exists and is valid.",
            ['index' => $index, 'ref' => $productIdentity]
        );
    }
}
