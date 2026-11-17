<?php
namespace App\Console\Commands;

use App\Models\Theme;
use Database\Seeders\ArianaMultiPlusThemeSeeder;
use Database\Seeders\SalesSupportThemeSeeder;
use Database\Seeders\SolarIndustryThemeSeeder;
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
        {--fresh : Also remove any master theme not in the canonical membership list}
        {--slug= : Run only the canonical source that declares this slug and verify only that theme (never removes anything)}';

    protected $description = 'Rebuild the complete canonical master Theme catalog through the authoritative seed sources and verify membership deterministically.';

    /**
     * @var array<int, array{label: string, run: array{0: string, 1: array<string, mixed>}, expected_slugs: string[]}>
     * One entry per authoritative source. Adding a new canonical theme
     * source means adding one more entry here — never inventing a
     * duplicate Theme definition elsewhere.
     */
    private const CANONICAL_SOURCES = [
        [
            'label' => 'marketplace:seed',
            'run' => ['marketplace:seed', []],
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
            'run' => ['nexus:seed', []],
            'expected_slugs' => ['nexus-pro-corporate', 'nexus-pro-personal', 'nexus-pro-store'],
        ],
        [
            'label' => 'acool:seed',
            'run' => ['acool:seed', []],
            'expected_slugs' => ['acool-hvac-service'],
        ],
        [
            'label' => 'malltina:seed',
            'run' => ['malltina:seed', []],
            'expected_slugs' => ['malltina-international'],
        ],
        [
            'label' => 'db:seed ' . SalesSupportThemeSeeder::class,
            'run' => ['db:seed', ['--class' => SalesSupportThemeSeeder::class, '--force' => true]],
            'expected_slugs' => ['sales-support-pro'],
        ],
        [
            'label' => 'db:seed ' . ArianaMultiPlusThemeSeeder::class,
            'run' => ['db:seed', ['--class' => ArianaMultiPlusThemeSeeder::class, '--force' => true]],
            'expected_slugs' => ['ariana-multiplus'],
        ],
        // ADR-084: «قالب صنعت برق», assembled from the template library.
        [
            'label' => 'db:seed ' . SolarIndustryThemeSeeder::class,
            'run' => ['db:seed', ['--class' => SolarIndustryThemeSeeder::class, '--force' => true]],
            'expected_slugs' => [SolarIndustryThemeSeeder::SLUG],
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
        $slug = $this->option('slug');
        if ($slug !== null && $slug !== '') {
            return $this->rebuildSingle((string) $slug);
        }

        $this->info('Rebuilding the canonical Theme catalog...');

        foreach (self::CANONICAL_SOURCES as $source) {
            [$command, $arguments] = $source['run'];
            $this->call($command, $arguments);
        }

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

    /**
     * Installs one canonical master theme without touching the rest of the
     * catalog. A production catalog may be deliberately partial (the
     * 2026-11-01 clean-slate purge removed the legacy marketplace themes), so
     * a full rebuild there would resurrect purged themes. This runs only the
     * one source that declares the slug. The theme still needs the Mandatory
     * Publish Gate (`theme-release:backfill --slug=`) before it becomes
     * commercially eligible.
     */
    private function rebuildSingle(string $slug): int
    {
        if ($this->option('fresh')) {
            $this->error('--fresh cannot be combined with --slug: a single-theme install never removes anything.');
            return self::FAILURE;
        }

        $source = collect(self::CANONICAL_SOURCES)
            ->first(fn (array $s) => in_array($slug, $s['expected_slugs'], true));
        if ($source === null) {
            $this->error("\"{$slug}\" is not a canonical master theme slug.");
            return self::FAILURE;
        }
        if (count($source['expected_slugs']) > 1) {
            $this->warn("Source {$source['label']} also seeds: " . implode(', ', array_diff($source['expected_slugs'], [$slug])));
        }

        [$command, $arguments] = $source['run'];
        $this->info("Installing canonical theme {$slug} via {$source['label']}...");
        $this->call($command, $arguments);

        $count = Theme::whereNull('site_id')->where('slug', $slug)->count();
        if ($count !== 1) {
            $this->error("Expected exactly one master theme \"{$slug}\" after seeding, found {$count}.");
            return self::FAILURE;
        }

        $this->info("Canonical theme {$slug} present. Next: php artisan theme-release:backfill --slug={$slug}");

        return self::SUCCESS;
    }
}
