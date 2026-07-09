<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseRequest;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseResult;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleReleaseProducerInterface;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * Commit 12-H — one-time backfill of module_releases for modules that
 * predate the ModuleReleaseProducerInterface pipeline (12-F/12-G) and
 * therefore never got an authoritative release row (see
 * docs/architecture/module-version-authority-adr.md).
 *
 * Selection rule: a module is a backfill candidate only if it exists as
 * a package (discovered via ModuleVersionRegistry). Whether it also has
 * a `modules` DB row and whether it already has an active release are
 * NOT checked here — this command never queries module_releases or
 * App\Models directly. Every candidate is simply offered to
 * ModuleReleaseProducerInterface::record(), the single write path for
 * module_releases, which already resolves both conditions and reports
 * back via ModuleReleaseResult::status:
 *   - no matching `modules` row               → skipped_no_module_row
 *   - an active release for that version exists → skipped_duplicate
 *   - otherwise                                 → created
 *
 * --dry-run lists candidates and their package versions without calling
 * the producer at all (no writes of any kind).
 */
class ModuleReleaseBackfillCommand extends Command
{
    protected $signature = 'module:release-backfill
        {--type=patch : Release type recorded for backfilled releases (patch|minor|major)}
        {--module= : Restrict backfill to a single module slug}
        {--dry-run : List candidate modules without recording anything}';

    protected $description = 'Backfill module_releases for existing modules that have no active release yet';

    public function handle(ModuleReleaseProducerInterface $producer): int
    {
        $releaseType = $this->option('type');
        if (!in_array($releaseType, ['patch', 'minor', 'major'], true)) {
            $this->error("Invalid --type [{$releaseType}]. Expected patch, minor, or major.");
            return self::FAILURE;
        }

        $modules = app(ModuleVersionRegistry::class)->all();

        $moduleFilter = $this->option('module');
        if ($moduleFilter) {
            $modules = array_intersect_key($modules, [$moduleFilter => true]);
        }

        if (empty($modules)) {
            $this->warn('No candidate modules discovered on the filesystem — nothing to backfill.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '[DRY RUN] No releases will be recorded.' : 'Running module_releases backfill…');

        $counters = [
            ModuleReleaseResult::STATUS_CREATED => 0,
            ModuleReleaseResult::STATUS_SKIPPED_DUPLICATE => 0,
            ModuleReleaseResult::STATUS_SKIPPED_NO_MODULE_ROW => 0,
            ModuleReleaseResult::STATUS_FAILED => 0,
        ];

        foreach ($modules as $slug => $data) {
            $version = $data['version'] ?? null;
            if (!$version) {
                $this->warn("  {$slug}: no version in module.json — skipped.");
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] {$slug} v{$version}");
                continue;
            }

            $result = $producer->record(new ModuleReleaseRequest(
                slug: $slug,
                version: $version,
                releaseType: $releaseType,
                distributedAt: new \DateTimeImmutable(),
            ));

            $counters[$result->status] = ($counters[$result->status] ?? 0) + 1;

            match ($result->status) {
                ModuleReleaseResult::STATUS_CREATED =>
                    $this->info("  \u{2705} {$slug} v{$version}: release created (id={$result->releaseId})."),
                ModuleReleaseResult::STATUS_SKIPPED_DUPLICATE =>
                    $this->line("  \u{23ED} {$slug} v{$version}: {$result->reason}"),
                ModuleReleaseResult::STATUS_SKIPPED_NO_MODULE_ROW =>
                    $this->warn("  \u{26A0} {$slug} v{$version}: {$result->reason}"),
                default =>
                    $this->error("  \u{274C} {$slug} v{$version}: {$result->reason}"),
            };
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('Dry run only — no changes were persisted.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->table(['status', 'count'], collect($counters)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
