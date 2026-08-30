<?php
namespace App\Console\Commands;

use App\Models\Theme;
use Database\Seeders\SalesSupportThemeSeeder;
use Illuminate\Console\Command;

/**
 * Phase 2b — the one authoritative orchestration entry point for
 * reconstructing the complete supported master Theme catalog.
 *
 * This does NOT duplicate any Theme definition — it invokes the existing,
 * already-authoritative seed commands/seeders exactly as they exist today
 * and then verifies the result against one explicit, version-controlled
 * membership list. That list — not history, not row count, not "however
 * many happen to be in a given database" — is what "canonical" means from
 * this phase forward: every slug in CANONICAL_SOURCES is a deliberate
 * product decision, verified deterministic and re-runnable (Phase 2b audit:
 * git history proved no re-runnable source exists for the 7 previously
 * "missing" theme slugs — including the 3 hand-authored Motion-dependency
 * "-premium" themes, whose referenced motion asset ids never resolved to
 * any real Motion Graphics catalog row either — so none were restored; see
 * DECISIONS.md for the full investigation).
 *
 * A fresh dev/test/CI database reconstructs the full set of authored master
 * Theme *definitions* through this one command — but that alone grants no
 * commercial eligibility (Phase 2d, ADR-025): every source seeds
 * `status = published` for authoring/workflow reasons only (e.g. so the
 * admin catalog UI shows it as complete), never as a commercial signal.
 * `Theme::isCommerciallyEligible()` requires a real current ThemeRelease
 * backed by a passing Commercial Quality Report, which this command
 * deliberately does not create — that is the sole job of the Mandatory
 * Publish Gate (`theme-release:backfill`, Phase 2c), run as the next step.
 * On a genuinely fresh database that single follow-up command is
 * sufficient — it evaluates and links each theme's first release
 * atomically. `theme-release:link-quality-reports` (Phase 2d) exists only
 * as a one-time bridge for a database whose releases were already backfilled
 * before the gate was wired into that command; it has nothing to do on a
 * fresh rebuild.
 */
class ThemeCatalogRebuildCommand extends Command
{
    protected $signature = 'theme-catalog:rebuild
        {--fresh : Also remove any master theme not in the canonical membership list}';

    protected $description = 'Rebuild the complete canonical master Theme catalog through the authoritative seed sources and verify membership deterministically.';

    /**
     * @var array<int, array{label: string, expected_slugs: string[]}>
     * One entry per authoritative source. Adding a new canonical theme
     * source means adding one more entry here — never inventing a
     * duplicate Theme definition elsewhere.
     */
    private const CANONICAL_SOURCES = [
        [
            'label' => 'marketplace:seed',
            'expected_slugs' => [
                'khabar-online', 'akademi-danesh', 'clinic-salamat', 'daftar-hoqooqi',
                'azhans-maskan', 'restaurant-lux', 'portfolio-khalaq', 'azhans-digital',
                'bazar-online', 'narmafzar-abri', 'landing-herfei', 'majale-online',
                'vakil-pro', 'salon-ziba', 'safar-lux', 'khodro-pro', 'sanat-molodi',
                'sakhteman-iran', 'mode-fa', 'digi-shop', 'mahsool-vije', 'enterprise-cloud',
            ],
        ],
        [
            'label' => 'nexus:seed',
            'expected_slugs' => ['nexus-pro-corporate', 'nexus-pro-personal', 'nexus-pro-store'],
        ],
        [
            'label' => 'acool:seed',
            'expected_slugs' => ['acool-hvac-service'],
        ],
        [
            'label' => 'malltina:seed',
            'expected_slugs' => ['malltina-international'],
        ],
        [
            'label' => 'db:seed ' . SalesSupportThemeSeeder::class,
            'expected_slugs' => ['sales-support-pro'],
        ],
    ];

    /**
     * Deliberately NOT a canonical source (Phase 2b audit): stores its page
     * content under settings.layouts.* instead of the Theme.pages field the
     * entire current architecture (rendering, ThemeRelease snapshots, the
     * Commercial Quality Gate) reads — a pre-multi-page-architecture
     * authoring convention that produces themes with zero real pages by
     * every current standard. Fixing it would mean writing a real
     * settings.layouts → pages data migration, which is out of proportion
     * to what this command is for; excluded rather than patched around. See
     * DECISIONS.md for the full investigation.
     */
    private const EXCLUDED_INCOMPATIBLE_SOURCE = \Database\Seeders\ProfessionalThemesSeeder::class;

    public function handle(): int
    {
        $this->info('Rebuilding the canonical Theme catalog...');

        $this->call('marketplace:seed');
        $this->call('nexus:seed');
        $this->call('acool:seed');
        $this->call('malltina:seed');
        $this->call('db:seed', ['--class' => SalesSupportThemeSeeder::class, '--force' => true]);

        $expectedSlugs = collect(self::CANONICAL_SOURCES)->flatMap(fn ($s) => $s['expected_slugs'])->unique()->values();

        // Duplicate identity within the expectation list itself would be an
        // authoring bug in this command, not a runtime condition — fail loud
        // rather than silently deduping.
        $rawSlugCount = collect(self::CANONICAL_SOURCES)->flatMap(fn ($s) => $s['expected_slugs'])->count();
        if ($rawSlugCount !== $expectedSlugs->count()) {
            $this->error('CANONICAL_SOURCES declares the same slug more than once — fix the membership list itself.');
            return self::FAILURE;
        }

        $actualSlugs = Theme::whereNull('site_id')->pluck('slug');

        $missing = $expectedSlugs->diff($actualSlugs);
        $unexpected = $actualSlugs->diff($expectedSlugs);
        $duplicated = $actualSlugs->duplicates();

        $ok = true;

        if ($missing->isNotEmpty()) {
            $this->error('Missing canonical theme(s) after rebuild: ' . $missing->implode(', '));
            $ok = false;
        }

        if ($duplicated->isNotEmpty()) {
            $this->error('Duplicate theme identity detected: ' . $duplicated->unique()->implode(', '));
            $ok = false;
        }

        if ($unexpected->isNotEmpty()) {
            $this->warn('Non-canonical master theme(s) present: ' . $unexpected->implode(', '));
            if ($this->option('fresh')) {
                Theme::whereNull('site_id')->whereIn('slug', $unexpected)->delete();
                $this->info('Removed non-canonical theme(s) (--fresh).');
            } else {
                $this->line('  (pass --fresh to remove them)');
            }
        }

        if (!$ok) {
            return self::FAILURE;
        }

        $this->info("Canonical catalog verified: {$expectedSlugs->count()} theme(s) present, deterministic membership confirmed.");

        return self::SUCCESS;
    }
}
