<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TB-3 — AI URL Analyzer: fetches a URL, extracts design signals, and calls
 * an AI API (GapGPT or Claude) to produce a ready-to-use Theme JSON.
 *
 * Provider selection via THEME_AI_PROVIDER env:
 *   gapgpt  → OpenAI-compatible (default when GAPGPT_API_KEY is set)
 *   claude  → Anthropic Messages API
 */
class ThemeAiAnalyzerService
{
    // ── GapGPT (OpenAI-compatible) ────────────────────────────────────────────
    private const GAPGPT_DEFAULT_BASE  = 'https://api.gapgpt.app/v1';
    private const GAPGPT_DEFAULT_MODEL = 'gpt-4o-mini';

    // ── Claude (Anthropic) ────────────────────────────────────────────────────
    private const CLAUDE_API     = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_VERSION = '2023-06-01';
    private const CLAUDE_MODEL   = 'claude-sonnet-4-6';

    private const MAX_TOKENS = 4096;
    private const TIMEOUT    = 60;

    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->provider = strtolower((string) config('services.theme_ai.provider', 'gapgpt'));

        if ($this->provider === 'claude') {
            $this->apiKey  = (string) config('services.anthropic.key', '');
            $this->baseUrl = self::CLAUDE_API;
            $this->model   = (string) config('services.theme_ai.model', self::CLAUDE_MODEL);
        } else {
            // GapGPT (default) — base URL + /chat/completions
            $this->apiKey  = (string) config('services.gapgpt.key', '');
            $base          = rtrim((string) config('services.gapgpt.base_url', self::GAPGPT_DEFAULT_BASE), '/');
            $this->baseUrl = $base . '/chat/completions';
            $this->model   = (string) config('services.theme_ai.model', self::GAPGPT_DEFAULT_MODEL);
        }
    }

    public function isEnabled(): bool
    {
        return !empty($this->apiKey);
    }

    public function getProviderLabel(): string
    {
        return $this->provider === 'claude' ? 'Claude AI' : 'GapGPT AI';
    }

    public function getEnvKeyName(): string
    {
        return $this->provider === 'claude' ? 'ANTHROPIC_API_KEY' : 'GAPGPT_API_KEY';
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function analyzeUrl(string $url, string $notes = ''): array
    {
        $pageData = $this->fetchPage($url);
        $prompt   = $this->buildPrompt($url, $pageData, $notes);
        $raw      = $this->provider === 'claude'
                        ? $this->callClaude($prompt)
                        : $this->callGapGpt($prompt);

        return $this->parseThemeJson($raw);
    }

    public function createFromJson(array $json, ?string $overrideName = null): Theme
    {
        $name = $overrideName ?: ($json['suggested_name'] ?? 'قالب هوش مصنوعی');
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(6));

        return Theme::create([
            'name'          => $name,
            'slug'          => $slug,
            'description'   => $json['description'] ?? 'تولید‌شده با هوش مصنوعی از تحلیل URL',
            'source'        => Theme::SOURCE_GENERATOR,
            'status'        => Theme::STATUS_DRAFT,
            'site_id'       => null,
            'header_blocks' => $json['header_blocks'] ?? [],
            'footer_blocks' => $json['footer_blocks'] ?? [],
            'pages'         => $json['pages'] ?? ['home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => $json['home_blocks'] ?? []]],
            'global_styles' => $json['global_styles'] ?? [],
            'settings'      => $json['settings'] ?? [],
        ]);
    }

    // ── Private: URL fetch ────────────────────────────────────────────────────

    private function fetchPage(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; SaaS-Theme-Bot/1.0)',
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'fa,en;q=0.9',
            ])
            ->timeout(15)
            ->get($url);

            if (!$response->successful()) {
                return ['error' => "HTTP {$response->status()}", 'html' => ''];
            }

            return $this->extractPageData($response->body(), $url);

        } catch (\Throwable $e) {
            Log::warning('TB-3: URL fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return ['error' => $e->getMessage(), 'html' => '', 'url' => $url];
        }
    }

    private function extractPageData(string $html, string $url): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($doc);

        $title       = $this->xpathText($xpath, '//title');
        $desc        = $this->xpathAttr($xpath, '//meta[@name="description"]', 'content');
        $h1          = $this->xpathTextAll($xpath, '//h1', 3);
        $h2          = $this->xpathTextAll($xpath, '//h2', 6);
        $navLinks    = $this->xpathTextAll($xpath, '//nav//a', 8);
        $footerLinks = $this->xpathTextAll($xpath, '//footer//a', 8);
        $metaKw      = $this->xpathAttr($xpath, '//meta[@name="keywords"]', 'content');
        $ogImage     = $this->xpathAttr($xpath, '//meta[@property="og:image"]', 'content');
        $themeColor  = $this->xpathAttr($xpath, '//meta[@name="theme-color"]', 'content');

        $styleAttr = [];
        foreach ($xpath->query('//*[@style]') as $node) {
            $styleAttr[] = $node->getAttribute('style');
            if (count($styleAttr) >= 20) break;
        }

        $lang     = 'fa';
        $htmlNode = $doc->documentElement;
        if ($htmlNode && $htmlNode->getAttribute('lang')) {
            $lang = strtolower(substr($htmlNode->getAttribute('lang'), 0, 2));
        }

        return [
            'url'          => $url,
            'title'        => $title,
            'description'  => $desc,
            'keywords'     => $metaKw,
            'lang'         => $lang,
            'h1'           => $h1,
            'h2'           => $h2,
            'nav_links'    => $navLinks,
            'footer_links' => $footerLinks,
            'og_image'     => $ogImage,
            'theme_color'  => $themeColor,
            'style_hints'  => implode(' ', array_slice($styleAttr, 0, 10)),
        ];
    }

    private function xpathText(\DOMXPath $x, string $q): string
    {
        $n = $x->query($q);
        return $n && $n->length > 0 ? trim($n->item(0)->textContent) : '';
    }

    private function xpathAttr(\DOMXPath $x, string $q, string $attr): string
    {
        $n = $x->query($q);
        return $n && $n->length > 0 ? trim($n->item(0)->getAttribute($attr)) : '';
    }

    private function xpathTextAll(\DOMXPath $x, string $q, int $limit): array
    {
        $result = [];
        $nodes  = $x->query($q);
        if (!$nodes) return $result;
        foreach ($nodes as $n) {
            $text = trim($n->textContent);
            if ($text) $result[] = $text;
            if (count($result) >= $limit) break;
        }
        return $result;
    }

    // ── Private: Prompt ───────────────────────────────────────────────────────

    private function buildPrompt(string $url, array $page, string $notes): array
    {
        $blockTypes = implode(', ', [
            'hero', 'heading', 'text', 'image', 'button', 'divider', 'spacer',
            'card', 'card_grid', 'feature_list', 'icon_box', 'testimonial',
            'faq', 'stats', 'team', 'cta', 'banner',
            'logo_bar', 'newsletter', 'contact_form',
            'nav_header', 'nav_footer', 'nav_logo', 'nav_links',
        ]);

        $pageInfo = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $system = <<<SYS
You are a professional web theme designer AI for a Persian (RTL, Farsi) SaaS platform.
Your task: analyze a website's extracted data and generate a complete theme JSON.
CRITICAL RULES:
1. Return ONLY valid JSON — no markdown, no explanation, no code fences.
2. Use RTL-friendly layout (direction: rtl).
3. All text content in Persian (Farsi) even if the source is English.
4. Primary font must be "Vazirmatn".
5. Use these available block types: {$blockTypes}
6. Keep blocks realistic — 5–12 blocks for home page, 3–5 for header/footer.
7. block IDs must be unique UUID-style strings.
SYS;

        $user = <<<USR
Website URL: {$url}
Extracted page data:
{$pageInfo}

Additional notes from user: {$notes}

Generate a complete Persian-language theme JSON with this exact structure:
{
  "suggested_name": "نام فارسی پیشنهادی قالب",
  "description": "توضیح کوتاه",
  "global_styles": {
    "primary_color": "#hex",
    "secondary_color": "#hex",
    "accent_color": "#hex",
    "background_color": "#hex",
    "text_color": "#hex",
    "font_family": "Vazirmatn",
    "border_radius": "md",
    "direction": "rtl"
  },
  "header_blocks": [
    { "id": "uuid", "type": "nav_header", "props": { "logo_text": "نام سایت", "logo_url": "/", "sticky": true, "background": "#ffffff" } },
    { "id": "uuid", "type": "nav_links", "props": { "links": [{"text":"خانه","url":"/"},{"text":"درباره ما","url":"/about"}] } }
  ],
  "footer_blocks": [
    { "id": "uuid", "type": "nav_footer", "props": { "columns": [{"title":"لینک‌ها","links":[{"text":"خانه","url":"/"}]}], "copyright": "© ۱۴۰۳ همه حقوق محفوظ است" } }
  ],
  "pages": {
    "home": {
      "title": "صفحه اصلی",
      "slug": "",
      "blocks": [
        { "id": "uuid", "type": "hero", "props": { "title": "عنوان اصلی", "subtitle": "توضیح", "button_text": "شروع کنید", "button_url": "/", "background": "primary" } }
      ]
    }
  },
  "settings": {}
}

Analyze the website's purpose, colors, navigation structure, and content sections.
Return ONLY the JSON object.
USR;

        return ['system' => $system, 'user' => $user];
    }

    // ── Private: GapGPT call (OpenAI-compatible) ──────────────────────────────

    private function callGapGpt(array $prompt): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(self::TIMEOUT)
        ->post($this->baseUrl, [
            'model'       => $this->model,
            'max_tokens'  => self::MAX_TOKENS,
            'messages'    => [
                ['role' => 'system', 'content' => $prompt['system']],
                ['role' => 'user',   'content' => $prompt['user']],
            ],
        ]);

        if (!$response->successful()) {
            $err = $response->json('error.message') ?? $response->body();
            Log::error('TB-3: GapGPT API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException("GapGPT API error: {$err}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    // ── Private: Claude call ──────────────────────────────────────────────────

    private function callClaude(array $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::CLAUDE_VERSION,
            'content-type'      => 'application/json',
        ])
        ->timeout(self::TIMEOUT)
        ->post(self::CLAUDE_API, [
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => $prompt['system'],
            'messages'   => [
                ['role' => 'user', 'content' => $prompt['user']],
            ],
        ]);

        if (!$response->successful()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Claude API error: {$err}");
        }

        return $response->json('content.0.text') ?? '';
    }

    // ── Private: JSON parse ───────────────────────────────────────────────────

    private function parseThemeJson(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("AI returned invalid JSON: {$e->getMessage()}");
        }

        $data['header_blocks'] = $this->normalizeBlocks($data['header_blocks'] ?? []);
        $data['footer_blocks'] = $this->normalizeBlocks($data['footer_blocks'] ?? []);

        if (!isset($data['pages']) || !is_array($data['pages'])) {
            $data['pages'] = ['home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []]];
        }

        foreach ($data['pages'] as $key => $page) {
            $data['pages'][$key]['blocks'] = $this->normalizeBlocks($page['blocks'] ?? []);
        }

        $data['global_styles'] = array_merge([
            'primary_color' => '#3b82f6',
            'font_family'   => 'Vazirmatn',
            'border_radius' => 'md',
            'direction'     => 'rtl',
        ], $data['global_styles'] ?? []);

        $data['settings'] = $data['settings'] ?? [];

        return $data;
    }

    private function normalizeBlocks(array $blocks): array
    {
        return array_map(function (array $block): array {
            $block['id']    = $block['id']    ?? (string) Str::uuid();
            $block['type']  = $block['type']  ?? 'text';
            $block['props'] = $block['props'] ?? [];
            return $block;
        }, $blocks);
    }
}
