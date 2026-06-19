<?php

namespace App\Services\ThemeBuilder;

/**
 * Heuristic section detector for AI Theme Generator.
 * Analyzes page signals (headings, nav, keywords) to determine
 * which sections are likely present on the source page.
 *
 * Returns an array of section descriptors with confidence scores.
 * Always includes header/footer (structural), hero (default enabled).
 * Other sections are scored from keyword matching.
 */
class ThemeSectionDetectorService
{
    // Section definitions: key → [label, defaultEnabled, keywords (fa+en)]
    private const SECTION_DEFS = [
        'header'       => ['هدر / ناوبری',      true,  ['nav', 'menu', 'header', 'logo', 'navigation', 'ناوبری', 'منو', 'هدر']],
        'hero'         => ['بنر اصلی (هیرو)',    true,  ['hero', 'banner', 'main', 'welcome', 'بنر', 'خوش‌آمد', 'اصلی']],
        'features'     => ['ویژگی‌ها',           true,  ['feature', 'benefit', 'why', 'advantage', 'ویژگی', 'مزیت', 'چرا', 'امکانات']],
        'services'     => ['خدمات',              false, ['service', 'solution', 'offer', 'خدمات', 'سرویس', 'راه‌حل']],
        'pricing'      => ['قیمت‌گذاری',         false, ['price', 'pricing', 'plan', 'subscription', 'تعرفه', 'قیمت', 'طرح', 'اشتراک']],
        'faq'          => ['سوالات متداول',      false, ['faq', 'question', 'answer', 'سوال', 'پاسخ', 'متداول']],
        'testimonials' => ['نظرات مشتریان',      false, ['testimonial', 'review', 'feedback', 'client', 'نظر', 'بازخورد', 'مشتری', 'تجربه']],
        'blog'         => ['وبلاگ / اخبار',      false, ['blog', 'post', 'article', 'news', 'وبلاگ', 'مطلب', 'خبر', 'مقاله']],
        'products'     => ['محصولات / فروشگاه',  false, ['product', 'shop', 'store', 'catalog', 'محصول', 'فروشگاه', 'کالا', 'خرید']],
        'cta'          => ['فراخوان عمل (CTA)',  true,  ['start', 'signup', 'register', 'get started', 'try', 'شروع', 'ثبت‌نام', 'رایگان']],
        'footer'       => ['فوتر',               true,  ['footer', 'copyright', 'links', 'فوتر', 'پاورقی', 'کپی‌رایت', 'لینک']],
    ];

    /**
     * Detect sections from page signals extracted by the crawler / HTML parser.
     *
     * @param array $pageData {title, h1[], h2[], nav_links[], footer_links[], keywords, description, ...}
     * @return array<array{key: string, label: string, confidence: int, enabled: bool}>
     */
    public function detect(array $pageData): array
    {
        $corpus = $this->buildCorpus($pageData);

        $sections = [];
        foreach (self::SECTION_DEFS as $key => [$label, $defaultEnabled, $keywords]) {
            $confidence  = $this->score($key, $corpus, $keywords);
            // Structural sections (header/footer) always high confidence
            if (in_array($key, ['header', 'footer'], true)) {
                $confidence = max($confidence, 90);
            }
            $sections[] = [
                'key'        => $key,
                'label'      => $label,
                'confidence' => $confidence,
                'enabled'    => $defaultEnabled || $confidence >= 50,
                'description'=> $this->sectionDescription($key),
            ];
        }

        // Sort: enabled first, then by confidence desc
        usort($sections, function ($a, $b) {
            if ($a['enabled'] !== $b['enabled']) {
                return $b['enabled'] <=> $a['enabled'];
            }
            return $b['confidence'] <=> $a['confidence'];
        });

        return $sections;
    }

    /**
     * Detect sections from a free-form text description (prompt mode).
     * Uses keyword matching on the description itself.
     */
    public function detectFromPrompt(string $description): array
    {
        return $this->detect([
            'title'        => $description,
            'description'  => $description,
            'keywords'     => $description,
            'h1'           => [$description],
            'h2'           => [],
            'nav_links'    => [],
            'footer_links' => [],
            'style_hints'  => '',
        ]);
    }

    /**
     * Detect sections from an AI-analyzed screenshot response.
     * The AI returns a JSON describing what was visually detected.
     */
    public function detectFromAiVisionResult(array $aiResult): array
    {
        // Build synthetic page data from AI vision analysis
        $pageData = [
            'title'        => $aiResult['site_name'] ?? '',
            'description'  => $aiResult['description'] ?? '',
            'keywords'     => implode(' ', $aiResult['sections_detected'] ?? []),
            'h1'           => [$aiResult['hero_text'] ?? ''],
            'h2'           => $aiResult['section_headings'] ?? [],
            'nav_links'    => $aiResult['nav_items'] ?? [],
            'footer_links' => [],
            'style_hints'  => implode(' ', $aiResult['colors'] ?? []),
            'theme_color'  => $aiResult['primary_color'] ?? '',
        ];

        $detected = $this->detect($pageData);

        // Override with AI-explicit detections
        if (!empty($aiResult['sections_detected'])) {
            $aiSections = array_map('strtolower', $aiResult['sections_detected']);
            foreach ($detected as &$section) {
                foreach ($aiSections as $aiKey) {
                    if (str_contains($aiKey, $section['key'])) {
                        $section['confidence'] = max($section['confidence'], 80);
                        $section['enabled']    = true;
                    }
                }
            }
            unset($section);
        }

        return $detected;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function buildCorpus(array $pageData): string
    {
        $parts = [
            $pageData['title']        ?? '',
            $pageData['description']  ?? '',
            $pageData['keywords']     ?? '',
            implode(' ', (array) ($pageData['h1']          ?? [])),
            implode(' ', (array) ($pageData['h2']          ?? [])),
            implode(' ', (array) ($pageData['nav_links']   ?? [])),
            implode(' ', (array) ($pageData['footer_links'] ?? [])),
            $pageData['style_hints']  ?? '',
        ];
        return strtolower(implode(' ', $parts));
    }

    private function score(string $key, string $corpus, array $keywords): int
    {
        $hits = 0;
        foreach ($keywords as $kw) {
            if (str_contains($corpus, strtolower($kw))) {
                $hits++;
            }
        }
        if ($hits === 0) return 10;
        if ($hits === 1) return 40;
        if ($hits === 2) return 60;
        if ($hits === 3) return 75;
        return min(95, 75 + ($hits - 3) * 5);
    }

    private function sectionDescription(string $key): string
    {
        return match ($key) {
            'header'       => 'لوگو، منوی ناوبری اصلی، دکمه CTA',
            'hero'         => 'بنر بزرگ اصلی با عنوان، توضیح و دکمه اقدام',
            'features'     => 'کارت‌های ویژگی با آیکون، عنوان و توضیح',
            'services'     => 'گرید خدمات ارائه‌شده با توضیح و لینک',
            'pricing'      => 'کارت‌های طرح قیمت‌گذاری با مقایسه',
            'faq'          => 'آکاردئون سوالات متداول',
            'testimonials' => 'اسلایدر نظرات مشتریان',
            'blog'         => 'گرید آخرین مطالب وبلاگ',
            'products'     => 'گرید محصولات برگزیده فروشگاه',
            'cta'          => 'بخش فراخوان برای ثبت‌نام یا تماس',
            'footer'       => 'ستون‌های لینک، اطلاعات تماس، کپی‌رایت',
            default        => '',
        };
    }
}
