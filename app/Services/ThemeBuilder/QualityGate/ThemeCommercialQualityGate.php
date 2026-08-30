<?php
namespace App\Services\ThemeBuilder\QualityGate;

use App\Models\Theme;
use App\Models\ThemeQualityReport;
use App\Services\ThemeBuilder\QualityGate\Checks\AccessibilityBaselineCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\AssetIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\ColorContrastCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\CommercialMetadataCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\DependencyIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\PerformanceBaselineCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\RenderIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\ResponsiveReadinessCheck;
use App\Services\ThemeBuilder\QualityGate\Checks\StructuralIntegrityCheck;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase 2a/2c — the one authoritative Commercial Quality Gate for Theme
 * products. A Theme must not become commercially publishable merely because
 * its data structure is valid — this is the permanent boundary that enforces
 * that rule.
 *
 * Phase 2c — Mandatory Commercial Theme Publish Gate: publishIfCompliant()
 * is now the sole path every production caller uses to reach
 * ThemeReleaseService::release() — ThemeBuilderService::publish() (the real
 * interactive publish flow, via ThemeBuilderController) and
 * ThemeReleaseBackfillCommand (the ledger-continuity backfill for
 * pre-existing catalog data) both go through it. Direct
 * ThemeReleaseService::release() calls remain possible at the class level
 * (it is still the sole *writer*, and tests exercising it in isolation call
 * it directly on purpose), but no production code path does so anymore — see
 * ADR-024. ThemeReleaseService::rollback() and attachDependencies() are
 * deliberately NOT gated here: rollback only repoints current_release_id to
 * an already-immutable, previously-approved release (no new content is
 * introduced), and attachDependencies() only adds metadata to a release that
 * already exists — neither is a new commercial publish event.
 *
 * Extensibility: add a class implementing ThemeQualityCheck and pass it into
 * the constructor's $checks array (or resolve this class from the container
 * with an extended default list) — no other file needs to change.
 */
class ThemeCommercialQualityGate
{
    /** @var ThemeQualityCheck[] */
    private array $checks;

    /**
     * @param ThemeQualityCheck[]|null $checks Defaults to the full Phase 2a
     *   check set. Pass an explicit list to add future checks (Icon Packs,
     *   Block Packs, Design Systems, SEO, visual regression, performance
     *   budgets, ...) without editing this class.
     */
    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? [
            app(StructuralIntegrityCheck::class),
            app(RenderIntegrityCheck::class),
            app(DependencyIntegrityCheck::class),
            app(AccessibilityBaselineCheck::class),
            app(ResponsiveReadinessCheck::class),
            app(ColorContrastCheck::class),
            app(AssetIntegrityCheck::class),
            app(PerformanceBaselineCheck::class),
            app(CommercialMetadataCheck::class),
        ];
    }

    /**
     * Runs every registered check and persists the result — including a
     * failing one; a failure is an audit fact, not something to discard.
     *
     * @param array<int, array{type: string, ref: string, version_constraint?: ?string, is_required: bool, metadata?: ?array}>|null $dependencies
     */
    public function evaluate(Theme $theme, string $version, ?array $dependencies = null): ThemeQualityReport
    {
        $results = [];

        foreach ($this->checks as $check) {
            try {
                foreach ($check->run($theme, $version, $dependencies) as $result) {
                    $results[] = $result;
                }
            } catch (Throwable $e) {
                // A check that itself throws is a blocking failure of that
                // check, not a silent skip — the gate must never treat "the
                // validator crashed" as "the theme passed."
                $results[] = ThemeQualityCheckResult::fail(
                    'gate_internal',
                    'gate.check_exception',
                    'Check ' . $check::class . ' threw: ' . $e->getMessage(),
                    ['check' => $check::class]
                );
            }
        }

        $hasBlockingFailure = (bool) array_filter($results, fn (ThemeQualityCheckResult $r) => $r->isBlockingFailure());

        return ThemeQualityReport::create([
            'theme_id' => $theme->id,
            'version' => $version,
            'status' => $hasBlockingFailure ? ThemeQualityReport::STATUS_FAILED : ThemeQualityReport::STATUS_PASSED,
            'checks' => array_map(fn (ThemeQualityCheckResult $r) => $r->toArray(), $results),
            'evaluated_at' => now(),
        ]);
    }

    /**
     * The real, atomic, fail-closed integration point: evaluate, and only
     * if the gate passes, create the ThemeRelease through the sole writer
     * (ThemeReleaseService::release()) — then link the already-persisted
     * report to the release it approved. A failing gate never calls
     * release() at all, so no ThemeRelease row, no current_release_id
     * change, and no legacy Theme.version bump can happen — there is
     * nothing to roll back because nothing was started.
     */
    public function publishIfCompliant(
        Theme $theme,
        ThemeReleaseService $releaseService,
        string $version,
        string $changelog,
        ?string $releaseNotes = null,
        ?array $compatibility = null,
        ?array $dependencies = null,
        ?int $publishedBy = null
    ): \App\Models\ThemeRelease {
        $report = $this->evaluate($theme, $version, $dependencies);

        if (!$report->isPassing()) {
            throw new ThemeQualityGateFailedException($report);
        }

        return DB::transaction(function () use ($theme, $releaseService, $version, $changelog, $releaseNotes, $compatibility, $dependencies, $publishedBy, $report) {
            $release = $releaseService->release($theme, $version, $changelog, $releaseNotes, $compatibility, $dependencies, $publishedBy);
            $report->update(['theme_release_id' => $release->id]);
            return $release;
        });
    }

    /**
     * Phase 2d reconciliation — for a release that already exists (created
     * by theme-release:backfill before Phase 2c wired the gate into that
     * command, so it has no linked quality report) evaluate the theme now,
     * for real, and link the report to that EXISTING release if it passes.
     * Never creates a new ThemeRelease — the release is already immutable
     * and its content already matches what's being evaluated (nothing has
     * republished since); this only supplies the missing evidence a real
     * evaluation, run honestly today, provides. If the theme's current
     * content no longer passes, the existing release is correctly left
     * unlinked — Theme::isCommerciallyEligible() will report it ineligible
     * until it is genuinely re-published, exactly as it should for any
     * other release that never had a passing report.
     */
    public function linkExistingReleaseIfCompliant(Theme $theme, \App\Models\ThemeRelease $release): ThemeQualityReport
    {
        if ($release->theme_id !== $theme->id) {
            throw new \InvalidArgumentException("Release {$release->id} does not belong to theme \"{$theme->slug}\".");
        }

        $dependencies = $release->structuredDependencies->map(fn ($d) => [
            'type' => $d->dependency_type,
            'ref' => $d->dependency_identifier,
            'version_constraint' => $d->version_constraint,
            'is_required' => $d->is_required,
            'metadata' => $d->metadata,
        ])->all();

        $report = $this->evaluate($theme, $release->version, $dependencies ?: null);

        if (!$report->isPassing()) {
            throw new ThemeQualityGateFailedException($report);
        }

        $report->update(['theme_release_id' => $release->id]);

        return $report;
    }
}
