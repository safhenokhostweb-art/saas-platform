<?php
namespace App\Console\Commands;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\QualityGate\ThemeQualityGateFailedException;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase 2d reconciliation — one-time bridge for releases that predate the
 * Mandatory Commercial Publish Gate (Phase 2c). Every one of the 28
 * canonical catalog releases was created by theme-release:backfill in
 * Phase 2b, before that command called ThemeCommercialQualityGate at all —
 * so none of them have a linked passing ThemeQualityReport, and under
 * Theme::isCommerciallyEligible() (Phase 2d, ADR-025) none of them are
 * currently commercially eligible despite being real, valid, already-quality-
 * gate-verified themes (Phase 2b/2c both independently confirmed 28/28
 * passing evaluations).
 *
 * This command closes that gap honestly: for every master Theme whose
 * current release has no linked report, it runs a REAL evaluation against
 * the theme's current content today and links the report to the EXISTING
 * release if it passes — it never creates a new release (the immutable
 * release already exists and its content hasn't changed since backfill) and
 * never fabricates a report. A theme that no longer passes is correctly left
 * ineligible, reported, and does not stop the rest of the batch.
 *
 * Idempotent: a release that already has a linked report is skipped.
 * Customer-instance Theme rows (site_id set) are never touched — same scope
 * boundary as theme-release:backfill.
 */
class ThemeReleaseQualityLinkCommand extends Command
{
    protected $signature = 'theme-release:link-quality-reports {--dry-run : Preview which releases would be linked without writing anything}';

    protected $description = 'Link a real, freshly-evaluated passing Commercial Quality Report to every existing master ThemeRelease that predates the Mandatory Publish Gate and has none yet.';

    public function handle(ThemeCommercialQualityGate $gate): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Theme::whereNull('site_id')
            ->whereNotNull('current_release_id')
            ->with('currentRelease.qualityReports')
            ->get()
            ->filter(fn (Theme $theme) => $theme->currentRelease
                && $theme->currentRelease->qualityReports->isEmpty());

        if ($candidates->isEmpty()) {
            $this->info('No release requires quality-report linkage — every master Theme\'s current release already has one.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d release(s) predate the Mandatory Publish Gate and have no linked quality report.%s',
            $candidates->count(),
            $dryRun ? ' [DRY RUN — no writes]' : ''
        ));

        $linked = 0;
        $failed = 0;

        foreach ($candidates as $theme) {
            if ($dryRun) {
                $this->line("  [DRY] would evaluate and link \"{$theme->slug}\" (release {$theme->currentRelease->version})");
                continue;
            }

            try {
                $gate->linkExistingReleaseIfCompliant($theme, $theme->currentRelease);
                $this->line("  ✓ {$theme->slug} → release {$theme->currentRelease->version} linked");
                $linked++;
            } catch (ThemeQualityGateFailedException $e) {
                $codes = implode(', ', array_column($e->report->blockingFailures(), 'code'));
                $this->error("  ✗ {$theme->slug}: does not currently pass the Commercial Quality Gate — {$codes}");
                $failed++;
            } catch (Throwable $e) {
                $this->error("  ✗ {$theme->slug}: {$e->getMessage()}");
                $failed++;
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete — no reports were linked.');
            return self::SUCCESS;
        }

        $this->info("Linkage complete: {$linked} linked, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
