<?php
namespace App\Console\Commands;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 1d — give every existing master Theme Product its honest first
 * commercial release.
 *
 * Scope: only master catalog themes (`site_id IS NULL`). Customer-instance
 * Theme rows (site_id set) are never touched here — per
 * COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md §6 and ADR-021 §6, a customer
 * instance gets its own first release through its own normal publish flow
 * (ThemeBuilderService::publish(), already wired), never a bulk backfill.
 *
 * Idempotency: candidates are selected via `whereDoesntHave('releases')`, so
 * a theme that already has any release (from this command or a real publish)
 * is never touched again — safe to re-run any number of times.
 *
 * Fail-safe: each theme is released independently. publishIfCompliant()
 * wraps its own write in a transaction, so one theme's failure (it fails the
 * Commercial Quality Gate, or the release-readiness gate) rolls back only
 * that theme and does not stop the remaining candidates from being
 * processed.
 *
 * No historical data is invented: every backfilled release is honestly
 * dated at the moment this command runs, versioned 1.0.0, with a changelog
 * that says exactly what happened — never a fabricated earlier version or
 * date. compatibility/dependencies are left null — no such data has ever
 * been captured for these themes, and none is guessed here.
 *
 * Phase 2c: this is a real, production-invocable path that creates a
 * ThemeRelease and advances current_release_id — exactly the action the
 * Mandatory Commercial Publish Gate governs — so it is not exempt from it.
 * A theme that fails the gate (e.g. no authored pages, a broken render, a
 * missing required dependency) is correctly left un-backfilled, not granted
 * a release it would not have earned through the normal publish flow
 * either; its failing ThemeQualityReport is persisted exactly as it would be
 * for a rejected interactive publish.
 */
class ThemeReleaseBackfillCommand extends Command
{
    protected $signature = 'theme-release:backfill {--dry-run : Preview which themes would be released without writing anything}';

    protected $description = 'Give every master Theme Product with zero releases its honest first commercial release (v1.0.0), idempotently, through the Mandatory Commercial Quality Gate.';

    public function handle(ThemeReleaseService $releaseService, ThemeCommercialQualityGate $qualityGate): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Theme::whereNull('site_id')
            ->whereDoesntHave('releases')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No master themes require backfilling — every master Theme already has at least one release.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d master theme(s) require a first commercial release.%s',
            $candidates->count(),
            $dryRun ? ' [DRY RUN — no writes]' : ''
        ));

        $succeeded = 0;
        $failed = 0;

        foreach ($candidates as $theme) {
            if ($dryRun) {
                $this->line("  [DRY] would release \"{$theme->slug}\" as 1.0.0");
                continue;
            }

            try {
                $qualityGate->publishIfCompliant(
                    $theme,
                    $releaseService,
                    '1.0.0',
                    'Initial commercial release',
                    'Initial commercial release created during Theme Productization migration.',
                    null, // compatibility — not provable from existing data, must not be fabricated
                    null, // dependencies — not provable from existing data, must not be fabricated
                    null  // published_by — system-run backfill, no human publisher
                );
                $this->line("  ✓ {$theme->slug} → 1.0.0");
                $succeeded++;
            } catch (ThemeQualityGateFailedException $e) {
                $codes = implode(', ', array_column($e->report->blockingFailures(), 'code'));
                $this->error("  ✗ {$theme->slug}: failed the Commercial Quality Gate — {$codes}");
                $failed++;
            } catch (Throwable $e) {
                $this->error("  ✗ {$theme->slug}: {$e->getMessage()}");
                $failed++;
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete — no releases were written.');
            return self::SUCCESS;
        }

        $this->info("Backfill complete: {$succeeded} released, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
