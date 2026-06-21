<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\JsonImport\Adapters\ElementorAdapter;
use App\Services\ThemeBuilder\JsonImport\ThemePackGenerator;
use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Imports the "Acool - Air Conditioner & HVAC Repair Service" Elementor
 * template kit (18 templates) into the ThemeBuilder marketplace.
 *
 * Source: acool-air-conditioner-hvac-repair-service-elementor-template-kit/
 * Format: Elementor Flexbox Container (v3.6+) — all elType=container layout
 * Pages: home, about, services, service-detail, projects, project-detail,
 *        pricing, faq, blog, contact-us, 404-error, single-post
 * Sections: header, footer (extracted separately)
 *
 * Usage:
 *   php artisan acool:seed            — install (skip if exists)
 *   php artisan acool:seed --fresh    — delete and re-install
 */
class AcoolHvacSeedCommand extends Command
{
    protected $signature = 'acool:seed {--fresh : Delete existing and re-seed}';
    protected $description = 'Seed Acool HVAC Elementor template kit (18 templates → ThemePack marketplace)';

    private const SLUG        = 'acool-hvac-service';
    private const KIT_PATH    = 'acool-air-conditioner-hvac-repair-service-elementor-template-kit';

    // ── Brand palette ──────────────────────────────────────────────────────────
    private const PRIMARY     = '#1e3a5f'; // Deep navy blue — HVAC professional
    private const ACCENT      = '#0ea5e9'; // Sky blue — cool/cold air
    private const WARM        = '#f97316'; // Orange — heat / service call-to-action
    private const BG          = '#f8fafc'; // Light gray background
    private const DARK        = '#0f172a'; // Dark navy — header/footer
    private const TEXT        = '#334155'; // Dark slate text

    // ── Page templates to import (name → canvas slug) ─────────────────────────
    private const PAGE_TEMPLATES = [
        'home'           => 'home',
        'about'          => 'about',
        'services'       => 'services',
        'service-detail' => 'service_detail',
        'projects'       => 'projects',
        'project-detail' => 'project_detail',
        'pricing'        => 'pricing',
        'faq'            => 'faq',
        'blog'           => 'blog',
        'contact-us'     => 'contact',
        '404-error'      => '404',
        'single-post'    => 'single_post',
    ];

    public function handle(): int
    {
        $this->printBanner();

        $kitPath = base_path(self::KIT_PATH);
        if (!is_dir($kitPath)) {
            $this->error("Kit directory not found: {$kitPath}");
            return 1;
        }

        $manifestPath = $kitPath . '/manifest.json';
        if (!file_exists($manifestPath)) {
            $this->error("manifest.json not found in kit directory.");
            return 1;
        }

        // ── Fresh mode ─────────────────────────────────────────────────────────
        if ($this->option('fresh')) {
            $deleted = Theme::where('slug', self::SLUG)->whereNull('site_id')->delete();
            if ($deleted) {
                $this->warn("Deleted existing Acool theme ({$deleted} record(s)).");
            }
        } elseif (Theme::where('slug', self::SLUG)->whereNull('site_id')->exists()) {
            $this->info('Acool HVAC theme already exists. Use --fresh to reinstall.');
            return 0;
        }

        // ── Load manifest ──────────────────────────────────────────────────────
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->info('Manifest loaded: ' . ($manifest['title'] ?? 'Acool Kit'));
        $this->info('Templates in manifest: ' . count($manifest['templates'] ?? []));

        // ── Process templates through ElementorAdapter ─────────────────────────
        $adapter      = new ElementorAdapter(new WidgetMapper());
        $allPages     = [];
        $headerBlocks = [];
        $footerBlocks = [];
        $processedCount = 0;
        $skippedCount   = 0;

        foreach ($manifest['templates'] ?? [] as $template) {
            $name     = $template['name'] ?? '';
            $source   = $template['source'] ?? '';
            $type     = $template['type'] ?? 'page';
            $category = $template['metadata']['template_type'] ?? '';

            $jsonPath = $kitPath . '/' . $source;
            if (!file_exists($jsonPath)) {
                $this->warn("  [SKIP] {$name} — file not found: {$source}");
                $skippedCount++;
                continue;
            }

            $json = json_decode(file_get_contents($jsonPath), true);
            if (!$json) {
                $this->warn("  [SKIP] {$name} — invalid JSON");
                $skippedCount++;
                continue;
            }

            // Convert through adapter
            $result = $adapter->convert($json);

            if ($type === 'section') {
                // Handle header / footer sections
                if (str_contains($category, 'header')) {
                    $headerBlocks = $result['pages']['home']['blocks'] ?? [];
                    $this->line("  [HDR] {$name} → " . count($headerBlocks) . " blocks");
                } elseif (str_contains($category, 'footer')) {
                    $footerBlocks = $result['pages']['home']['blocks'] ?? [];
                    $this->line("  [FTR] {$name} → " . count($footerBlocks) . " blocks");
                } else {
                    // Other sections (forms etc.) — skip
                    $this->line("  [SKIP] {$name} (section: {$category})");
                }
            } else {
                // Page template
                $slug     = $this->slugForTemplate($source);
                $pageBlocks = $result['pages']['home']['blocks'] ?? [];
                $allPages[$slug] = [
                    'title'  => $name,
                    'slug'   => $slug === 'home' ? '' : $slug,
                    'blocks' => $pageBlocks,
                ];
                $this->line("  [PAGE] {$name} ({$slug}) → " . count($pageBlocks) . " blocks");
                $processedCount++;
            }
        }

        $this->info("Processed: {$processedCount} pages, skipped: {$skippedCount}");

        if (empty($allPages)) {
            $this->error('No pages were extracted. Check adapter and JSON format.');
            return 1;
        }

        // ── If adapter yields empty blocks, use built-in content ──────────────
        // This ensures the theme renders beautifully even if Elementor container
        // nesting produced no top-level renderable blocks on some pages.
        foreach (self::PAGE_TEMPLATES as $key => $slug) {
            if (empty($allPages[$slug]['blocks'])) {
                $allPages[$slug] = [
                    'title'  => $this->pageTitle($key),
                    'slug'   => $slug === 'home' ? '' : $slug,
                    'blocks' => $this->buildFallbackBlocks($key),
                ];
                $this->warn("  [FALLBACK] {$key} — using built-in blocks");
            }
        }

        // ── Global styles from kit ─────────────────────────────────────────────
        $globalStyles = $this->buildGlobalStyles();

        // ── Create header/footer fallbacks if empty ────────────────────────────
        if (empty($headerBlocks)) {
            $headerBlocks = $this->buildHeader();
        }
        if (empty($footerBlocks)) {
            $footerBlocks = $this->buildFooter();
        }

        // ── Build ThemePack via generator ──────────────────────────────────────
        $generator = new ThemePackGenerator();
        $result = $generator->generate([
            'pages'         => $allPages,
            'global_styles' => $globalStyles,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'meta'          => [
                'source'  => 'elementor',
                'version' => $manifest['kit_version'] ?? '1.0.0',
                'title'   => $manifest['title'] ?? 'Acool HVAC',
            ],
        ], 'Acool HVAC Service', [
            'category'               => 'industrial',
            'description'            => 'قالب حرفه‌ای تأسیسات و تعمیر کولر/سرمایش — ۱۸ صفحه کامل از Elementor Kit',
            'publish_to_marketplace' => true,
        ]);

        $pack  = $result['pack'];
        $theme = $result['theme'];

        // ── Update theme slug + marketplace fields ─────────────────────────────
        if ($theme) {
            $theme->update([
                'slug'           => self::SLUG,
                'status'         => Theme::STATUS_PUBLISHED,
                'thumbnail_url'  => $this->thumbnailSvg(),
                'preview_images' => $this->previewImages($kitPath, $manifest),
            ]);

            ThemeMarketplace::where('theme_id', $theme->id)->update([
                'annual_price'     => 0,
                'lifetime_price'   => 0,
                'is_featured'      => 1,
                'marketplace_score'=> 85,
                'design_score'     => 88,
                'commerce_score'   => 80,
                'completeness_score' => 95,
                'preview_url'      => 'https://elementorkits-1.nextdin.com/acool/',
            ]);

            $this->newLine();
            $this->info('✓ ThemePack created: ' . $pack->uuid);
            $this->info('✓ Theme published:   ' . $theme->id . ' (' . $theme->slug . ')');
            $this->info('✓ Pages:             ' . count($allPages));
            $this->info('✓ Total blocks:      ' . ($result['stats']['total_blocks'] ?? 0));
            $this->info('✓ Quality score:     ' . ($result['quality']['score'] ?? 0) . '/100');
        } else {
            $this->warn('Theme marketplace entry not created. Check DB state.');
        }

        return 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function slugForTemplate(string $source): string
    {
        // source = "templates/home.json" → "home"
        $base = basename($source, '.json');
        return self::PAGE_TEMPLATES[$base] ?? Str::slug($base);
    }

    private function pageTitle(string $key): string
    {
        $titles = [
            'home'           => 'صفحه اصلی',
            'about'          => 'درباره ما',
            'services'       => 'خدمات',
            'service-detail' => 'جزئیات خدمت',
            'projects'       => 'پروژه‌ها',
            'project-detail' => 'جزئیات پروژه',
            'pricing'        => 'تعرفه',
            'faq'            => 'سوالات متداول',
            'blog'           => 'وبلاگ',
            'contact-us'     => 'تماس با ما',
            '404-error'      => 'صفحه ۴۰۴',
            'single-post'    => 'پست وبلاگ',
        ];
        return $titles[$key] ?? ucfirst($key);
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║  Acool HVAC — Elementor Kit Import                       ║');
        $this->info('║  Air Conditioner & HVAC Repair Service Template          ║');
        $this->info('║  18 templates → ThemeBuilder Canvas Marketplace          ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    // ── Global styles ─────────────────────────────────────────────────────────

    private function buildGlobalStyles(): array
    {
        return [
            'primaryColor'    => self::PRIMARY,
            'secondaryColor'  => self::ACCENT,
            'accentColor'     => self::WARM,
            'backgroundColor' => self::BG,
            'textColor'       => self::TEXT,
            'fontFamily'      => 'Vazirmatn, sans-serif',
            'direction'       => 'rtl',
            'borderRadius'    => '8px',
            'shadow'          => '0 4px 16px rgba(30,58,95,0.12)',
            'custom_css'      => '',
        ];
    }

    // ── Header / Footer fallbacks ─────────────────────────────────────────────

    private function buildHeader(): array
    {
        return [
            [
                'id'   => Str::random(8),
                'type' => 'heading',
                'props'=> ['text' => 'آکول سرویس', 'level' => 1],
            ],
        ];
    }

    private function buildFooter(): array
    {
        return [
            [
                'id'   => Str::random(8),
                'type' => 'text',
                'props'=> ['content' => '© ۱۴۰۳ آکول سرویس — تعمیر و نگهداری تهویه مطبوع'],
            ],
        ];
    }

    // ── Fallback blocks per page (shown when adapter yields empty) ────────────

    private function buildFallbackBlocks(string $pageKey): array
    {
        $heroDefault = [
            'id'    => Str::random(8),
            'type'  => 'hero',
            'props' => [
                'title'       => 'تعمیر و نگهداری تهویه مطبوع',
                'subtitle'    => 'سرویس حرفه‌ای کولرهای گازی، چیلر و سیستم‌های HVAC با بیش از ۱۵ سال تجربه',
                'button_text' => 'درخواست سرویس',
                'button_url'  => '#contact',
                'background'  => self::PRIMARY,
            ],
            'blocks' => [],
        ];

        $blocks = match ($pageKey) {
            'home' => [
                $heroDefault,
                [
                    'id'    => Str::random(8),
                    'type'  => 'icon',
                    'props' => ['name' => 'fas fa-snowflake', 'size' => 'lg'],
                    'blocks'=> [],
                ],
                [
                    'id'    => Str::random(8),
                    'type'  => 'heading',
                    'props' => ['text' => 'خدمات تخصصی ما', 'level' => 2],
                    'blocks'=> [],
                ],
                [
                    'id'    => Str::random(8),
                    'type'  => 'blog_posts',
                    'props' => ['title' => 'پروژه‌های اخیر', 'limit' => 3],
                    'blocks'=> [],
                ],
                [
                    'id'    => Str::random(8),
                    'type'  => 'testimonials',
                    'props' => ['title' => 'نظرات مشتریان', 'limit' => 3],
                    'blocks'=> [],
                ],
            ],
            'about' => [
                [
                    'id'    => Str::random(8),
                    'type'  => 'hero_split',
                    'props' => [
                        'title'       => 'درباره آکول سرویس',
                        'subtitle'    => 'ما با بیش از ۱۵ سال تجربه در زمینه تعمیر و نگهداری سیستم‌های تهویه مطبوع فعالیت می‌کنیم',
                        'button_text' => 'تماس با ما',
                        'button_url'  => '#contact',
                        'image_url'   => '',
                    ],
                    'blocks'=> [],
                ],
                ['id' => Str::random(8), 'type' => 'team', 'props' => ['title' => 'تیم متخصص ما', 'limit' => 6], 'blocks' => []],
            ],
            'services' => [
                [
                    'id'    => Str::random(8),
                    'type'  => 'heading',
                    'props' => ['text' => 'خدمات تخصصی کولر و تهویه مطبوع', 'level' => 1],
                    'blocks'=> [],
                ],
                [
                    'id'    => Str::random(8),
                    'type'  => 'text',
                    'props' => ['content' => 'نصب، تعمیر، شارژ گاز، سرویس دوره‌ای و نگهداری انواع کولرهای گازی، چیلر، فن‌کویل و سیستم‌های مرکزی'],
                    'blocks'=> [],
                ],
            ],
            'pricing' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'تعرفه خدمات', 'level' => 1], 'blocks' => []],
                ['id' => Str::random(8), 'type' => 'pricing_table', 'props' => ['title' => 'پلن‌های سرویس'], 'blocks' => []],
            ],
            'faq' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'سوالات متداول', 'level' => 1], 'blocks' => []],
                [
                    'id'    => Str::random(8),
                    'type'  => 'accordion',
                    'props' => [
                        'items' => [
                            ['title' => 'هزینه شارژ گاز کولر چقدر است؟', 'blocks' => []],
                            ['title' => 'آیا سرویس در محل ارائه می‌دهید؟', 'blocks' => []],
                            ['title' => 'ضمانت تعمیرات چند ماه است؟', 'blocks' => []],
                            ['title' => 'چه برندهایی را سرویس می‌دهید؟', 'blocks' => []],
                        ],
                    ],
                    'blocks'=> [],
                ],
            ],
            'blog' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'مقالات و اخبار', 'level' => 1], 'blocks' => []],
                ['id' => Str::random(8), 'type' => 'blog_posts', 'props' => ['title' => '', 'limit' => 9], 'blocks' => []],
            ],
            'contact' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'تماس با ما', 'level' => 1], 'blocks' => []],
                ['id' => Str::random(8), 'type' => 'map', 'props' => ['address' => 'تهران، ایران', 'zoom' => 13], 'blocks' => []],
            ],
            'projects' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'نمونه پروژه‌ها', 'level' => 1], 'blocks' => []],
                ['id' => Str::random(8), 'type' => 'gallery', 'props' => ['title' => 'پروژه‌های انجام شده', 'items' => []], 'blocks' => []],
            ],
            '404' => [
                ['id' => Str::random(8), 'type' => 'heading', 'props' => ['text' => 'صفحه مورد نظر یافت نشد', 'level' => 1], 'blocks' => []],
                ['id' => Str::random(8), 'type' => 'button', 'props' => ['text' => 'بازگشت به صفحه اصلی', 'url' => '/', 'style' => 'primary'], 'blocks' => []],
            ],
            default => [$heroDefault],
        };

        return $blocks;
    }

    // ── Thumbnail SVG ─────────────────────────────────────────────────────────

    private function thumbnailSvg(): string
    {
        $primary = self::PRIMARY;
        $accent  = self::ACCENT;
        $warm    = self::WARM;

        return "data:image/svg+xml;base64," . base64_encode(<<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$primary}"/>
      <stop offset="100%" style="stop-color:{$accent}"/>
    </linearGradient>
  </defs>
  <rect width="800" height="500" fill="url(#bg)"/>
  <!-- Header bar -->
  <rect x="0" y="0" width="800" height="60" fill="rgba(0,0,0,0.3)"/>
  <rect x="30" y="18" width="120" height="24" rx="4" fill="white" opacity="0.9"/>
  <rect x="560" y="20" width="60" height="20" rx="10" fill="{$warm}"/>
  <rect x="640" y="20" width="60" height="20" rx="10" fill="{$warm}" opacity="0.7"/>
  <rect x="720" y="20" width="50" height="20" rx="10" fill="{$warm}" opacity="0.4"/>
  <!-- Hero -->
  <rect x="0" y="60" width="800" height="200" fill="rgba(255,255,255,0.05)"/>
  <rect x="60" y="95" width="300" height="32" rx="6" fill="white" opacity="0.9"/>
  <rect x="60" y="140" width="240" height="16" rx="4" fill="white" opacity="0.6"/>
  <rect x="60" y="170" width="180" height="16" rx="4" fill="white" opacity="0.4"/>
  <rect x="60" y="210" width="140" height="36" rx="8" fill="{$warm}"/>
  <!-- AC unit icon -->
  <rect x="520" y="80" width="220" height="160" rx="12" fill="white" opacity="0.15"/>
  <rect x="540" y="95" width="180" height="120" rx="8" fill="white" opacity="0.1"/>
  <circle cx="630" cy="155" r="40" fill="none" stroke="white" stroke-width="3" opacity="0.5"/>
  <line x1="630" y1="115" x2="630" y2="195" stroke="white" stroke-width="2" opacity="0.5"/>
  <line x1="590" y1="155" x2="670" y2="155" stroke="white" stroke-width="2" opacity="0.5"/>
  <!-- Services row -->
  <rect x="0" y="290" width="800" height="130" fill="white" opacity="0.07"/>
  <rect x="50" y="310" width="180" height="90" rx="10" fill="white" opacity="0.12"/>
  <rect x="250" y="310" width="180" height="90" rx="10" fill="white" opacity="0.12"/>
  <rect x="450" y="310" width="180" height="90" rx="10" fill="white" opacity="0.12"/>
  <rect x="650" y="310" width="110" height="90" rx="10" fill="white" opacity="0.12"/>
  <!-- Footer -->
  <rect x="0" y="450" width="800" height="50" fill="rgba(0,0,0,0.4)"/>
  <text x="400" y="481" text-anchor="middle" font-family="Arial" font-size="13" fill="white" opacity="0.7">Acool HVAC Service — Air Conditioner &amp; HVAC Repair</text>
</svg>
SVG);
    }

    // ── Preview images from kit screenshots ───────────────────────────────────

    private function previewImages(string $kitPath, array $manifest): array
    {
        $images = [];
        $priority = ['home', 'services', 'about', 'pricing'];

        foreach ($manifest['templates'] ?? [] as $template) {
            if (($template['type'] ?? '') !== 'page') continue;
            $screenshot = $template['screenshot'] ?? '';
            if (!$screenshot) continue;

            $base = basename($screenshot, '.jpg');
            $images[$base] = $template['preview_url'] ?? '';
        }

        // Return in priority order then rest
        $sorted = [];
        foreach ($priority as $p) {
            if (isset($images[$p])) $sorted[] = $images[$p];
        }
        foreach ($images as $key => $url) {
            if (!in_array($url, $sorted)) $sorted[] = $url;
        }

        return array_values(array_filter($sorted));
    }
}
