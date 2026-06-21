<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ThemeBuilder\ReverseEngineering\DomExtractionService;
use App\Services\ThemeBuilder\ReverseEngineering\LayoutReconstructionService;
use App\Services\ThemeBuilder\ReverseEngineering\VisualAnalysisService;
use App\Services\ThemeBuilder\ReverseEngineering\CommerceAnalysisService;
use App\Services\ThemeBuilder\ReverseEngineering\SimilarityEngineService;
use App\Services\ThemeBuilder\ReverseEngineering\ReverseEngineeringQualityService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real-world validation of the Visual Reverse Engineering pipeline
 * against apple.com, malltina.com, digikala.com
 */
class ValidateReverseEngineeringCommand extends Command
{
    protected $signature   = 'reverse-engineer:validate {--site= : Run for single site only}';
    protected $description = 'Validate Visual RE pipeline against real websites';

    private const SITES = [
        'apple'    => ['url' => 'https://www.apple.com', 'type' => 'tech'],
        'malltina' => ['url' => 'https://malltina.com', 'type' => 'ecommerce'],
        'digikala' => ['url' => 'https://www.digikala.com', 'type' => 'ecommerce'],
    ];

    public function __construct(
        private readonly DomExtractionService          $domExtractor,
        private readonly LayoutReconstructionService   $layoutReconstructor,
        private readonly VisualAnalysisService         $visualAnalyzer,
        private readonly CommerceAnalysisService       $commerceAnalyzer,
        private readonly SimilarityEngineService       $similarityEngine,
        private readonly ReverseEngineeringQualityService $qualityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $siteFilter = $this->option('site');
        $results    = [];

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║   Visual Reverse Engineering — Real-World Validation     ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->info('');

        foreach (self::SITES as $key => $site) {
            if ($siteFilter && $siteFilter !== $key) continue;

            $this->info("▶ Processing: {$site['url']}");
            $result = $this->runValidation($key, $site['url'], $site['type']);
            $results[$key] = $result;

            $this->printSummary($key, $result);
            $this->newLine();
        }

        // Save JSON results to file
        $jsonPath = base_path('docs/re_validation_results.json');
        file_put_contents($jsonPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Full results saved to: {$jsonPath}");

        // Final verdict
        $this->printFinalVerdict($results);

        return Command::SUCCESS;
    }

    private function runValidation(string $key, string $url, string $type): array
    {
        $result = [
            'site'       => $key,
            'url'        => $url,
            'type'       => $type,
            'status'     => 'pending',
            'error'      => null,
            'html_size'  => 0,
            'fetch_time' => 0,
        ];

        // ── Fetch HTML ──────────────────────────────────────────────────────
        $fetchStart = microtime(true);
        try {
            $this->line("  → Fetching HTML...");
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fa,en-US;q=0.7,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
            ])->timeout(30)->get($url);

            $html = $response->body();
            $result['http_status']  = $response->status();
            $result['html_size']    = strlen($html);
            $result['fetch_time']   = round((microtime(true) - $fetchStart) * 1000);
            $result['final_url']    = $response->effectiveUri()?->__toString() ?? $url;

            $this->line("  → HTTP {$response->status()}, " . number_format($result['html_size']) . " bytes, {$result['fetch_time']}ms");
        } catch (\Throwable $e) {
            $result['status'] = 'fetch_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Fetch failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 1: DOM Extraction ─────────────────────────────────────────
        $this->line("  → Phase 1: DOM Extraction...");
        try {
            $t = microtime(true);
            $domStructure = $this->domExtractor->extract($html, $url);
            $result['phase1_time'] = round((microtime(true) - $t) * 1000);
            $result['dom'] = $domStructure;

            $landmarks = collect($domStructure['landmarks'])->where('count', '>', 0)->pluck('type')->toArray();
            $this->line("  → Sections found: " . implode(', ', $landmarks) . " ({$domStructure['sections_count']} total)");
            $this->line("  → Title: " . ($domStructure['title'] ?: '(none)'));
            $this->line("  → Header: " . ($domStructure['header']['detected'] ? '✓' : '✗') . " | Hero: " . ($domStructure['hero']['detected'] ? '✓' : '✗') . " | Footer: " . ($domStructure['footer']['detected'] ? '✓' : '✗'));
        } catch (\Throwable $e) {
            $result['status'] = 'phase1_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 1 failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 2: Layout Reconstruction ─────────────────────────────────
        $this->line("  → Phase 2: Layout Reconstruction...");
        try {
            $t = microtime(true);
            $layoutMap = $this->layoutReconstructor->reconstruct($domStructure, $html);
            $result['phase2_time'] = round((microtime(true) - $t) * 1000);
            $result['layout'] = $layoutMap;

            $sectionNames = collect($layoutMap['sections'] ?? [])->pluck('type')->toArray();
            $this->line("  → Layout sections: " . implode(', ', $sectionNames));
            $this->line("  → Layout style: " . ($layoutMap['layout_style'] ?? 'unknown'));
        } catch (\Throwable $e) {
            $result['status'] = 'phase2_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 2 failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 3: Visual Analysis ────────────────────────────────────────
        $this->line("  → Phase 3: Visual Analysis...");
        try {
            $t = microtime(true);
            $visualProfile = $this->visualAnalyzer->analyze($html, $domStructure);
            $result['phase3_time'] = round((microtime(true) - $t) * 1000);
            $result['visual'] = $visualProfile;

            $palette = $visualProfile['color_palette'];
            $this->line("  → Primary color: " . ($palette['primary'] ?? 'unknown'));
            $this->line("  → Colors detected: " . ($palette['count'] ?? 0));
            $this->line("  → Font: " . ($visualProfile['typography']['primary_font'] ?? 'unknown'));
            $this->line("  → Density: " . ($visualProfile['visual_density'] ?? 'unknown'));
            $this->line("  → Framework: " . ($visualProfile['style_framework'] ?? 'unknown'));
        } catch (\Throwable $e) {
            $result['status'] = 'phase3_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 3 failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 4: Commerce Analysis ──────────────────────────────────────
        $this->line("  → Phase 4: Commerce Analysis...");
        try {
            $t = microtime(true);
            $commerceProfile = $this->commerceAnalyzer->analyze($html, $domStructure);
            $result['phase4_time'] = round((microtime(true) - $t) * 1000);
            $result['commerce'] = $commerceProfile;

            $this->line("  → Is commerce: " . ($commerceProfile['is_commerce'] ? 'YES' : 'NO'));
            $this->line("  → Commerce score: " . ($commerceProfile['commerce_score'] ?? 0) . "/100");
            $this->line("  → Products detected: " . ($commerceProfile['product_cards']['count'] ?? 0));
        } catch (\Throwable $e) {
            $result['status'] = 'phase4_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 4 failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 5: Similarity Scoring ─────────────────────────────────────
        $this->line("  → Phase 5: Similarity Scoring...");
        try {
            $t = microtime(true);
            $similarityScores = $this->similarityEngine->score($domStructure, $layoutMap, $visualProfile, $commerceProfile);
            $result['phase5_time'] = round((microtime(true) - $t) * 1000);
            $result['similarity'] = $similarityScores;

            $best = $similarityScores['best_match_label'] ?? '';
            $bestScore = $similarityScores['best_match_score'] ?? 0;
            $this->line("  → Best match: {$best} ({$bestScore}%)");
            $this->line("  → Structure: " . ($similarityScores['structure'] ?? 0) . " | Visual: " . ($similarityScores['visual'] ?? 0) . " | Commerce: " . ($similarityScores['commerce'] ?? 0) . " | Layout: " . ($similarityScores['layout'] ?? 0));
        } catch (\Throwable $e) {
            $result['status'] = 'phase5_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 5 failed: " . $e->getMessage());
            return $result;
        }

        // ── Phase 7 (Quality, no DB needed) ────────────────────────────────
        $this->line("  → Phase 7: Quality Validation...");
        try {
            $t = microtime(true);
            $qualityReport = $this->qualityService->validate(
                $domStructure, $layoutMap, $visualProfile, $commerceProfile, $similarityScores, null
            );
            $result['phase7_time'] = round((microtime(true) - $t) * 1000);
            $result['quality'] = $qualityReport;

            $this->line("  → Quality verdict: " . ($qualityReport['verdict'] ?? 'UNKNOWN'));
            $this->line("  → Quality score: " . ($qualityReport['score'] ?? 0) . "/100");
        } catch (\Throwable $e) {
            $result['status'] = 'phase7_failed';
            $result['error']  = $e->getMessage();
            $this->error("  ✗ Phase 7 failed: " . $e->getMessage());
            return $result;
        }

        $result['status'] = 'complete';
        return $result;
    }

    private function printSummary(string $key, array $result): void
    {
        if ($result['status'] !== 'complete') {
            $this->error("  ✗ {$key}: FAILED ({$result['status']}): " . ($result['error'] ?? ''));
            return;
        }

        $sim = $result['similarity'] ?? [];
        $qual = $result['quality'] ?? [];

        $overall  = $sim['best_match_score'] ?? 0;
        $grade    = $this->gradeScore($overall);
        $verdict  = $qual['verdict'] ?? 'UNKNOWN';

        $this->info("  ✓ {$key}: {$grade} — Overall similarity: {$overall}% — Quality: {$verdict}");
    }

    private function printFinalVerdict(array $results): void
    {
        $this->info('');
        $this->info('════════════════════════════════════════════════════════════');
        $this->info('  FINAL VALIDATION VERDICT');
        $this->info('════════════════════════════════════════════════════════════');

        $allPass  = true;
        $anyExcel = false;

        foreach ($results as $key => $result) {
            if ($result['status'] !== 'complete') {
                $this->line("  {$key}: ✗ PIPELINE_ERROR");
                $allPass = false;
                continue;
            }

            $sim   = $result['similarity']['best_match_score'] ?? 0;
            $grade = $this->gradeScore($sim);
            $icon  = $sim >= 70 ? '✓' : '✗';

            $this->line("  {$key}: {$icon} {$sim}% — {$grade}");

            if ($sim < 70) $allPass = false;
            if ($sim >= 90) $anyExcel = true;
        }

        $this->info('');

        if ($allPass) {
            $verdict = $anyExcel ? 'REVERSE_ENGINEERING_VALIDATED (EXCELLENT)' : 'REVERSE_ENGINEERING_VALIDATED';
            $this->info("  🟢 {$verdict}");
        } else {
            $this->error("  🔴 REVERSE_ENGINEERING_FAILED");
        }

        $this->info('════════════════════════════════════════════════════════════');
    }

    private function gradeScore(int $score): string
    {
        if ($score >= 90) return 'EXCELLENT (90%+)';
        if ($score >= 80) return 'GOOD (80%+)';
        if ($score >= 70) return 'PASS (70%+)';
        if ($score >= 50) return 'PARTIAL (50%+)';
        return 'FAIL (<50%)';
    }
}
