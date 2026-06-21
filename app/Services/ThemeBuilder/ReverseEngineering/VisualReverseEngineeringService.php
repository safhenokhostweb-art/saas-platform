<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

use App\Models\ReverseEngineeringJob;
use App\Models\ThemePack;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Main Orchestrator — Visual Reverse Engineering Engine
 *
 * Runs all 7 phases in sequence:
 *   Phase 1: DomExtractionService
 *   Phase 2: LayoutReconstructionService
 *   Phase 3: VisualAnalysisService
 *   Phase 4: CommerceAnalysisService
 *   Phase 5: SimilarityEngineService
 *   Phase 6: ReverseThemeReconstructorService
 *   Phase 7: ReverseEngineeringQualityService
 */
class VisualReverseEngineeringService
{
    private const CLAUDE_API     = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_VERSION = '2023-06-01';
    private const CLAUDE_MODEL   = 'claude-sonnet-4-6';
    private const TIMEOUT        = 90;

    public function __construct(
        private readonly DomExtractionService            $domExtractor,
        private readonly LayoutReconstructionService     $layoutReconstructor,
        private readonly VisualAnalysisService           $visualAnalyzer,
        private readonly CommerceAnalysisService         $commerceAnalyzer,
        private readonly SimilarityEngineService         $similarityEngine,
        private readonly ReverseThemeReconstructorService $reconstructor,
        private readonly ReverseEngineeringQualityService $qualityService,
    ) {}

    public function isEnabled(): bool
    {
        return !empty(config('services.anthropic.key', ''))
            || !empty(config('services.deepseek.key', ''));
    }

    public function getEnvKeyName(): string
    {
        if (!empty(config('services.anthropic.key', ''))) return 'ANTHROPIC_API_KEY';
        if (!empty(config('services.deepseek.key', ''))) return 'DEEPSEEK_API_KEY';
        return 'ANTHROPIC_API_KEY';
    }

    public function getActiveProvider(): string
    {
        if (!empty(config('services.anthropic.key', ''))) return 'claude';
        if (!empty(config('services.deepseek.key', ''))) return 'deepseek';
        return 'php-fallback';
    }

    // ── Entry points by input type ────────────────────────────────────────────

    /**
     * Reverse-engineer from a URL.
     */
    public function fromUrl(string $url, string $name = '', string $notes = ''): ReverseEngineeringJob
    {
        $job = ReverseEngineeringJob::create([
            'input_type'    => ReverseEngineeringJob::INPUT_URL,
            'input_source'  => $url,
            'input_notes'   => $notes,
            'suggested_name'=> $name ?: $url,
            'status'        => ReverseEngineeringJob::STATUS_EXTRACTING,
        ]);

        try {
            [$html, $finalUrl] = $this->fetchUrl($url);
            return $this->runPipeline($job, $html, $finalUrl, $name ?: $this->inferName($html, $url));
        } catch (\Throwable $e) {
            $job->update(['status' => ReverseEngineeringJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            Log::error('VRE: URL processing failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reverse-engineer from a screenshot (Claude Vision).
     */
    public function fromScreenshot(string $base64Image, string $mimeType, string $name = '', string $notes = ''): ReverseEngineeringJob
    {
        $job = ReverseEngineeringJob::create([
            'input_type'    => ReverseEngineeringJob::INPUT_SCREENSHOT,
            'input_source'  => 'screenshot.' . ($mimeType === 'image/png' ? 'png' : 'jpg'),
            'input_notes'   => $notes,
            'suggested_name'=> $name ?: 'قالب تصویر',
            'status'        => ReverseEngineeringJob::STATUS_EXTRACTING,
        ]);

        try {
            // Phase 1 via Vision
            $visionResult = $this->analyzeScreenshotWithVision($base64Image, $mimeType, $notes);
            $domStructure = $this->domExtractor->extractFromVision($visionResult);
            $finalName    = $name ?: ($visionResult['site_name'] ?? 'قالب تصویر');

            return $this->runPipelineFromDom($job, $domStructure, '', $finalName);
        } catch (\Throwable $e) {
            $job->update(['status' => ReverseEngineeringJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reverse-engineer from raw HTML.
     */
    public function fromHtml(string $html, string $name = '', string $notes = ''): ReverseEngineeringJob
    {
        $job = ReverseEngineeringJob::create([
            'input_type'    => ReverseEngineeringJob::INPUT_HTML,
            'input_source'  => 'uploaded.html',
            'input_notes'   => $notes,
            'suggested_name'=> $name ?: 'قالب HTML',
            'status'        => ReverseEngineeringJob::STATUS_EXTRACTING,
        ]);

        try {
            $finalName = $name ?: $this->inferName($html, '');
            return $this->runPipeline($job, $html, '', $finalName);
        } catch (\Throwable $e) {
            $job->update(['status' => ReverseEngineeringJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Reverse-engineer from text prompt.
     */
    public function fromPrompt(string $prompt, string $name = ''): ReverseEngineeringJob
    {
        $job = ReverseEngineeringJob::create([
            'input_type'    => ReverseEngineeringJob::INPUT_PROMPT,
            'input_source'  => null,
            'input_notes'   => $prompt,
            'suggested_name'=> $name ?: 'قالب توصیف‌شده',
            'status'        => ReverseEngineeringJob::STATUS_EXTRACTING,
        ]);

        try {
            $domStructure   = $this->domExtractor->extractFromPrompt($prompt);
            $finalName      = $name ?: 'قالب توصیف‌شده';
            return $this->runPipelineFromDom($job, $domStructure, '', $finalName);
        } catch (\Throwable $e) {
            $job->update(['status' => ReverseEngineeringJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    // ── Pipeline ──────────────────────────────────────────────────────────────

    private function runPipeline(ReverseEngineeringJob $job, string $html, string $url, string $name): ReverseEngineeringJob
    {
        // Phase 1: DOM Extraction
        $domStructure = $this->domExtractor->extract($html, $url);
        $job->update(['dom_structure' => $domStructure, 'suggested_name' => $name]);

        return $this->runPipelineFromDom($job, $domStructure, $html, $name);
    }

    private function runPipelineFromDom(ReverseEngineeringJob $job, array $domStructure, string $html, string $name): ReverseEngineeringJob
    {
        // Phase 2: Layout Reconstruction
        $job->update(['status' => ReverseEngineeringJob::STATUS_ANALYZING]);
        $layoutMap = empty($html)
            ? $this->layoutReconstructor->reconstructFromSignals($domStructure)
            : $this->layoutReconstructor->reconstruct($domStructure, $html);
        $job->update(['layout_map' => $layoutMap]);

        // Phase 3: Visual Analysis
        $visualProfile = $this->analyzeVisual($html, $domStructure);
        $job->update(['visual_profile' => $visualProfile]);

        // Phase 4: Commerce Analysis
        $commerceProfile = $this->analyzeCommerce($html, $domStructure, $job->input_type);
        $job->update(['commerce_profile' => $commerceProfile]);

        // Phase 5: Similarity Scoring
        $job->update(['status' => ReverseEngineeringJob::STATUS_SCORING]);
        $similarityScores = $this->similarityEngine->score(
            $domStructure,
            $layoutMap,
            $visualProfile,
            $commerceProfile,
        );
        $job->update(['similarity_scores' => $similarityScores]);

        // Phase 6: Theme Reconstruction
        $job->update(['status' => ReverseEngineeringJob::STATUS_RECONSTRUCTING]);
        $apiKey    = (string) config('services.anthropic.key', '');
        $themePack = $this->reconstructor->reconstruct(
            $name,
            $domStructure,
            $layoutMap,
            $visualProfile,
            $commerceProfile,
            $similarityScores,
            $apiKey,
        );
        $job->update(['theme_pack_id' => $themePack->id]);

        // Phase 7: Quality Validation
        $job->update(['status' => ReverseEngineeringJob::STATUS_VALIDATING]);
        $qualityReport = $this->qualityService->validate(
            $domStructure,
            $layoutMap,
            $visualProfile,
            $commerceProfile,
            $similarityScores,
            $themePack,
        );
        $job->update([
            'quality_report' => $qualityReport,
            'status'         => ReverseEngineeringJob::STATUS_COMPLETE,
            'meta'           => [
                'name'         => $name,
                'best_match'   => $similarityScores['best_match'] ?? '',
                'best_score'   => $similarityScores['best_match_score'] ?? 0,
                'quality'      => $qualityReport['verdict'] ?? 'UNKNOWN',
                'quality_score'=> $qualityReport['score'] ?? 0,
            ],
        ]);

        return $job->fresh();
    }

    // ── Visual analysis dispatch ──────────────────────────────────────────────

    private function analyzeVisual(string $html, array $dom): array
    {
        if (!empty($html)) {
            return $this->visualAnalyzer->analyze($html, $dom);
        }
        // Vision result stored in dom
        if ($dom['source'] === 'vision') {
            return $this->visualAnalyzer->analyzeFromVision($dom['visual_hints'] ?? []);
        }
        return $this->visualAnalyzer->analyzeFromPrompt($dom['meta']['description'] ?? '');
    }

    private function analyzeCommerce(string $html, array $dom, string $inputType): array
    {
        if (!empty($html)) {
            return $this->commerceAnalyzer->analyze($html, $dom);
        }
        if ($inputType === ReverseEngineeringJob::INPUT_SCREENSHOT) {
            return $this->commerceAnalyzer->analyzeFromVision($dom['visual_hints'] ?? []);
        }
        return $this->commerceAnalyzer->analyzeFromPrompt($dom['meta']['description'] ?? '');
    }

    // ── URL fetcher ───────────────────────────────────────────────────────────

    private function fetchUrl(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (compatible; SaaS-ReverseEngineer-Bot/1.0)',
            'Accept'          => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'fa,en;q=0.9',
        ])->timeout(20)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}: دریافت صفحه ناموفق بود.");
        }

        return [$response->body(), $response->effectiveUri()?->__toString() ?? $url];
    }

    private function inferName(string $html, string $url): string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        if ($url) {
            $host = parse_url($url, PHP_URL_HOST) ?? '';
            return str_replace(['www.', '.com', '.ir', '.io', '.co'], '', $host);
        }
        return 'قالب بازسازی‌شده';
    }

    // ── Claude Vision ─────────────────────────────────────────────────────────

    private function analyzeScreenshotWithVision(string $base64Image, string $mimeType, string $notes): array
    {
        $apiKey = (string) config('services.anthropic.key', '');
        if (empty($apiKey)) {
            return $this->screenshotFallback($notes);
        }

        $systemPrompt = <<<SYS
You are an expert web designer AI specializing in visual reverse engineering.
Analyze this website screenshot and extract complete design information.
Return ONLY valid JSON — no markdown, no explanation.
SYS;

        $userText = <<<USR
Analyze this website screenshot for reverse engineering. Return JSON:
{
  "site_name": "name if visible",
  "site_type": "saas|ecommerce|portfolio|corporate|blog",
  "description": "brief description",
  "sections_detected": ["header","hero","features","pricing","testimonials","faq","blog","products","cta","footer"],
  "section_headings": ["h2 text 1","h2 text 2"],
  "nav_items": ["Home","About","Contact"],
  "hero_text": "main headline if visible",
  "colors": ["#hex1","#hex2","#hex3","#hex4","#hex5"],
  "primary_color": "#hex",
  "style": "modern|minimal|corporate|creative|technical",
  "layout": "single-column|editorial|full-bleed|bento",
  "hero_layout": "centered|split|standard",
  "has_pricing": true,
  "has_testimonials": true,
  "has_products": true,
  "has_blog": true,
  "has_faq": false,
  "is_rtl": false,
  "product_grid_cols": 4,
  "card_border_radius": "md",
  "has_animations": true,
  "visual_density": "tight|standard|spacious"
}
Additional notes: {$notes}
USR;

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::CLAUDE_VERSION,
            'content-type'      => 'application/json',
        ])->timeout(self::TIMEOUT)->post(self::CLAUDE_API, [
            'model'      => self::CLAUDE_MODEL,
            'max_tokens' => 1500,
            'system'     => $systemPrompt,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64Image]],
                    ['type' => 'text', 'text' => $userText],
                ],
            ]],
        ]);

        if (!$response->successful()) {
            Log::warning('VRE: Vision API failed', ['status' => $response->status()]);
            return $this->screenshotFallback($notes);
        }

        $raw  = $response->json('content.0.text') ?? '{}';
        $raw  = $this->stripFences($raw);
        $data = json_decode($raw, true) ?? [];

        return array_merge($this->screenshotFallback($notes), $data);
    }

    private function screenshotFallback(string $notes): array
    {
        return [
            'site_name'          => '',
            'site_type'          => 'general',
            'description'        => $notes,
            'sections_detected'  => ['header', 'hero', 'features', 'cta', 'footer'],
            'section_headings'   => [],
            'nav_items'          => ['خانه', 'درباره ما', 'خدمات', 'تماس'],
            'hero_text'          => '',
            'colors'             => ['#3b82f6'],
            'primary_color'      => '#3b82f6',
            'style'              => 'modern',
            'layout'             => 'single-column',
            'hero_layout'        => 'centered',
            'has_pricing'        => false,
            'has_testimonials'   => false,
            'has_products'       => false,
            'has_blog'           => false,
            'has_faq'            => false,
            'is_rtl'             => false,
        ];
    }

    private function stripFences(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m)) return $m[1];
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($raw, $start, $end - $start + 1);
        }
        return $raw;
    }
}
