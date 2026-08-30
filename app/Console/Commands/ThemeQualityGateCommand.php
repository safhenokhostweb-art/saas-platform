<?php
namespace App\Console\Commands;

use App\Models\Theme;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use Illuminate\Console\Command;

/**
 * Phase 2a — CI/CD and manual entry point for the Commercial Quality Gate.
 * Evaluates (never publishes) — this command only reports; it never calls
 * ThemeReleaseService::release(). Use ThemeCommercialQualityGate::
 * publishIfCompliant() directly wherever an actual gated publish is needed.
 */
class ThemeQualityGateCommand extends Command
{
    protected $signature = 'theme:quality-gate {theme? : Theme id or slug. Omit to evaluate every master theme.}';

    protected $description = 'Run the Commercial Quality Gate against one or all master themes and report results honestly (does not publish anything).';

    public function handle(ThemeCommercialQualityGate $gate): int
    {
        $themeArg = $this->argument('theme');

        $themes = $themeArg
            ? Theme::whereNull('site_id')->where(fn ($q) => $q->where('id', $themeArg)->orWhere('slug', $themeArg))->get()
            : Theme::whereNull('site_id')->orderBy('id')->get();

        if ($themes->isEmpty()) {
            $this->error('No matching master theme found.');
            return self::FAILURE;
        }

        $passed = 0;
        $failed = 0;

        foreach ($themes as $theme) {
            $version = $theme->currentRelease?->version ?? '0.0.0-evaluation';
            $dependencies = $theme->currentRelease?->structuredDependencies
                ->map(fn ($d) => [
                    'type' => $d->dependency_type,
                    'ref' => $d->dependency_identifier,
                    'version_constraint' => $d->version_constraint,
                    'is_required' => $d->is_required,
                    'metadata' => $d->metadata,
                ])->all();

            $report = $gate->evaluate($theme, $version, $dependencies);

            if ($report->isPassing()) {
                $passed++;
                $this->line("  ✓ {$theme->slug}: PASSED (" . count($report->nonBlockingFindings()) . ' non-blocking finding(s))');
            } else {
                $failed++;
                $this->error("  ✗ {$theme->slug}: FAILED");
                foreach ($report->blockingFailures() as $failure) {
                    $this->line("      - [{$failure['code']}] {$failure['message']}");
                }
            }
        }

        $this->info("Quality gate evaluation complete: {$passed} passed, {$failed} failed (evaluation only — nothing was published).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
