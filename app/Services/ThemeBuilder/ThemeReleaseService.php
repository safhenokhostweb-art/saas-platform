<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRelease;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use App\Services\ThemeBuilder\Dependency\ExtensionVersionSelectionResolver;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionBlockTypeExtractor;
use App\Services\ThemeBuilder\Dependency\ThemeExtensionDependencyExtractor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 1a — Theme Product Release Ledger Foundation.
 *
 * The sole writer of theme_releases. Mirrors the guarantees
 * ModuleReleaseProducerAdapter already gives Modules: transactional,
 * monotonic-version-enforced, append-only, single designated writer.
 *
 * Release-readiness is enforced here, not in ThemeBuilderService or any
 * controller — this is the one place every caller of release() (the publish
 * flow today, a future backfill/import command tomorrow) is guaranteed to
 * go through the same gate. See ThemeReleaseReadinessCheck for how future
 * commercial gates (dependencies, rendering, accessibility, performance)
 * are added here without scattering validation elsewhere.
 */
class ThemeReleaseService
{
    /** @var ThemeReleaseReadinessCheck[] */
    private array $readinessChecks;

    /**
     * @param ThemeReleaseReadinessCheck[]|null $readinessChecks Defaults to
     *   the current minimum gate (authored pages). Pass an explicit list to
     *   layer in future checks without changing this class's body.
     */
    public function __construct(?array $readinessChecks = null)
    {
        $this->readinessChecks = $readinessChecks ?? [new PagesAuthoredReadinessCheck()];
    }

    /**
     * Freeze the theme's current live content as a new immutable release and
     * make it the theme's current release. Structured dependency rows
     * (Phase 1e) are written in the same transaction via writeDependencyRows().
     *
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null $dependencies
     */
    public function release(
        Theme $theme,
        string $version,
        string $changelog,
        ?string $releaseNotes = null,
        ?array $compatibility = null,
        ?array $dependencies = null,
        ?int $publishedBy = null
    ): ThemeRelease {
        foreach ($this->readinessChecks as $check) {
            $check->check($theme);
        }

        $this->assertValidSemver($version);
        $this->assertValidCompatibility($compatibility);

        $dependencies = $this->deriveDependenciesForRelease($theme, $dependencies);

        $this->assertValidDependencies($dependencies);

        $latest = $this->latestRelease($theme);

        if ($latest && version_compare($version, $latest->version, '<=')) {
            throw new InvalidArgumentException(
                "Release version {$version} must be strictly greater than the latest release ({$latest->version})."
            );
        }

        return DB::transaction(function () use ($theme, $version, $changelog, $releaseNotes, $compatibility, $dependencies, $publishedBy) {
            // Supersede whatever is currently PUBLISHED via the pointer — not
            // necessarily the highest-ever version, since a prior rollback()
            // may have made an older release current again while a newer,
            // already-superseded one still has a higher version number.
            // currentRelease() (method, not the cached property) so a $theme
            // instance reused across calls always sees the live pointer.
            $current = $theme->currentRelease()->first();
            if ($current && $current->status === ThemeRelease::STATUS_PUBLISHED) {
                $current->update(['status' => ThemeRelease::STATUS_SUPERSEDED]);
            }

            $release = $theme->releases()->create([
                'version' => $version,
                'changelog' => $changelog,
                'release_notes' => $releaseNotes,
                'status' => ThemeRelease::STATUS_PUBLISHED,
                'compatibility' => $compatibility,
                'dependencies' => $dependencies,
                // Every master-theme-owned, render-affecting field on Theme —
                // the full set needed to recreate this release. Deliberately
                // excludes catalog/marketplace-only fields (name, slug,
                // description, thumbnail, preview_image, category,
                // is_marketplace, marketplace_price, license_type,
                // marketplace_badge, installs_count) per the contract's §3.7
                // "marketplace fields live on the catalog record, never the
                // release record" — those describe the listing, not the
                // rendered product, and changing them must not require a new
                // release. Never include site-scoped customer data: this
                // snapshot is only ever built from $theme, which for a
                // customer-instance Theme row is that instance's own product
                // content, never another tenant's.
                'snapshot' => [
                    'header_blocks' => $theme->header_blocks,
                    'footer_blocks' => $theme->footer_blocks,
                    'header_variants' => $theme->getHeaderVariants(),
                    'footer_variants' => $theme->getFooterVariants(),
                    'pages' => $theme->pages,
                    'global_styles' => $theme->global_styles,
                    'settings' => $theme->settings,
                ],
                'published_by' => $publishedBy,
                'published_at' => now(),
            ]);

            $this->writeDependencyRows($release, $dependencies ?? []);

            $theme->current_release_id = $release->id;
            $theme->save();

            return $release->fresh();
        });
    }

    /**
     * Phase 3G-B-5 — the ONE place Extension usage inside a Theme is turned
     * into commercial dependency declarations, shared by both release() (so
     * the sole writer always guarantees this regardless of caller) and
     * ThemeCommercialQualityGate::publishIfCompliant() (so the gate
     * evaluates the SAME final dependency set that will actually be
     * persisted, rather than a stale pre-Extension candidate array — the
     * gate runs BEFORE release() is ever called, so without this shared
     * call the gate would silently never see Extension dependencies at all).
     *
     * Reads header_blocks/footer_blocks/pages/settings.extension_selections
     * directly off the SAME live $theme instance release() goes on to freeze
     * into the snapshot — nothing re-fetches Theme state in between, so
     * there is no TOCTOU window between what this derives and what gets
     * persisted.
     *
     * A Theme with zero ext:* usage returns $manualDependencies completely
     * unchanged (including a literal null) — existing non-Extension release
     * behavior is byte-identical to before this phase.
     *
     * Fails closed (InvalidArgumentException, before any DB write) on:
     *   - any unresolved/ambiguous ext:* element ownership
     *   - any used Extension product with no truthful version selection
     *   - any invalid version selection (nonexistent release, or a release
     *     whose manifest doesn't provide a used element)
     *   - a manually-supplied "extension" dependency that conflicts with a
     *     derived one (different version_constraint or is_required)
     *
     * A manually-supplied "extension" dependency identical to the derived
     * one is deduplicated safely (kept once, never a duplicate-key error).
     * Every other existing dependency type passes through completely
     * untouched — this method only ever adds/merges "extension" entries.
     *
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null $manualDependencies
     * @return array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null
     */
    public function deriveDependenciesForRelease(Theme $theme, ?array $manualDependencies): ?array
    {
        $extractor = new ThemeExtensionDependencyExtractor(
            new ThemeExtensionBlockTypeExtractor(),
            new ExtensionElementOwnershipResolver(),
            new ExtensionVersionSelectionResolver(),
        );

        $result = $extractor->extractFromSnapshot(
            $theme->header_blocks ?? [],
            $theme->footer_blocks ?? [],
            $theme->pages ?? [],
            $theme->settings['extension_selections'] ?? [],
        );

        if ($result->hasUnresolved()) {
            throw new InvalidArgumentException(
                'Theme uses Extension element type(s) with no owning Extension product: '
                . implode(', ', $result->unresolvedElementTypes) . '.'
            );
        }

        if ($result->hasAmbiguous()) {
            $details = [];
            foreach ($result->ambiguousElementTypes as $type => $candidates) {
                $details[] = "{$type} (claimed by: " . implode(', ', $candidates) . ')';
            }
            throw new InvalidArgumentException(
                'Theme uses Extension element type(s) with ambiguous ownership: ' . implode('; ', $details) . '.'
            );
        }

        if ($result->hasUnresolvedVersionSelections()) {
            throw new InvalidArgumentException(
                'Theme uses Extension product(s) with no author-selected release version: '
                . implode(', ', $result->unresolvedVersionSelections) . '.'
            );
        }

        if ($result->hasInvalidVersionSelections()) {
            $details = [];
            foreach ($result->invalidVersionSelections as $product => $reason) {
                $details[] = "{$product}: {$reason}";
            }
            throw new InvalidArgumentException(
                'Theme has invalid Extension version selection(s): ' . implode('; ', $details) . '.'
            );
        }

        if ($result->resolvedDependencies === []) {
            return $manualDependencies;
        }

        $merged = $manualDependencies ?? [];

        foreach ($result->resolvedDependencies as $proposal) {
            $merged = $this->mergeExtensionDependency($merged, $proposal->toReleaseDependencyArray());
        }

        return $merged;
    }

    /**
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}> $dependencies
     * @param array{type: string, ref: string, version_constraint: ?string, is_required: bool, metadata: array} $derived
     * @return array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>
     */
    private function mergeExtensionDependency(array $dependencies, array $derived): array
    {
        foreach ($dependencies as $existing) {
            if (($existing['type'] ?? null) !== $derived['type'] || ($existing['ref'] ?? null) !== $derived['ref']) {
                continue;
            }

            $sameConstraint = ($existing['version_constraint'] ?? null) === $derived['version_constraint'];
            $sameRequired = (bool) ($existing['is_required'] ?? false) === $derived['is_required'];

            if ($sameConstraint && $sameRequired) {
                // Identical manual entry already declares this — keep the
                // single existing one, never insert a duplicate.
                return $dependencies;
            }

            throw new InvalidArgumentException(
                "Manually supplied dependency for \"{$derived['ref']}\" conflicts with the Extension usage actually "
                . "found in this Theme (manual: version_constraint="
                . ($existing['version_constraint'] ?? 'null') . ', is_required=' . ($existing['is_required'] ? 'true' : 'false')
                . '; required by usage: version_constraint=' . $derived['version_constraint'] . ', is_required=' . ($derived['is_required'] ? 'true' : 'false') . ').'
            );
        }

        $dependencies[] = $derived;

        return $dependencies;
    }

    /**
     * Attach structured dependency rows to a release that already exists —
     * for a release created before Phase 1e (a one-time extraction pass,
     * mirroring how Phase 1d backfilled releases for themes that predated
     * the ledger itself) or for adding a dependency type in a later pass.
     * Goes through the same validation as release() and the same sole
     * writer; no controller or command writes theme_release_dependencies
     * directly.
     *
     * Not restricted to "only once per release": ThemeReleaseDependency's
     * own unique constraint plus the duplicate check in
     * assertValidDependencies() prevent the same {type, identifier} pair
     * from ever being attached twice to the same release.
     *
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}> $dependencies
     */
    public function attachDependencies(ThemeRelease $release, array $dependencies): void
    {
        if (empty($dependencies)) {
            return;
        }

        $this->assertValidDependencies($dependencies);
        $this->assertNoDuplicateExistingDependencies($release, $dependencies);

        DB::transaction(function () use ($release, $dependencies) {
            $this->writeDependencyRows($release, $dependencies);
        });
    }

    /**
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}> $dependencies
     */
    private function writeDependencyRows(ThemeRelease $release, array $dependencies): void
    {
        foreach ($dependencies as $dependency) {
            $release->structuredDependencies()->create([
                'dependency_type' => $dependency['type'],
                'dependency_identifier' => $dependency['ref'],
                'version_constraint' => $dependency['version_constraint'] ?? null,
                'is_required' => $dependency['is_required'],
                'metadata' => $dependency['metadata'] ?? null,
            ]);
        }
    }

    private function assertNoDuplicateExistingDependencies(ThemeRelease $release, array $dependencies): void
    {
        $existing = $release->structuredDependencies()
            ->get(['dependency_type', 'dependency_identifier'])
            ->map(fn ($row) => $row->dependency_type . '::' . $row->dependency_identifier)
            ->all();

        foreach ($dependencies as $dependency) {
            $key = $dependency['type'] . '::' . $dependency['ref'];
            if (in_array($key, $existing, true)) {
                throw new InvalidArgumentException(
                    "Release {$release->version} already has a \"{$dependency['type']}\" dependency on \"{$dependency['ref']}\"."
                );
            }
        }
    }

    /**
     * Point the theme's current release back at an earlier, still-immutable
     * release. Never mutates release content, never creates a new row, and
     * never touches the theme's live draft columns (header_blocks/footer_blocks/
     * pages/global_styles/settings) — a commercial rollback only changes which
     * release is authoritative, it does not restore draft content. That is a
     * distinct concern already handled by ThemeBuilderService::restoreRevision().
     *
     * (Phase 2b: the legacy Theme.version int this docblock used to describe
     * was retired — ThemeRelease.version is the sole commercial version
     * authority now.)
     */
    public function rollback(Theme $theme, string $targetVersion): ThemeRelease
    {
        $target = $theme->releases()->where('version', $targetVersion)->first();

        if (!$target) {
            throw new InvalidArgumentException("No release {$targetVersion} exists for theme {$theme->slug}.");
        }

        return DB::transaction(function () use ($theme, $target) {
            $current = $theme->currentRelease()->first();
            if ($current && $current->id !== $target->id && $current->status === ThemeRelease::STATUS_PUBLISHED) {
                $current->update(['status' => ThemeRelease::STATUS_SUPERSEDED]);
            }

            if ($target->status !== ThemeRelease::STATUS_PUBLISHED) {
                $target->update(['status' => ThemeRelease::STATUS_PUBLISHED]);
            }

            $theme->current_release_id = $target->id;
            $theme->save();

            return $target->fresh();
        });
    }

    /**
     * Copy an immutable release's frozen snapshot back into the theme's live
     * draft columns, so authoring can continue from that point — distinct
     * from rollback(), which only repoints which release is current/authoritative
     * and never touches draft content. This is the release-ledger equivalent of
     * ThemeBuilderService::restoreRevision(), sourced from a real commercial
     * release instead of a manual checkpoint. Never mutates current_release_id,
     * status, or any ThemeRelease row — draft-side only, and the theme remains
     * on whatever release was current before this call until a new release()
     * is made from the restored draft.
     */
    public function restoreToDraft(ThemeRelease $release): Theme
    {
        $theme = $release->theme;
        $snapshot = $release->snapshot;

        $theme->update([
            'header_blocks' => $snapshot['header_blocks'] ?? [],
            'footer_blocks' => $snapshot['footer_blocks'] ?? [],
            'pages' => $snapshot['pages'] ?? [],
            'global_styles' => $snapshot['global_styles'] ?? [],
            'settings' => $snapshot['settings'] ?? [],
        ]);

        return $theme->fresh();
    }

    /**
     * Suggests the next patch version for a theme with no explicit version
     * supplied by the caller (no version-authoring UI exists yet). Never
     * guesses a major/minor bump — that is a product decision only a human
     * caller can make; patch is the one bump that is always safe to assume.
     */
    public function nextPatchVersion(Theme $theme): string
    {
        $latest = $this->latestRelease($theme);
        if (!$latest) {
            return '1.0.0';
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $latest->version));
        return sprintf('%d.%d.%d', $major, $minor, $patch + 1);
    }

    /**
     * Enforces a stable shape only — not real constraint data. No compatibility
     * requirement has been captured for any theme yet (per
     * THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 3, nothing may be fabricated
     * at migration/backfill time), so every existing caller passes null. This
     * exists so the day real data is supplied, it is caught if malformed
     * rather than silently stored as whatever shape happened to be passed.
     *
     * @param array<string, string>|null $compatibility Map of identifier
     *   (e.g. "platform") to a version constraint string (e.g. ">=1.0.0").
     */
    private function assertValidCompatibility(?array $compatibility): void
    {
        if ($compatibility === null) {
            return;
        }

        foreach ($compatibility as $key => $constraint) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException(
                    'Each compatibility entry must be keyed by a non-empty string identifier (e.g. "platform").'
                );
            }
            if (!is_string($constraint) || $constraint === '') {
                throw new InvalidArgumentException(
                    "Compatibility constraint for \"{$key}\" must be a non-empty string (e.g. \">=1.0.0\")."
                );
            }
        }
    }

    /**
     * Enforces the shape THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 2 defines
     * for theme_release_dependencies rows. `type` is deliberately not
     * restricted to a fixed whitelist: the architecture treats it as an open
     * enum (module | motion_asset | motion_pack | icon_asset | icon_pack |
     * block_pack | design_system today, more product classes later) that
     * must not require a code change here to extend — only a non-empty
     * string is required structurally.
     *
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null $dependencies
     */
    private function assertValidDependencies(?array $dependencies): void
    {
        if ($dependencies === null) {
            return;
        }

        $seen = [];

        foreach ($dependencies as $index => $dependency) {
            if (!is_array($dependency)) {
                throw new InvalidArgumentException("Dependency entry #{$index} must be an array.");
            }
            foreach (['type', 'ref', 'is_required'] as $requiredKey) {
                if (!array_key_exists($requiredKey, $dependency)) {
                    throw new InvalidArgumentException("Dependency entry #{$index} is missing required key \"{$requiredKey}\".");
                }
            }
            if (!is_string($dependency['type']) || $dependency['type'] === '') {
                throw new InvalidArgumentException("Dependency entry #{$index} must have a non-empty string \"type\".");
            }
            if (!is_string($dependency['ref']) || $dependency['ref'] === '') {
                throw new InvalidArgumentException("Dependency entry #{$index} must have a non-empty string \"ref\".");
            }
            if (array_key_exists('version_constraint', $dependency) && $dependency['version_constraint'] !== null) {
                if (!is_string($dependency['version_constraint'])) {
                    throw new InvalidArgumentException("Dependency entry #{$index} \"version_constraint\" must be a string or null.");
                }
                $this->assertValidVersionConstraintSyntax($dependency['version_constraint'], $index);
            }
            if (!is_bool($dependency['is_required'])) {
                throw new InvalidArgumentException("Dependency entry #{$index} \"is_required\" must be a boolean.");
            }
            if (array_key_exists('metadata', $dependency)
                && $dependency['metadata'] !== null
                && !is_array($dependency['metadata'])) {
                throw new InvalidArgumentException("Dependency entry #{$index} \"metadata\" must be an array or null.");
            }

            $key = $dependency['type'] . '::' . $dependency['ref'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException(
                    "Duplicate dependency: \"{$dependency['type']}\" on \"{$dependency['ref']}\" appears more than once in the same release."
                );
            }
            $seen[$key] = true;
        }
    }

    /**
     * Syntax validation only — this phase stores and validates the shape of
     * a compatibility range, it does not resolve or enforce it against any
     * installed version (that is future work, once a consumer like
     * Marketplace cutover or a runtime resolver exists). Deliberately reuses
     * the exact single-clause grammar ModuleDeploymentDependencyResolver
     * already established for module-to-module dependencies
     * (`<op><version>`, operators >=, <=, >, <, =) rather than inventing an
     * incompatible format, and extends it with two additive, non-conflicting
     * capabilities that grammar didn't need for its own use case:
     *   - space-separated clauses, ANDed, for a bounded range
     *     (e.g. ">=1.5 <3.0");
     *   - a leading "^" (caret) clause for the standard semver
     *     "compatible-with" shorthand (e.g. "^2.0").
     */
    private function assertValidVersionConstraintSyntax(string $constraint, int $index): void
    {
        $trimmed = trim($constraint);
        if ($trimmed === '') {
            throw new InvalidArgumentException(
                "Dependency entry #{$index} \"version_constraint\" must not be an empty/whitespace string — use null for unconstrained."
            );
        }

        foreach (preg_split('/\s+/', $trimmed) as $clause) {
            if (!preg_match('/^(>=|<=|>|<|=|\^)\d+(\.\d+){0,2}$/', $clause)) {
                throw new InvalidArgumentException(
                    "Dependency entry #{$index} has an invalid version_constraint clause \"{$clause}\" in \"{$constraint}\" "
                    . '— expected e.g. ">=2.0", "^2.0", or ">=1.5 <3.0" (operators: >=, <=, >, <, =, ^).'
                );
            }
        }
    }

    private function assertValidSemver(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException("Release version \"{$version}\" must be semver (e.g. 1.0.0).");
        }
    }

    /**
     * Highest-versioned release for this theme, compared with version_compare()
     * rather than a DB-specific numeric sort (semver strings do not sort
     * correctly as plain strings, e.g. "1.10.0" vs "1.9.0").
     *
     * Always queried fresh (releases(), not the cached releases property) —
     * a $theme instance reused across multiple release() calls must not see a
     * stale, pre-cached collection that's missing rows just written.
     */
    private function latestRelease(Theme $theme): ?ThemeRelease
    {
        return $theme->releases()->get()->reduce(
            fn (?ThemeRelease $carry, ThemeRelease $release) =>
                ($carry === null || version_compare($release->version, $carry->version, '>')) ? $release : $carry,
            null
        );
    }
}
