<?php
namespace App\Console\Commands;

use App\Models\ThemeRelease;
use App\Models\ThemeReleaseDependency;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 1e — extract structured dependency rows for existing (Phase 1d
 * backfilled) master Theme releases, from the only data that actually
 * proves a dependency exists today:
 *
 *   - snapshot.settings.supported_modules       → one `module` row each
 *   - snapshot.settings.motion.required_assets  → one `motion_asset` row each
 *
 * Both are read from the release's own frozen snapshot, not the theme's
 * live (possibly since-edited) draft — a dependency row describes what a
 * specific immutable release actually contains, not whatever the draft
 * currently says.
 *
 * ThemeMarketplace.requirements (a human-authored, per-module description
 * string — verified in Phase 1e's audit to contain no version data at all)
 * is attached as metadata.description where it exists for a given module,
 * since it is real, already-existing data about that exact module/theme
 * pair — never invented, never treated as a version constraint.
 *
 * No version_constraint is ever set here: no such data has been captured
 * anywhere in this codebase for any of these themes. A theme with neither
 * source present (e.g. an AI-generated theme with no supported_modules key
 * at all) gets zero dependency rows and is reported, not guessed at.
 *
 * Idempotent: only releases with zero existing structuredDependencies rows
 * are candidates, so re-running only fills in what's still missing.
 */
class ThemeReleaseDependencyBackfillCommand extends Command
{
    protected $signature = 'theme-release:backfill-dependencies {--dry-run : Preview extracted dependencies without writing anything}';

    protected $description = 'Extract structured theme_release_dependencies rows from existing releases\' settings.supported_modules / settings.motion.required_assets (idempotent).';

    public function handle(ThemeReleaseService $releaseService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $releases = ThemeRelease::whereHas('theme', fn ($q) => $q->whereNull('site_id'))
            ->whereDoesntHave('structuredDependencies')
            ->with(['theme.marketplaceMeta'])
            ->orderBy('id')
            ->get();

        if ($releases->isEmpty()) {
            $this->info('No releases require dependency backfilling.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d release(s) have no structured dependency rows yet.%s',
            $releases->count(),
            $dryRun ? ' [DRY RUN — no writes]' : ''
        ));

        $succeeded = 0;
        $failed = 0;
        $noData = 0;

        foreach ($releases as $release) {
            $theme = $release->theme;
            $settings = $release->snapshot['settings'] ?? [];
            $requirementsDescriptions = $theme->marketplaceMeta->requirements ?? [];
            if (!is_array($requirementsDescriptions)) {
                $requirementsDescriptions = [];
            }

            $dependencies = $this->extractDependencies($settings, $requirementsDescriptions);

            if (empty($dependencies)) {
                $this->line("  – {$theme->slug} (release {$release->version}): no extractable dependency data — left unresolved.");
                $noData++;
                continue;
            }

            if ($dryRun) {
                foreach ($dependencies as $dependency) {
                    $this->line("  [DRY] {$theme->slug} {$release->version}: {$dependency['type']} → {$dependency['ref']}");
                }
                continue;
            }

            try {
                $releaseService->attachDependencies($release, $dependencies);
                $this->line("  ✓ {$theme->slug} (release {$release->version}): " . count($dependencies) . ' dependency row(s)');
                $succeeded++;
            } catch (Throwable $e) {
                $this->error("  ✗ {$theme->slug}: {$e->getMessage()}");
                $failed++;
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete — no rows were written.');
            return self::SUCCESS;
        }

        $this->info("Dependency backfill complete: {$succeeded} releases updated, {$noData} with no extractable data, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{type: string, ref: string, version_constraint: ?string, is_required: bool, metadata: array}>
     */
    private function extractDependencies(array $settings, array $requirementsDescriptions): array
    {
        $dependencies = [];

        foreach ((array) ($settings['supported_modules'] ?? []) as $moduleSlug) {
            if (!is_string($moduleSlug) || $moduleSlug === '') {
                continue;
            }

            $metadata = ['source' => 'settings.supported_modules'];
            if (isset($requirementsDescriptions[$moduleSlug]) && is_string($requirementsDescriptions[$moduleSlug])) {
                $metadata['description'] = $requirementsDescriptions[$moduleSlug];
            }

            $dependencies[] = [
                'type' => ThemeReleaseDependency::TYPE_MODULE,
                'ref' => $moduleSlug,
                'version_constraint' => null,
                'is_required' => false,
                'metadata' => $metadata,
            ];
        }

        foreach ((array) ($settings['motion']['required_assets'] ?? []) as $asset) {
            $assetId = is_array($asset) ? ($asset['asset_id'] ?? null) : null;
            if (!is_string($assetId) || $assetId === '') {
                continue;
            }

            $metadata = ['source' => 'settings.motion.required_assets'];
            if (!empty($settings['motion']['package_reference'])) {
                $metadata['package_reference'] = $settings['motion']['package_reference'];
            }

            $dependencies[] = [
                'type' => ThemeReleaseDependency::TYPE_MOTION_ASSET,
                'ref' => $assetId,
                'version_constraint' => null,
                'is_required' => false,
                'metadata' => $metadata,
            ];
        }

        return $dependencies;
    }
}
