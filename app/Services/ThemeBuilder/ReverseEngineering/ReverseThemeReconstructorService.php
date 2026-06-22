<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

use App\Models\ThemePack;
use App\Models\ThemeTemplate;
use App\Services\ThemeBuilder\ThemePlaceholderService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Phase 6 — Theme Reconstruction
 *
 * Generates a complete ThemePack using:
 *   - Detected DOM structure
 *   - Detected layout map
 *   - Detected visual profile (colors, typography)
 *   - Detected commerce patterns
 *   - Similarity scores
 *
 * SAFE MODE: Never copies HTML, CSS, images, or copyrighted content.
 * All text content is original Persian placeholder text.
 * All images are inline SVG data URIs.
 */
class ReverseThemeReconstructorService
{
    // Claude API constants
    private const CLAUDE_API     = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_MODEL   = 'claude-sonnet-4-6';
    private const CLAUDE_VERSION = '2023-06-01';
    private const MAX_TOKENS     = 8000;
    private const TIMEOUT        = 120;

    // DeepSeek API constants (OpenAI-compatible)
    private const DEEPSEEK_API   = 'https://api.deepseek.com/v1/chat/completions';
    private const DEEPSEEK_MODEL = 'deepseek-chat';

    public function __construct(
        private readonly ThemePlaceholderService $placeholder,
    ) {}

    /**
     * Reconstruct a ThemePack from all analysis phases.
     *
     * @param string $name             Desired pack name
     * @param array  $domStructure     Phase 1
     * @param array  $layoutMap        Phase 2
     * @param array  $visualProfile    Phase 3
     * @param array  $commerceProfile  Phase 4
     * @param array  $similarityScores Phase 5
     * @param string $apiKey           Anthropic API key (optional — falls back to PHP)
     * @return ThemePack
     */
    public function reconstruct(
        string $name,
        array $domStructure,
        array $layoutMap,
        array $visualProfile,
        array $commerceProfile,
        array $similarityScores,
        string $apiKey = ''
    ): ThemePack {
        // Build global styles from visual profile
        $globalStyles = $this->buildGlobalStyles($visualProfile, $similarityScores);

        // Determine which sections to generate
        $sections = array_map(fn ($s) => $s['type'], $layoutMap['sections'] ?? []);
        $sections = array_unique(array_filter($sections));
        if (empty($sections)) {
            $sections = ['header', 'hero', 'features', 'cta', 'footer'];
        }

        // Try AI-assisted pack generation, fall back to PHP
        $packJson    = null;
        $deepseekKey = (string) config('services.deepseek.key', '');

        if (!empty($apiKey)) {
            try {
                $packJson = $this->aiGeneratePack($name, $sections, $globalStyles, $layoutMap, $visualProfile, $commerceProfile, $similarityScores, $apiKey);
            } catch (\Throwable $e) {
                Log::warning('RE: Claude reconstruction failed, trying DeepSeek', ['error' => $e->getMessage()]);
            }
        }

        if ($packJson === null && !empty($deepseekKey)) {
            try {
                $packJson = $this->aiGeneratePackDeepSeek($name, $sections, $globalStyles, $layoutMap, $visualProfile, $commerceProfile, $similarityScores, $deepseekKey);
                Log::info('RE: DeepSeek reconstruction succeeded');
            } catch (\Throwable $e) {
                Log::warning('RE: DeepSeek reconstruction failed, using PHP fallback', ['error' => $e->getMessage()]);
            }
        }

        if ($packJson === null) {
            $packJson = $this->phpBuildPack($name, $sections, $globalStyles, $layoutMap, $commerceProfile);
        }

        return $this->persistThemePack($packJson, $name, $similarityScores);
    }

    // ── AI Pack Generation ────────────────────────────────────────────────────

    private function aiGeneratePack(
        string $name,
        array  $sections,
        array  $globalStyles,
        array  $layoutMap,
        array  $visualProfile,
        array  $commerceProfile,
        array  $similarityScores,
        string $apiKey
    ): ?array {
        $bestMatch   = $similarityScores['best_match'] ?? 'stripe';
        $bestScore   = $similarityScores['best_match_score'] ?? 0;
        $isCommerce  = $commerceProfile['is_commerce'] ?? false;
        $sectionList = implode(', ', $sections);
        $stylesJson  = json_encode($globalStyles, JSON_UNESCAPED_UNICODE);
        $colorPalette = $visualProfile['color_palette'] ?? [];
        $typography   = $visualProfile['typography'] ?? [];

        $system = <<<SYS
You are a professional Persian (RTL/Farsi) web theme designer AI.
Generate a complete TB3 ThemePack JSON for a Persian website.

RULES:
1. Return ONLY valid JSON — no markdown, no code fences.
2. ALL text must be in Persian (Farsi).
3. Direction is always RTL. Font is always Vazirmatn.
4. Generate ORIGINAL content — never copy source material.
5. Use only these block types: hero, heading, text, image, button, divider, spacer, card, card_grid, feature_list, icon_box, testimonial, faq, stats, stats_row, team, cta, banner, logo_bar, newsletter, contact_form, nav_header, nav_footer, pricing_cards, features_grid, hero_split, hero_gradient, testimonials_slider, bento_grid, services_cards, contact_split
6. Each block: { "id": "UUID", "type": "...", "data": { ... } }
7. Images: use placeholder descriptions, NOT real URLs.
SYS;

        $user = <<<USR
Reverse-engineer and reconstruct a Persian website theme based on this analysis:

Site name: "{$name}"
Best structural match: {$bestMatch} (score: {$bestScore}/100)
Sections to generate: {$sectionList}
Is commerce site: {$isCommerce}
Global styles: {$stylesJson}
Primary color: {$colorPalette['primary']}
Font: {$typography['primary_font']}

Generate a complete Persian ThemePack JSON:
{
  "pack_name": "{$name}",
  "description": "توضیح فارسی قالب",
  "global_styles": { "primary_color": "...", "secondary_color": "...", "accent_color": "...", "background_color": "...", "text_color": "...", "font_family": "Vazirmatn", "border_radius": "...", "direction": "rtl" },
  "responsive_settings": { "desktop": { "base_font_size": "16px", "container_max_width": "1280px" }, "tablet": { "base_font_size": "15px", "container_max_width": "768px" }, "mobile": { "base_font_size": "14px", "container_max_width": "100%" } },
  "headers": [{ "name": "هدر اصلی", "blocks": [{ "id": "UUID", "type": "nav_header", "data": { "logo_text": "...", "logo_url": "/", "sticky": true, "links": [...], "cta_text": "...", "cta_url": "#" } }] }],
  "footers": [{ "name": "فوتر اصلی", "blocks": [{ "id": "UUID", "type": "nav_footer", "data": { "logo_text": "...", "columns": [...], "copyright": "..." } }] }],
  "pages": [{ "title": "صفحه اصلی", "slug": "", "blocks": [ ... ] }],
  "templates": [{ "name": "...", "type": "section", "category": "hero", "blocks": [...] }],
  "demo_data": { "site_name": "{$name}", "tagline": "..." }
}

IMPORTANT: Replace all UUID with real UUIDs. Generate 6–10 blocks for the home page. Ensure all sections: {$sectionList}
USR;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::CLAUDE_VERSION,
            'content-type'      => 'application/json',
        ])->timeout(self::TIMEOUT)->post(self::CLAUDE_API, [
            'model'      => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Claude API: ' . ($response->json('error.message') ?? $response->status()));
        }

        $raw = $response->json('content.0.text') ?? '{}';
        $raw = $this->stripJsonFences($raw);

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    // ── DeepSeek AI Pack Generation (OpenAI-compatible) ──────────────────────

    private function aiGeneratePackDeepSeek(
        string $name,
        array  $sections,
        array  $globalStyles,
        array  $layoutMap,
        array  $visualProfile,
        array  $commerceProfile,
        array  $similarityScores,
        string $apiKey
    ): ?array {
        $bestMatch   = $similarityScores['best_match'] ?? 'ecommerce';
        $bestScore   = $similarityScores['best_match_score'] ?? 0;
        $isCommerce  = $commerceProfile['is_commerce'] ?? false;
        $sectionList = implode(', ', $sections);
        $stylesJson  = json_encode($globalStyles, JSON_UNESCAPED_UNICODE);
        $colorPalette = $visualProfile['color_palette'] ?? [];
        $typography   = $visualProfile['typography'] ?? [];

        $primaryColor = $colorPalette['primary'] ?? '#3b82f6';

        $systemPrompt = 'You are a professional Persian (RTL/Farsi) web theme designer AI. Generate a complete TB3 ThemePack JSON for a Persian e-commerce website. Return ONLY valid JSON — no markdown, no code fences, no explanation. ALL text in Persian. Direction RTL. Font Vazirmatn. Use ORIGINAL content only.';

        $userPrompt = <<<USR
Reverse-engineer and reconstruct a Persian website theme. Site: "{$name}". Best match: {$bestMatch} ({$bestScore}/100). Commerce: {$isCommerce}. Sections: {$sectionList}.

Global styles: {$stylesJson}

Return this exact JSON structure (fill all fields, generate UUIDs for IDs):
{
  "pack_name": "{$name}",
  "description": "توضیح فارسی قالب فروشگاهی",
  "global_styles": { "primary_color": "{$primaryColor}", "secondary_color": "#ffffff", "accent_color": "#f59e0b", "background_color": "#ffffff", "text_color": "#111827", "font_family": "Vazirmatn", "border_radius": "md", "direction": "rtl" },
  "responsive_settings": { "desktop": { "base_font_size": "16px", "container_max_width": "1280px" }, "tablet": { "base_font_size": "15px", "container_max_width": "768px" }, "mobile": { "base_font_size": "14px", "container_max_width": "100%" } },
  "headers": [{ "name": "هدر اصلی", "blocks": [{ "id": "uuid1", "type": "nav_header", "data": { "logo_text": "{$name}", "logo_url": "/", "sticky": true, "links": [{"text": "خانه", "url": "/"}, {"text": "محصولات", "url": "/products"}, {"text": "درباره ما", "url": "/about"}, {"text": "تماس", "url": "/contact"}], "cta_text": "خرید کنید", "cta_url": "/products" } }] }],
  "footers": [{ "name": "فوتر اصلی", "blocks": [{ "id": "uuid2", "type": "nav_footer", "data": { "logo_text": "{$name}", "columns": [{"title": "دسترسی سریع", "links": [{"text": "خانه", "url": "/"}, {"text": "محصولات", "url": "/products"}]}, {"title": "پشتیبانی", "links": [{"text": "تماس با ما", "url": "/contact"}, {"text": "سوالات متداول", "url": "/faq"}]}], "copyright": "© 2024 {$name}. تمام حقوق محفوظ است." } }] }],
  "pages": [{
    "title": "صفحه اصلی",
    "slug": "",
    "blocks": [
      { "id": "uuid3", "type": "hero", "data": { "title": "خرید آنلاین با بهترین قیمت", "subtitle": "هزاران محصول متنوع با ارسال سریع", "cta_text": "مشاهده محصولات", "cta_url": "/products", "layout": "standard", "bg_color": "#f8fafc" } },
      { "id": "uuid4", "type": "card_grid", "data": { "title": "محصولات پرفروش", "columns": 4, "show_price": true, "show_badge": true, "items": [{"title": "محصول نمونه ۱", "price": "۱۵۰,۰۰۰ تومان", "badge": "پرفروش"}, {"title": "محصول نمونه ۲", "price": "۲۸۰,۰۰۰ تومان", "badge": "جدید"}, {"title": "محصول نمونه ۳", "price": "۹۵,۰۰۰ تومان"}, {"title": "محصول نمونه ۴", "price": "۳۴۰,۰۰۰ تومان", "badge": "تخفیف"}] } },
      { "id": "uuid5", "type": "features_grid", "data": { "title": "چرا ما را انتخاب کنید؟", "columns": 3, "items": [{"icon": "🚚", "title": "ارسال سریع", "text": "تحویل در کمتر از ۴۸ ساعت"}, {"icon": "🔒", "title": "پرداخت امن", "text": "درگاه‌های معتبر بانکی"}, {"icon": "↩️", "title": "ضمانت بازگشت", "text": "۷ روز ضمانت بازگشت کالا"}] } },
      { "id": "uuid6", "type": "stats_row", "data": { "items": [{"number": "+۱۰,۰۰۰", "label": "مشتری راضی"}, {"number": "+۵۰,۰۰۰", "label": "محصول موجود"}, {"number": "۹۸٪", "label": "رضایت مشتریان"}, {"number": "+۱۰۰", "label": "برند معتبر"}] } },
      { "id": "uuid7", "type": "testimonials_slider", "data": { "title": "نظرات مشتریان ما", "items": [{"name": "علی محمدی", "text": "خرید از این سایت بسیار راحت بود. کیفیت محصول عالی بود."}, {"name": "فاطمه احمدی", "text": "ارسال سریع و بسته‌بندی مناسب. حتماً دوباره خرید می‌کنم."}, {"name": "حسن رضایی", "text": "قیمت‌ها مناسب و پشتیبانی عالی است."}] } },
      { "id": "uuid8", "type": "cta", "data": { "title": "همین الان خرید کنید", "subtitle": "با تخفیف‌های ویژه و ارسال رایگان", "cta_text": "شروع خرید", "cta_url": "/products", "bg_color": "#3b82f6", "text_color": "#ffffff" } }
    ]
  }],
  "templates": [
    { "name": "هیرو فروشگاه", "type": "section", "category": "hero", "blocks": [{ "id": "t-uuid1", "type": "hero", "data": { "title": "خرید آنلاین", "subtitle": "محصولات با کیفیت", "cta_text": "مشاهده", "cta_url": "/" } }] },
    { "name": "گرید محصولات", "type": "section", "category": "products", "blocks": [{ "id": "t-uuid2", "type": "card_grid", "data": { "title": "محصولات", "columns": 4 } }] },
    { "name": "ویژگی‌ها", "type": "section", "category": "features", "blocks": [{ "id": "t-uuid3", "type": "features_grid", "data": { "title": "مزایا", "columns": 3 } }] }
  ],
  "demo_data": { "site_name": "{$name}", "tagline": "فروشگاه آنلاین حرفه‌ای" }
}

Replace all uuid1-uuid8, t-uuid1-t-uuid3 with actual UUID v4 values. Keep all Persian text.
USR;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(self::TIMEOUT)->post(self::DEEPSEEK_API, [
            'model'       => self::DEEPSEEK_MODEL,
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => 0.3,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('DeepSeek API: ' . ($response->json('error.message') ?? $response->status()));
        }

        $raw = $response->json('choices.0.message.content') ?? '{}';
        $raw = $this->stripJsonFences($raw);

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    // ── PHP Fallback Pack Builder ─────────────────────────────────────────────

    private function phpBuildPack(
        string $name,
        array  $sections,
        array  $globalStyles,
        array  $layoutMap,
        array  $commerceProfile
    ): array {
        $homeBlocks = [];
        $templates  = [];

        $headerData = $this->placeholder->header(['site_name' => $name]);
        $footerData = $this->placeholder->footer(['site_name' => $name]);

        foreach ($sections as $section) {
            if (in_array($section, ['header', 'footer'], true)) continue;

            $blockType  = $this->sectionToBlockType($section, $commerceProfile);
            $blockData  = $this->buildBlockData($section, $name, $layoutMap, $commerceProfile);
            $blockId    = (string) Str::uuid();

            $block      = ['id' => $blockId, 'type' => $blockType, 'data' => $blockData];
            $homeBlocks[] = $block;

            $templates[] = [
                'name'     => $this->sectionLabel($section),
                'type'     => 'section',
                'category' => $this->sectionToCategory($section),
                'blocks'   => [$block],
            ];
        }

        return [
            'pack_name'   => $name,
            'description' => "قالب {$name} — بازسازی‌شده با موتور مهندسی معکوس بصری",
            'global_styles'      => $globalStyles,
            'responsive_settings'=> [
                'desktop' => ['base_font_size' => '16px', 'container_max_width' => '1280px'],
                'tablet'  => ['base_font_size' => '15px', 'container_max_width' => '768px'],
                'mobile'  => ['base_font_size' => '14px', 'container_max_width' => '100%'],
            ],
            'headers' => [['name' => 'هدر اصلی', 'blocks' => [
                ['id' => (string) Str::uuid(), 'type' => 'nav_header', 'data' => $headerData],
            ]]],
            'footers' => [['name' => 'فوتر اصلی', 'blocks' => [
                ['id' => (string) Str::uuid(), 'type' => 'nav_footer', 'data' => $footerData],
            ]]],
            'pages'   => [['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => $homeBlocks]],
            'templates' => $templates,
            'demo_data' => ['site_name' => $name, 'tagline' => 'راه‌حل حرفه‌ای برای کسب‌وکار شما'],
        ];
    }

    private function buildBlockData(string $section, string $name, array $layoutMap, array $commerce): array
    {
        $phData = $this->placeholder->forSection($section, ['site_name' => $name]);

        // Enrich with layout hints
        $colSig = $layoutMap['column_signature'] ?? [];
        $cols   = $colSig[$section] ?? 3;

        return match ($section) {
            'hero' => array_merge($phData, [
                'layout'     => $layoutMap['sections'][1]['data']['layout'] ?? 'standard',
                'text_align' => 'center',
            ]),
            'features' => array_merge($phData, [
                'columns'    => $cols,
                'show_icons' => true,
            ]),
            'pricing' => array_merge($phData, [
                'columns'    => $cols,
                'has_toggle' => $commerce['pricing']['has_toggle'] ?? false,
            ]),
            'products' => array_merge($phData, [
                'columns'    => $cols,
                'show_price' => true,
                'show_badge' => true,
            ]),
            default => $phData,
        };
    }

    // ── ThemePack Persistence ─────────────────────────────────────────────────

    private function persistThemePack(array $packJson, string $name, array $similarityScores): ThemePack
    {
        $bestMatch = $similarityScores['best_match'] ?? 'custom';

        $themePack = ThemePack::create([
            'name'         => $name,
            'description'  => $packJson['description'] ?? "قالب بازسازی‌شده با مهندسی معکوس بصری از {$bestMatch}",
            'version'      => '1.0.0',
            'author'       => 'Visual Reverse Engineering Engine (TB3 Sprint 9.5)',
            'license_type' => 'free',
            'tags'         => ['reverse-engineered', 'tb3-sprint9_5', "match:{$bestMatch}"],
            'is_public'    => false,
            'is_active'    => true,
            'site_id'      => null,
            'pack_data'    => [
                'global_styles'       => $packJson['global_styles'] ?? [],
                'responsive_settings' => $packJson['responsive_settings'] ?? [],
                'headers'             => $packJson['headers'] ?? [],
                'footers'             => $packJson['footers'] ?? [],
                'pages'               => $packJson['pages'] ?? [],
                'templates'           => $packJson['templates'] ?? [],
                'demo_data'           => $packJson['demo_data'] ?? [],
                'similarity'          => [
                    'best_match'  => $bestMatch,
                    'score'       => $similarityScores['best_match_score'] ?? 0,
                ],
            ],
        ]);

        // Create section templates
        foreach ($packJson['templates'] ?? [] as $tpl) {
            if (empty($tpl['blocks'])) continue;
            $category = $tpl['category'] ?? 'hero';
            $type     = $tpl['type']     ?? 'section';
            ThemeTemplate::create([
                'type'         => in_array($type, array_keys(ThemeTemplate::TYPES)) ? $type : 'section',
                'category'     => in_array($category, array_keys(ThemeTemplate::CATEGORIES)) ? $category : 'hero',
                'name'         => $tpl['name'] ?? "قالب {$name}",
                'description'  => "بازسازی‌شده از: {$name}",
                'tags'         => ['reverse-engineered', 'tb3-sprint9_5'],
                'version'      => '1.0.0',
                'license_type' => 'free',
                'blocks'       => $this->normalizeBlocks($tpl['blocks']),
                'is_public'    => false,
                'is_active'    => true,
                'site_id'      => null,
            ]);
        }

        return $themePack;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildGlobalStyles(array $visual, array $scores): array
    {
        $palette  = $visual['color_palette'] ?? [];
        $typo     = $visual['typography'] ?? [];
        $radius   = $visual['border_radius']['scale'] ?? 'md';

        return [
            'primary_color'    => $palette['primary']    ?? '#3b82f6',
            'secondary_color'  => $palette['secondary']  ?? '#8b5cf6',
            'accent_color'     => $palette['accent']     ?? '#f59e0b',
            'background_color' => $palette['background'] ?? '#ffffff',
            'text_color'       => $palette['text']       ?? '#111827',
            'font_family'      => 'Vazirmatn',
            'border_radius'    => $radius,
            'direction'        => 'rtl',
            'shadow'           => $visual['shadow_system']['card'] ?? '0 4px 16px rgba(0,0,0,0.08)',
            'dark_mode'        => $visual['dark_mode'] ?? false,
            'breakpoints'      => ['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280],
        ];
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

    private function sectionToBlockType(string $section, array $commerce): string
    {
        if ($section === 'products' && ($commerce['is_commerce'] ?? false)) {
            return 'card_grid';
        }
        return match ($section) {
            'hero'         => 'hero',
            'features'     => 'features_grid',
            'services'     => 'services_cards',
            'pricing'      => 'pricing_cards',
            'faq'          => 'faq',
            'testimonials' => 'testimonials_slider',
            'blog'         => 'card_grid',
            'products'     => 'card_grid',
            'cta'          => 'cta',
            'stats'        => 'stats_row',
            'team'         => 'team',
            default        => 'text',
        };
    }

    private function sectionToCategory(string $section): string
    {
        return match ($section) {
            'hero', 'cta' => 'hero',
            'features', 'services' => 'features',
            'pricing' => 'pricing',
            'testimonials', 'reviews' => 'testimonials',
            'blog', 'news' => 'blog',
            'faq' => 'faq',
            'products' => 'products',
            default => 'hero',
        };
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'hero'         => 'هیرو — بنر اصلی',
            'features'     => 'ویژگی‌ها',
            'services'     => 'خدمات',
            'pricing'      => 'قیمت‌گذاری',
            'faq'          => 'سوالات متداول',
            'testimonials' => 'نظرات مشتریان',
            'blog'         => 'وبلاگ',
            'products'     => 'محصولات',
            'cta'          => 'فراخوان عمل',
            'stats'        => 'آمار و ارقام',
            'team'         => 'تیم ما',
            default        => $section,
        };
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
