<?php

namespace App\Services\ThemeBuilder;

use App\Models\AiGenerationJob;
use App\Models\ThemePack;
use App\Models\ThemeTemplate;
use App\Services\AI\GapGptClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TB3 Sprint 8 — AI Theme Generator (GapGPT Exclusive)
 */
class ThemeAiGeneratorService
{
    private const MAX_TOKENS = 8000;
    private const TIMEOUT    = 90;

    public function __construct(
        private readonly ThemeSectionDetectorService $detector,
        private readonly ThemePlaceholderService $placeholder,
        private readonly ?GapGptClient $gapGpt = null,
    ) {}

    private function client(): GapGptClient
    {
        return $this->gapGpt ?? app(GapGptClient::class);
    }

    public function isEnabled(): bool
    {
        return $this->client()->isEnabled();
    }

    public function getProviderLabel(): string
    {
        return 'GapGPT AI';
    }

    public function getEnvKeyName(): string
    {
        return 'GAPGPT_API_KEY';
    }

    // ── STEP 1: Detect sections from input ───────────────────────────────────

    /**
     * Process URL input: crawl → extract → detect sections.
     * Returns an AiGenerationJob in STATUS_AWAITING state.
     */
    public function fromUrl(string $url, string $notes = '', string $suggestedName = ''): AiGenerationJob
    {
        $job = AiGenerationJob::create([
            'input_type'    => AiGenerationJob::INPUT_URL,
            'input_source'  => $url,
            'input_notes'   => $notes,
            'suggested_name'=> $suggestedName,
            'status'        => AiGenerationJob::STATUS_DETECTING,
        ]);

        try {
            $pageData        = $this->fetchAndExtractUrl($url);
            $detectedSections = $this->detector->detect($pageData);

            // Infer name from page title if not provided
            if (empty($suggestedName) && !empty($pageData['title'])) {
                $suggestedName = $pageData['title'];
            }

            $job->update([
                'page_signals'      => $pageData,
                'detected_sections' => $detectedSections,
                'suggested_name'    => $suggestedName ?: 'قالب هوش مصنوعی',
                'status'            => AiGenerationJob::STATUS_AWAITING,
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => AiGenerationJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    /**
     * Process screenshot input: Claude Vision → detect sections.
     * $imageData is base64-encoded image content with MIME prefix.
     */
    public function fromScreenshot(string $imageData, string $mimeType, string $notes = '', string $suggestedName = ''): AiGenerationJob
    {
        $job = AiGenerationJob::create([
            'input_type'    => AiGenerationJob::INPUT_SCREENSHOT,
            'input_source'  => 'screenshot.' . ($mimeType === 'image/png' ? 'png' : 'jpg'),
            'input_notes'   => $notes,
            'suggested_name'=> $suggestedName,
            'status'        => AiGenerationJob::STATUS_DETECTING,
        ]);

        try {
            $visionResult     = $this->analyzeScreenshot($imageData, $mimeType, $notes);
            $detectedSections = $this->detector->detectFromAiVisionResult($visionResult);

            if (empty($suggestedName) && !empty($visionResult['site_name'])) {
                $suggestedName = $visionResult['site_name'];
            }

            $job->update([
                'page_signals'      => $visionResult,
                'detected_sections' => $detectedSections,
                'suggested_name'    => $suggestedName ?: 'قالب تولیدشده از تصویر',
                'status'            => AiGenerationJob::STATUS_AWAITING,
                'meta'              => ['input_method' => 'vision', 'provider' => $this->provider],
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => AiGenerationJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    /**
     * Process HTML upload: parse → extract → detect sections.
     */
    public function fromHtml(string $htmlContent, string $notes = '', string $suggestedName = ''): AiGenerationJob
    {
        $job = AiGenerationJob::create([
            'input_type'    => AiGenerationJob::INPUT_HTML,
            'input_source'  => 'uploaded.html',
            'input_notes'   => $notes,
            'suggested_name'=> $suggestedName,
            'status'        => AiGenerationJob::STATUS_DETECTING,
        ]);

        try {
            $pageData        = $this->extractFromHtml($htmlContent);
            $detectedSections = $this->detector->detect($pageData);

            if (empty($suggestedName) && !empty($pageData['title'])) {
                $suggestedName = $pageData['title'];
            }

            $job->update([
                'page_signals'      => $pageData,
                'detected_sections' => $detectedSections,
                'suggested_name'    => $suggestedName ?: 'قالب تولیدشده از HTML',
                'status'            => AiGenerationJob::STATUS_AWAITING,
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => AiGenerationJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    /**
     * Process text prompt: detect from description → prepare job.
     */
    public function fromPrompt(string $description, string $suggestedName = ''): AiGenerationJob
    {
        $job = AiGenerationJob::create([
            'input_type'    => AiGenerationJob::INPUT_PROMPT,
            'input_source'  => null,
            'input_notes'   => $description,
            'suggested_name'=> $suggestedName ?: 'قالب توصیف‌شده',
            'status'        => AiGenerationJob::STATUS_DETECTING,
        ]);

        try {
            $detectedSections = $this->detector->detectFromPrompt($description);

            $job->update([
                'page_signals'      => ['description' => $description],
                'detected_sections' => $detectedSections,
                'status'            => AiGenerationJob::STATUS_AWAITING,
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => AiGenerationJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            throw $e;
        }

        return $job->fresh();
    }

    // ── STEP 2: Generate theme pack from selected sections ────────────────────

    /**
     * Generate a complete TB3 ThemePack from the selected sections.
     *
     * @param AiGenerationJob $job          The job in AWAITING state
     * @param array           $selectedKeys Which section keys to include (user selection)
     * @param string          $name         Final theme pack name
     * @return ThemePack
     */
    public function generateThemePack(AiGenerationJob $job, array $selectedKeys, string $name): ThemePack
    {
        $job->update([
            'status'           => AiGenerationJob::STATUS_GENERATING,
            'selected_sections' => $this->buildSelectedSections($job->detected_sections ?? [], $selectedKeys),
        ]);

        try {
            $context = [
                'site_name'    => $name,
                'description'  => $job->input_notes ?? '',
                'page_signals' => $job->page_signals ?? [],
            ];

            // Build global styles (extract color hints from page signals if available)
            $globalStyles = $this->inferGlobalStyles($job->page_signals ?? [], $selectedKeys);

            // Call AI to generate block structures for each section
            $packJson = $this->callAiForThemePack($name, $selectedKeys, $context, $globalStyles);

            // Build ThemePack from AI response
            $themePack = $this->buildThemePack($packJson, $name, $job->input_type);

            // Create individual ThemeTemplates for each section
            $this->createSectionTemplates($packJson, $name, $selectedKeys);

            $job->update([
                'status'        => AiGenerationJob::STATUS_COMPLETE,
                'theme_pack_id' => $themePack->id,
                'meta'          => array_merge($job->meta ?? [], [
                    'provider'      => $this->provider,
                    'pack_id'       => $themePack->id,
                    'sections_generated' => count($selectedKeys),
                ]),
            ]);

            return $themePack;

        } catch (\Throwable $e) {
            $job->update(['status' => AiGenerationJob::STATUS_FAILED, 'error_message' => $e->getMessage()]);
            Log::error('TB8: Theme pack generation failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // ── Private: URL fetch & extraction ──────────────────────────────────────

    private function fetchAndExtractUrl(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (compatible; SaaS-Theme-Bot/1.0)',
            'Accept'          => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'fa,en;q=0.9',
        ])->timeout(15)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}: دریافت صفحه ناموفق بود.");
        }

        return $this->extractFromHtml($response->body(), $url);
    }

    private function extractFromHtml(string $html, string $url = ''): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_use_internal_errors($prev);
        $xpath = new \DOMXPath($doc);

        $title       = $this->xText($xpath, '//title');
        $desc        = $this->xAttr($xpath, '//meta[@name="description"]', 'content');
        $keywords    = $this->xAttr($xpath, '//meta[@name="keywords"]', 'content');
        $h1          = $this->xTextAll($xpath, '//h1', 3);
        $h2          = $this->xTextAll($xpath, '//h2', 8);
        $navLinks    = $this->xTextAll($xpath, '//nav//a', 10);
        $footerLinks = $this->xTextAll($xpath, '//footer//a', 8);
        $themeColor  = $this->xAttr($xpath, '//meta[@name="theme-color"]', 'content');
        $ogImage     = $this->xAttr($xpath, '//meta[@property="og:image"]', 'content');

        // Extract inline style color hints
        $styleAttrs = [];
        foreach ($xpath->query('//*[@style]') as $node) {
            $styleAttrs[] = $node->getAttribute('style');
            if (count($styleAttrs) >= 15) break;
        }

        // Extract structural landmarks
        $hasPricing     = $this->containsKeywords($h2, ['price', 'pricing', 'plan', 'قیمت', 'طرح']);
        $hasTestimonials = $this->containsKeywords($h2, ['testimonial', 'review', 'نظر', 'بازخورد']);
        $hasFaq         = $this->containsKeywords($h2, ['faq', 'question', 'سوال']);
        $hasProducts    = $this->containsKeywords($h2, ['product', 'shop', 'محصول', 'فروشگاه']);
        $hasBlog        = $this->containsKeywords($h2, ['blog', 'article', 'news', 'وبلاگ', 'مطلب', 'خبر']);

        return [
            'url'          => $url,
            'title'        => $title,
            'description'  => $desc,
            'keywords'     => $keywords,
            'h1'           => $h1,
            'h2'           => $h2,
            'nav_links'    => $navLinks,
            'footer_links' => $footerLinks,
            'theme_color'  => $themeColor,
            'og_image'     => $ogImage,
            'style_hints'  => implode(' ', array_slice($styleAttrs, 0, 10)),
            'signals'      => compact('hasPricing', 'hasTestimonials', 'hasFaq', 'hasProducts', 'hasBlog'),
        ];
    }

    private function xText(\DOMXPath $x, string $q): string
    {
        $n = $x->query($q);
        return $n && $n->length > 0 ? trim($n->item(0)->textContent) : '';
    }

    private function xAttr(\DOMXPath $x, string $q, string $attr): string
    {
        $n = $x->query($q);
        return $n && $n->length > 0 ? trim($n->item(0)->getAttribute($attr)) : '';
    }

    private function xTextAll(\DOMXPath $x, string $q, int $limit): array
    {
        $result = [];
        $nodes  = $x->query($q);
        if (!$nodes) return $result;
        foreach ($nodes as $n) {
            $t = trim($n->textContent);
            if ($t) { $result[] = $t; }
            if (count($result) >= $limit) break;
        }
        return $result;
    }

    private function containsKeywords(array $texts, array $keywords): bool
    {
        $corpus = strtolower(implode(' ', $texts));
        foreach ($keywords as $kw) {
            if (str_contains($corpus, strtolower($kw))) return true;
        }
        return false;
    }

    // ── Private: Screenshot (Claude Vision) ──────────────────────────────────

    private function analyzeScreenshot(string $imageData, string $mimeType, string $notes): array
    {
        if ($this->provider !== 'claude') {
            // GapGPT vision not supported — fall back to prompt-based
            return $this->analyzeScreenshotViaPrompt($notes);
        }

        $system = <<<SYS
You are an expert web designer AI. Analyze this website screenshot and extract design information.
Return ONLY valid JSON — no markdown, no explanation.
SYS;

        $userText = <<<USR
Analyze this website screenshot and return a JSON object with:
{
  "site_name": "name of the site if visible",
  "site_type": "business type (e.g. SaaS, ecommerce, portfolio)",
  "description": "brief description of what this site does",
  "sections_detected": ["header", "hero", "features", ...],
  "section_headings": ["heading1", "heading2"],
  "nav_items": ["Home", "About", "Services"],
  "hero_text": "main headline if visible",
  "colors": ["#hex1", "#hex2"],
  "primary_color": "#hex",
  "style": "modern/minimal/corporate/creative",
  "layout": "single-column/multi-column",
  "has_pricing": true/false,
  "has_testimonials": true/false,
  "has_products": true/false,
  "has_blog": true/false
}
Additional notes: {$notes}
USR;

        $raw  = $this->client()->askWithImage(
            prompt: $userText,
            base64Image: $imageData,
            mimeType: $mimeType,
            system: $system,
            maxTokens: 1024,
            timeout: self::TIMEOUT,
        );
        $raw  = $this->stripJsonFences($raw);
        $data = json_decode($raw, true) ?? [];

        return $data;
    }

    private function analyzeScreenshotViaPrompt(string $notes): array
    {
        return [
            'site_name'          => '',
            'description'        => $notes,
            'sections_detected'  => ['header', 'hero', 'features', 'cta', 'footer'],
            'section_headings'   => [],
            'nav_items'          => [],
            'hero_text'          => '',
            'colors'             => [],
            'primary_color'      => '#3b82f6',
        ];
    }

    // ── Private: AI generation ────────────────────────────────────────────────

    private function inferGlobalStyles(array $pageSignals, array $sections): array
    {
        $primaryColor = $pageSignals['theme_color'] ?? '';

        // Basic color validation
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            $primaryColor = '#3b82f6'; // Default indigo
        }

        return [
            'primary_color'    => $primaryColor,
            'secondary_color'  => '#8b5cf6',
            'accent_color'     => '#f59e0b',
            'background_color' => '#ffffff',
            'text_color'       => '#111827',
            'font_family'      => 'Vazirmatn',
            'border_radius'    => 'md',
            'direction'        => 'rtl',
        ];
    }

    private function callAiForThemePack(string $name, array $sections, array $context, array $globalStyles): array
    {
        $sectionList   = implode(', ', $sections);
        $stylesJson    = json_encode($globalStyles, JSON_UNESCAPED_UNICODE);
        $contextJson   = json_encode([
            'site_name'   => $name,
            'description' => $context['description'] ?? '',
            'signals'     => array_keys(array_filter($context['page_signals']['signals'] ?? [])),
        ], JSON_UNESCAPED_UNICODE);

        $blockTypes    = implode(', ', [
            'hero', 'heading', 'text', 'image', 'button', 'divider', 'spacer',
            'card', 'card_grid', 'feature_list', 'icon_box', 'testimonial',
            'faq', 'stats', 'team', 'cta', 'banner', 'logo_bar', 'newsletter',
            'contact_form', 'nav_header', 'nav_footer', 'nav_logo', 'nav_links',
            'pricing_cards', 'features_grid', 'hero_split', 'hero_gradient',
            'stats_row', 'testimonials_slider', 'bento_grid', 'services_cards',
        ]);

        $system = <<<SYS
You are a professional Persian (RTL/Farsi) web theme designer AI for TB3 Theme Builder.
Your task: generate a complete, ready-to-use theme pack JSON for a Persian website.

CRITICAL RULES:
1. Return ONLY valid JSON — no markdown, no explanation, no code fences.
2. All text content must be in Persian (Farsi), even if source is English.
3. Layout direction is always RTL (right-to-left).
4. Font family is always "Vazirmatn".
5. Generate ORIGINAL placeholder content — NEVER copy from the source.
6. Use only these block types: {$blockTypes}
7. Each block must have: "id" (UUID), "type", "data" (props object).
8. Generate realistic, professional content — not just Lorem Ipsum.
SYS;

        $user = <<<USR
Generate a complete Persian TB3 Theme Pack for: "{$name}"

Context: {$contextJson}
Sections to generate: {$sectionList}
Global styles to use as base: {$stylesJson}

Return JSON with EXACTLY this structure:
{
  "pack_name": "{$name}",
  "description": "توضیح فارسی قالب",
  "global_styles": {
    "primary_color": "#hex",
    "secondary_color": "#hex",
    "accent_color": "#hex",
    "background_color": "#ffffff",
    "text_color": "#111827",
    "font_family": "Vazirmatn",
    "border_radius": "md",
    "direction": "rtl"
  },
  "responsive_settings": {
    "desktop": { "base_font_size": "16px", "container_max_width": "1280px" },
    "tablet":  { "base_font_size": "15px", "container_max_width": "768px" },
    "mobile":  { "base_font_size": "14px", "container_max_width": "100%" }
  },
  "headers": [
    {
      "name": "هدر اصلی",
      "blocks": [
        { "id": "UUID", "type": "nav_header", "data": { "logo_text": "نام سایت", "logo_url": "/", "sticky": true, "background": "#ffffff", "links": [{"text":"خانه","url":"/"}], "cta_text": "شروع رایگان", "cta_url": "#" } }
      ]
    }
  ],
  "footers": [
    {
      "name": "فوتر اصلی",
      "blocks": [
        { "id": "UUID", "type": "nav_footer", "data": { "logo_text": "نام سایت", "columns": [{"title":"لینک‌ها","links":[{"text":"خانه","url":"/"}]}], "copyright": "© ۱۴۰۳ همه حقوق محفوظ" } }
      ]
    }
  ],
  "pages": [
    {
      "title": "صفحه اصلی",
      "slug": "",
      "blocks": [
        // hero block, then other enabled sections as blocks
      ]
    }
  ],
  "templates": [
    { "name": "هیرو اصلی", "type": "section", "category": "hero", "blocks": [...] },
    { "name": "ویژگی‌ها", "type": "section", "category": "features", "blocks": [...] }
    // one template per non-structural section
  ],
  "demo_data": {
    "site_name": "{$name}",
    "tagline": "شعار سایت"
  }
}

IMPORTANT:
- Replace all "UUID" with actual unique UUID strings like "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
- Generate 6-10 blocks for the home page covering all enabled sections
- Each template has 2-4 blocks for its section type
- Make all text professional Persian business language
- Use appropriate Persian business writing style (formal, professional)
USR;

        $raw = $this->callAi($system, $user);
        $raw = $this->stripJsonFences($raw);

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning('TB8: AI returned invalid JSON, using fallback', ['error' => $e->getMessage()]);
            $data = $this->buildFallbackPackData($name, $sections, $globalStyles);
        }

        return $this->normalizePackData($data, $globalStyles);
    }

    private function callAi(string $system, string $user): string
    {
        return $this->client()->ask(
            prompt: $user,
            system: $system,
            maxTokens: self::MAX_TOKENS,
            timeout: self::TIMEOUT,
        );
    }

    // ── Private: Build ThemePack ──────────────────────────────────────────────

    private function buildThemePack(array $packJson, string $name, string $inputType): ThemePack
    {
        $slug = Str::slug($name) . '-ai-' . Str::lower(Str::random(6));

        return ThemePack::create([
            'name'          => $name,
            'description'   => $packJson['description'] ?? "قالب تولیدشده با هوش مصنوعی از ورودی {$inputType}",
            'version'       => '1.0.0',
            'author'        => 'AI Generator (TB3 Sprint 8)',
            'license_type'  => 'free',
            'tags'          => ['ai-generated', 'tb3-sprint8', $inputType],
            'is_public'     => false,
            'is_active'     => true,
            'site_id'       => null,
            'pack_data'     => [
                'global_styles'       => $packJson['global_styles'] ?? [],
                'responsive_settings' => $packJson['responsive_settings'] ?? [],
                'headers'             => $packJson['headers'] ?? [],
                'footers'             => $packJson['footers'] ?? [],
                'pages'               => $packJson['pages'] ?? [],
                'templates'           => $packJson['templates'] ?? [],
                'demo_data'           => $packJson['demo_data'] ?? [],
            ],
        ]);
    }

    private function createSectionTemplates(array $packJson, string $packName, array $sections): void
    {
        $templates = $packJson['templates'] ?? [];

        foreach ($templates as $tpl) {
            if (empty($tpl['blocks'])) continue;

            $category = $tpl['category'] ?? 'hero';
            $type     = $tpl['type']     ?? 'section';

            ThemeTemplate::create([
                'type'         => in_array($type, array_keys(ThemeTemplate::TYPES)) ? $type : 'section',
                'category'     => in_array($category, array_keys(ThemeTemplate::CATEGORIES)) ? $category : 'hero',
                'name'         => $tpl['name'] ?? "قالب {$packName}",
                'description'  => "تولیدشده از پک: {$packName}",
                'tags'         => ['ai-generated', 'tb3-sprint8'],
                'version'      => '1.0.0',
                'license_type' => 'free',
                'blocks'       => $this->normalizeBlocks($tpl['blocks']),
                'is_public'    => false,
                'is_active'    => true,
                'site_id'      => null,
            ]);
        }
    }

    private function normalizeBlocks(array $blocks): array
    {
        return array_map(function (array $block): array {
            $block['id']   = $block['id']   ?? (string) Str::uuid();
            $block['type'] = $block['type'] ?? 'text';
            $props         = $block['data'] ?? $block['props'] ?? [];
            unset($block['props']);
            $block['data'] = $props;
            return $block;
        }, $blocks);
    }

    private function buildSelectedSections(array $detected, array $selectedKeys): array
    {
        return array_map(function (array $section) use ($selectedKeys): array {
            $section['enabled'] = in_array($section['key'], $selectedKeys, true);
            return $section;
        }, $detected);
    }

    private function normalizePackData(array $data, array $defaultStyles): array
    {
        $data['global_styles'] = array_merge($defaultStyles, $data['global_styles'] ?? []);

        if (!isset($data['responsive_settings'])) {
            $data['responsive_settings'] = [
                'desktop' => ['base_font_size' => '16px', 'container_max_width' => '1280px'],
                'tablet'  => ['base_font_size' => '15px', 'container_max_width' => '768px'],
                'mobile'  => ['base_font_size' => '14px', 'container_max_width' => '100%'],
            ];
        }

        // Normalize blocks inside headers, footers, pages
        foreach (['headers', 'footers'] as $zone) {
            foreach ($data[$zone] ?? [] as &$entry) {
                $entry['blocks'] = $this->normalizeBlocks($entry['blocks'] ?? []);
            }
            unset($entry);
        }

        foreach ($data['pages'] ?? [] as &$page) {
            $page['blocks'] = $this->normalizeBlocks($page['blocks'] ?? []);
        }
        unset($page);

        return $data;
    }

    private function buildFallbackPackData(string $name, array $sections, array $styles): array
    {
        // Pure-PHP fallback when AI returns invalid JSON
        // Uses ThemePlaceholderService to build realistic Persian content
        $headerData = $this->placeholder->header(['site_name' => $name]);
        $footerData = $this->placeholder->footer(['site_name' => $name]);

        $homeBlocks = [];
        $templates  = [];

        foreach ($sections as $sectionKey) {
            if (in_array($sectionKey, ['header', 'footer'], true)) continue;

            $phData = $this->placeholder->forSection($sectionKey, ['site_name' => $name]);
            $blockType = $this->sectionToBlockType($sectionKey);
            $blockId   = (string) Str::uuid();

            $block = ['id' => $blockId, 'type' => $blockType, 'data' => $phData];
            $homeBlocks[] = $block;

            $templates[] = [
                'name'     => $this->sectionLabel($sectionKey),
                'type'     => 'section',
                'category' => $sectionKey === 'cta' ? 'hero' : $sectionKey,
                'blocks'   => [$block],
            ];
        }

        return [
            'pack_name'     => $name,
            'description'   => "قالب {$name} — تولیدشده با هوش مصنوعی",
            'global_styles' => $styles,
            'responsive_settings' => [
                'desktop' => ['base_font_size' => '16px', 'container_max_width' => '1280px'],
                'tablet'  => ['base_font_size' => '15px', 'container_max_width' => '768px'],
                'mobile'  => ['base_font_size' => '14px', 'container_max_width' => '100%'],
            ],
            'headers'   => [['name' => 'هدر اصلی', 'blocks' => [
                ['id' => (string) Str::uuid(), 'type' => 'nav_header', 'data' => $headerData],
            ]]],
            'footers'   => [['name' => 'فوتر اصلی', 'blocks' => [
                ['id' => (string) Str::uuid(), 'type' => 'nav_footer', 'data' => $footerData],
            ]]],
            'pages'     => [['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => $homeBlocks]],
            'templates' => $templates,
            'demo_data' => ['site_name' => $name],
        ];
    }

    private function sectionToBlockType(string $key): string
    {
        return match ($key) {
            'hero'         => 'hero',
            'features'     => 'feature_list',
            'services'     => 'services_cards',
            'pricing'      => 'pricing_cards',
            'faq'          => 'faq',
            'testimonials' => 'testimonial',
            'blog'         => 'card_grid',
            'products'     => 'card_grid',
            'cta'          => 'cta',
            default        => 'text',
        };
    }

    private function sectionLabel(string $key): string
    {
        $labels = [
            'hero'         => 'هیرو — بنر اصلی',
            'features'     => 'ویژگی‌ها',
            'services'     => 'خدمات',
            'pricing'      => 'قیمت‌گذاری',
            'faq'          => 'سوالات متداول',
            'testimonials' => 'نظرات مشتریان',
            'blog'         => 'وبلاگ',
            'products'     => 'محصولات',
            'cta'          => 'فراخوان عمل',
        ];
        return $labels[$key] ?? $key;
    }

    private function stripJsonFences(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m)) {
            return $m[1];
        }
        return $raw;
    }
}
