<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightExtractionService;
use App\Services\ThemeBuilder\ReverseEngineering\PlaywrightSnapshotService;
use App\Services\ThemeBuilder\ReverseEngineering\DomExtractionService;
use App\Services\ThemeBuilder\ReverseEngineering\LayoutReconstructionService;
use App\Services\ThemeBuilder\ReverseEngineering\VisualAnalysisService;
use App\Services\ThemeBuilder\ReverseEngineering\CommerceAnalysisService;
use App\Services\ThemeBuilder\ReverseEngineering\SimilarityEngineService;
use App\Services\ThemeBuilder\ReverseEngineering\ReverseEngineeringQualityService;
use App\Services\ThemeBuilder\ReverseEngineering\HeadlessComponentExtractor;

/**
 * TB3 Sprint 11.5 — Headless Engine Real-World Validation Suite
 *
 * Tests Playwright-powered extraction against 6 real SPA/complex sites.
 * Outputs HEADLESS_ENGINE_OPERATIONAL or HEADLESS_ENGINE_BLOCKED.
 *
 * Usage:
 *   php artisan reverse-engineer:validate-headless
 *   php artisan reverse-engineer:validate-headless --site=digikala
 *   php artisan reverse-engineer:validate-headless --device=mobile
 */
class ValidateHeadlessEngineeringCommand extends Command
{
    protected $signature = 'reverse-engineer:validate-headless
                            {--site=   : Run single site (apple|malltina|digikala|amazon|stripe|shopify)}
                            {--device= : Viewport device (desktop|mobile|all) [default: desktop]}
                            {--timeout=35000 : Playwright navigation timeout in ms}';

    protected $description = 'TB3 Sprint 11.5: Validate Playwright Headless RE engine against 6 SPA sites';

    private const SITES = [
        'apple'    => ['url' => 'https://www.apple.com',    'type' => 'tech',      'target' => 95, 'spa' => true],
        'malltina' => ['url' => 'https://malltina.com',     'type' => 'ecommerce', 'target' => 90, 'spa' => false],
        'digikala' => ['url' => 'https://www.digikala.com', 'type' => 'ecommerce', 'target' => 90, 'spa' => true],
        'amazon'   => ['url' => 'https://www.amazon.com',   'type' => 'ecommerce', 'target' => 85, 'spa' => true],
        'stripe'   => ['url' => 'https://stripe.com',       'type' => 'saas',      'target' => 80, 'spa' => true],
        'shopify'  => ['url' => 'https://www.shopify.com',  'type' => 'saas',      'target' => 80, 'spa' => true],
    ];

    public function __construct(
        private readonly PlaywrightExtractionService      $playwright,
        private readonly PlaywrightSnapshotService        $snapshotService,
        private readonly HeadlessComponentExtractor       $headlessExtractor,
        private readonly DomExtractionService             $domExtractor,
        private readonly LayoutReconstructionService      $layoutReconstructor,
        private readonly VisualAnalysisService            $visualAnalyzer,
        private readonly CommerceAnalysisService          $commerceAnalyzer,
        private readonly SimilarityEngineService          $similarityEngine,
        private readonly ReverseEngineeringQualityService $qualityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->printBanner();

        // ── Prerequisite check ────────────────────────────────────────────────
        if (!$this->playwright->isAvailable()) {
            $report = $this->playwright->availabilityReport();
            $this->error('');
            $this->error('  HEADLESS_ENGINE_BLOCKED');
            $this->error('');
            $this->line('  Reason: Playwright is not available on this system.');
            $this->printAvailabilityReport($report);
            $this->line('');
            $this->line('  To install:');
            $this->line('    cd scripts/headless && npm install && npx playwright install chromium');
            $this->line('');
            return Command::FAILURE;
        }

        $report = $this->playwright->availabilityReport();
        $this->info('  ✓ Playwright available');
        if ($report['using_system'] ?? false) {
            $this->line('  ✓ Using system Chrome: ' . ($report['chrome_path'] ?? ''));
        }
        $this->line('');

        // ── Run validation suite ──────────────────────────────────────────────
        $siteFilter = $this->option('site');
        $device     = $this->option('device') ?: 'desktop';
        $timeout    = (int) ($this->option('timeout') ?: 35000);
        $results    = [];

        foreach (self::SITES as $key => $site) {
            if ($siteFilter && $siteFilter !== $key) continue;

            $results[$key] = $this->validateSite($key, $site, $device, $timeout);
            $this->line('');
        }

        // ── Save results ──────────────────────────────────────────────────────
        $jsonPath = base_path('docs/headless_validation_results.json');
        file_put_contents($jsonPath, json_encode([
            'run_at'  => now()->toIso8601String(),
            'device'  => $device,
            'timeout' => $timeout,
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("  Results saved → docs/headless_validation_results.json");

        return $this->printFinalVerdict($results);
    }

    // ── Per-site validation ───────────────────────────────────────────────────

    private function validateSite(string $key, array $site, string $device, int $timeout): array
    {
        $url    = $site['url'];
        $target = $site['target'];

        $this->info("▶ [{$key}] {$url}");
        $this->line("  Target: {$target}%+ | SPA: " . ($site['spa'] ? 'Yes' : 'No') . " | Device: {$device}");

        $result = [
            'site'    => $key,
            'url'     => $url,
            'type'    => $site['type'],
            'target'  => $target,
            'is_spa'  => $site['spa'],
            'device'  => $device,
            'status'  => 'pending',
            'error'   => null,
            'score'   => 0,
            'target_met' => false,
        ];

        // Phase 0: Playwright extraction
        $this->line("  [0] Playwright extraction...");
        $extractStart = microtime(true);
        try {
            $playwright = $this->playwright->extract($url, $timeout, $device);
            $result['extract_ms']   = (int) ((microtime(true) - $extractStart) * 1000);
            $result['html_size']    = strlen($playwright['rendered_html'] ?? '');
            $result['screenshot']   = !empty($playwright['screenshot_base64']);
            $result['framework']    = $this->summarizeFramework($playwright['framework'] ?? []);
            $result['performance']  = $playwright['performance'] ?? [];
            $result['product_count'] = $playwright['components']['product_grid']['count'] ?? 0;

            $this->line("  ✓ Rendered HTML: " . number_format($result['html_size']) . " bytes | {$result['extract_ms']}ms");
            $this->line("  ✓ Framework: " . ($result['framework'] ?: 'unknown'));
            $this->line("  ✓ Products: {$result['product_count']} | Screenshot: " . ($result['screenshot'] ? 'yes' : 'no'));
        } catch (\Throwable $e) {
            $result['status'] = 'extraction_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Extraction failed: " . $e->getMessage());
            return $result;
        }

        // Phase 1: DOM extraction from rendered HTML
        $this->line("  [1] DOM extraction...");
        try {
            $renderedHtml = $playwright['rendered_html'] ?? '';
            $domStructure = $this->domExtractor->extractFromRenderedDom($renderedHtml, $url, $playwright);

            $sections = collect($domStructure['landmarks'] ?? [])->where('count', '>', 0)->pluck('type')->toArray();
            $this->line("  ✓ Sections: " . (empty($sections) ? '(none)' : implode(', ', $sections)));
            $this->line("  ✓ Header: " . ($domStructure['header']['detected'] ? '✓' : '✗')
                . " | Hero: " . ($domStructure['hero']['detected'] ? '✓' : '✗')
                . " | Footer: " . ($domStructure['footer']['detected'] ? '✓' : '✗'));
        } catch (\Throwable $e) {
            $result['status'] = 'phase1_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 1 (DOM) failed: " . $e->getMessage());
            return $result;
        }

        // Snapshots
        $this->line("  [S] Generating snapshots...");
        try {
            $snapshots = $this->snapshotService->generateAll($playwright, $domStructure);
            $cssSnap   = $snapshots['css']    ?? [];
            $domSnap   = $snapshots['dom']    ?? [];
            $laySnap   = $snapshots['layout'] ?? [];

            $result['snapshots'] = [
                'primary_color'     => $cssSnap['primary_color'] ?? null,
                'color_scheme'      => $cssSnap['color_scheme']  ?? null,
                'section_count'     => $domSnap['section_count'] ?? null,
                'column_signature'  => $laySnap['column_signature'] ?? null,
                'layout_style'      => $laySnap['layout_style'] ?? null,
                'is_rtl'            => $domSnap['is_rtl']        ?? false,
            ];

            $this->line("  ✓ CSS primary: " . ($cssSnap['primary_color'] ?? 'n/a')
                . " | sections: " . ($domSnap['section_count'] ?? '?')
                . " | columns: " . ($laySnap['column_signature'] ?? '?'));
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Snapshot generation failed (non-fatal): " . $e->getMessage());
            $snapshots = [];
        }

        // Phase 2: Layout
        $this->line("  [2] Layout reconstruction...");
        try {
            $layoutMap = $this->layoutReconstructor->reconstruct($domStructure, $renderedHtml);
        } catch (\Throwable $e) {
            $result['status'] = 'phase2_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 2 failed: " . $e->getMessage());
            return $result;
        }

        // Phase 3: Visual
        $this->line("  [3] Visual analysis...");
        try {
            $visualProfile = $this->visualAnalyzer->analyze($renderedHtml, $domStructure);
            $this->line("  ✓ Primary color: " . ($visualProfile['color_palette']['primary'] ?? 'unknown')
                . " | Font: " . ($visualProfile['typography']['primary_font'] ?? 'unknown'));
        } catch (\Throwable $e) {
            $result['status'] = 'phase3_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 3 failed: " . $e->getMessage());
            return $result;
        }

        // Phase 4: Commerce
        $this->line("  [4] Commerce analysis...");
        try {
            $commerceProfile = $this->commerceAnalyzer->analyze($renderedHtml, $domStructure);
            $this->line("  ✓ Commerce: " . ($commerceProfile['is_commerce'] ? 'YES' : 'NO')
                . " | Score: " . ($commerceProfile['commerce_score'] ?? 0));
        } catch (\Throwable $e) {
            $result['status'] = 'phase4_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 4 failed: " . $e->getMessage());
            return $result;
        }

        // Phase 5: Similarity (headless mode ON)
        $this->line("  [5] Similarity scoring (headless)...");
        try {
            $similarityScores = $this->similarityEngine->score(
                $domStructure,
                $layoutMap,
                $visualProfile,
                $commerceProfile,
                isHeadless: true,
                cssSnapshot: $snapshots['css'] ?? [],
                domSnapshot: $snapshots['dom'] ?? [],
            );

            $best      = $similarityScores['best_match_label'] ?? '';
            $bestScore = $similarityScores['best_match_score'] ?? 0;
            $overall   = $similarityScores['overall'] ?? $bestScore;

            $result['score']          = $bestScore;
            $result['best_match']     = $best;
            $result['structure_score'] = $similarityScores['structure'] ?? 0;
            $result['visual_score']    = $similarityScores['visual'] ?? 0;
            $result['commerce_score']  = $similarityScores['commerce'] ?? 0;
            $result['layout_score']    = $similarityScores['layout'] ?? 0;
            $result['target_met']      = $bestScore >= $target;

            $this->line("  ✓ Best match: {$best} ({$bestScore}%) — Target {$target}%: " . ($result['target_met'] ? 'MET ✓' : 'MISSED ✗'));
            $this->line("  ✓ S={$result['structure_score']} V={$result['visual_score']} C={$result['commerce_score']} L={$result['layout_score']}");
        } catch (\Throwable $e) {
            $result['status'] = 'phase5_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 5 failed: " . $e->getMessage());
            return $result;
        }

        // Phase 7: Quality
        $this->line("  [7] Quality validation...");
        try {
            $qualityReport = $this->qualityService->validate(
                $domStructure, $layoutMap, $visualProfile, $commerceProfile, $similarityScores, null
            );
            $result['quality_verdict'] = $qualityReport['verdict'] ?? 'UNKNOWN';
            $result['quality_score']   = $qualityReport['score'] ?? 0;

            $this->line("  ✓ Quality: {$result['quality_verdict']} ({$result['quality_score']}/100)");
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Quality validation failed (non-fatal): " . $e->getMessage());
            $result['quality_verdict'] = 'ERROR';
        }

        $result['status'] = 'complete';
        $grade = $this->grade($bestScore);
        $this->info("  ★ {$key}: {$bestScore}% — {$grade}" . ($result['target_met'] ? ' [TARGET MET]' : ' [BELOW TARGET]'));

        return $result;
    }

    // ── Output helpers ────────────────────────────────────────────────────────

    private function printBanner(): void
    {
        $this->line('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║    TB3 Sprint 11.5 — Headless Engine Validation Suite        ║');
        $this->info('║    Playwright + Chrome — SPA Reverse Engineering             ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    private function printAvailabilityReport(array $report): void
    {
        $this->line('');
        $this->line('  Availability check:');
        $this->line('    node:       ' . ($report['node']       ? '✓' : '✗ not found'));
        $this->line('    script:     ' . ($report['script']     ? '✓' : '✗ scripts/headless/extract.mjs missing'));
        $this->line('    playwright: ' . ($report['playwright'] ? '✓' : '✗ node_modules/playwright not installed'));
        $this->line('    chrome:     ' . ($report['chrome_path'] ?? '✗ not found'));
    }

    private function printFinalVerdict(array $results): int
    {
        $this->line('');
        $this->info('══════════════════════════════════════════════════════════════');
        $this->info('  HEADLESS ENGINE VALIDATION — FINAL VERDICT');
        $this->info('══════════════════════════════════════════════════════════════');
        $this->line('');

        $allComplete   = true;
        $allTargetsMet = true;
        $totalScore    = 0;
        $count         = 0;

        foreach ($results as $key => $result) {
            $site = self::SITES[$key] ?? ['target' => 70];

            if ($result['status'] !== 'complete') {
                $this->line("  {$key}: ✗ {$result['status']}" . ($result['error'] ? ' — ' . mb_substr($result['error'], 0, 80) : ''));
                $allComplete   = false;
                $allTargetsMet = false;
                continue;
            }

            $score      = $result['score'] ?? 0;
            $target     = $result['target'] ?? $site['target'];
            $targetMet  = $result['target_met'] ?? ($score >= $target);
            $grade      = $this->grade($score);
            $icon       = $targetMet ? '✓' : '✗';

            $this->line("  {$key}: {$icon} {$score}% ({$grade}) — target {$target}%: " . ($targetMet ? 'MET' : 'MISSED'));

            if (!$targetMet) $allTargetsMet = false;
            $totalScore += $score;
            $count++;
        }

        $avgScore = $count > 0 ? round($totalScore / $count) : 0;

        $this->line('');
        $this->line("  Average score: {$avgScore}%");
        $this->line('');

        if (!$allComplete) {
            $this->error('  HEADLESS_ENGINE_BLOCKED');
            $this->error('  One or more sites failed to extract. Check Playwright setup.');
            $this->line('');
            return Command::FAILURE;
        }

        if ($allTargetsMet) {
            $this->info('  HEADLESS_ENGINE_OPERATIONAL');
            $this->info("  All targets met. Average accuracy: {$avgScore}%");
        } else {
            $this->warn('  HEADLESS_ENGINE_PARTIAL');
            $this->warn("  Some targets missed. Average: {$avgScore}%. Check SPA extraction quality.");
        }

        $this->line('══════════════════════════════════════════════════════════════');
        $this->line('');

        return $allTargetsMet ? Command::SUCCESS : Command::FAILURE;
    }

    private function summarizeFramework(array $fw): string
    {
        $detected = [];
        if ($fw['is_nextjs']    ?? false) $detected[] = 'Next.js';
        elseif ($fw['is_react'] ?? false) $detected[] = 'React';
        if ($fw['is_vue']       ?? false) $detected[] = 'Vue';
        if ($fw['is_angular']   ?? false) $detected[] = 'Angular';
        if ($fw['is_nuxt']      ?? false) $detected[] = 'Nuxt';
        if ($fw['is_tailwind']  ?? false) $detected[] = 'Tailwind';
        if ($fw['is_bootstrap'] ?? false) $detected[] = 'Bootstrap';
        if ($fw['is_mui']       ?? false) $detected[] = 'MUI';
        return implode('+', $detected);
    }

    private function grade(int $score): string
    {
        if ($score >= 90) return 'EXCELLENT';
        if ($score >= 80) return 'GOOD';
        if ($score >= 70) return 'PASS';
        if ($score >= 50) return 'PARTIAL';
        return 'FAIL';
    }
}
