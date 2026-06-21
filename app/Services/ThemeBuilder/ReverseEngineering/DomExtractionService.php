<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

/**
 * Phase 1 — DOM Extraction
 *
 * Performs deep structural DOM analysis to detect:
 *   - Header / Mega Menu
 *   - Hero sections
 *   - Grid layouts (2-col, 3-col, 4-col)
 *   - Card patterns
 *   - Feature sections
 *   - CTA sections
 *   - Pricing sections
 *   - Testimonials
 *   - FAQ / Accordion
 *   - Blog grids
 *   - Footer structure
 *
 * Returns a DomStructure array describing each detected component.
 */
class DomExtractionService
{
    // Structural landmark roles
    private const LANDMARK_PATTERNS = [
        'header'  => ['header', '[role="banner"]', 'nav:first-of-type', '.header', '#header', '.site-header', '.navbar'],
        'hero'    => ['.hero', '.banner', '.jumbotron', '[data-section="hero"]', '.landing-hero', '.intro-section', '.home-hero', '.hero-section', '.main-banner'],
        'pricing' => ['.pricing', '#pricing', '[data-section="pricing"]', '.plans', '.tariffs', '.subscription-plans', '.price-table'],
        'testimonials' => ['.testimonials', '#testimonials', '.reviews', '.feedback', '.client-reviews', '[data-section="testimonials"]'],
        'faq'    => ['.faq', '#faq', '.accordion', '[data-section="faq"]', '.questions', '.help-section'],
        'blog'   => ['.blog', '#blog', '.news', '.articles', '[data-section="blog"]', '.post-grid', '.latest-posts'],
        'cta'    => ['.cta', '.call-to-action', '[data-section="cta"]', '.get-started', '.signup-section', '.cta-section'],
        'footer' => ['footer', '[role="contentinfo"]', '.footer', '#footer', '.site-footer'],
    ];

    // Grid detection patterns (column count heuristics)
    private const GRID_PATTERNS = [
        'col-2' => ['grid-cols-2', 'columns-2', 'col-md-6', 'col-6', 'w-1/2', 'flex-row'],
        'col-3' => ['grid-cols-3', 'columns-3', 'col-md-4', 'col-4', 'w-1/3'],
        'col-4' => ['grid-cols-4', 'columns-4', 'col-md-3', 'col-3', 'w-1/4'],
    ];

    /**
     * Extract full DOM structure from raw HTML.
     *
     * @param string $html Raw HTML content
     * @param string $url  Source URL (for metadata)
     * @return array DomStructure
     */
    public function extract(string $html, string $url = ''): array
    {
        // Detect rendering mode before DOM parse
        $renderMode = $this->detectRenderMode($html, $url);

        $prev = libxml_use_internal_errors(true);
        $doc  = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_use_internal_errors($prev);
        $xpath = new \DOMXPath($doc);

        // Core extraction (always runs)
        $header  = $this->analyzeHeader($xpath);
        $footer  = $this->analyzeFooter($xpath);
        $hero    = $this->analyzeHero($xpath);

        // Semantic fallback: if class-based detection fails, use HTML5 elements
        if (!$header['detected'] && !$footer['detected']) {
            $semFallback = $this->semanticFallback($xpath, $html);
            if ($semFallback['sections_found'] > 0) {
                $header = array_merge($header, ['detected' => $semFallback['has_nav']]);
                $footer = array_merge($footer, ['detected' => $semFallback['has_footer']]);
                $hero   = array_merge($hero,   ['detected' => $semFallback['has_main']]);
            }
        }

        return [
            'url'           => $url,
            'title'         => $this->extractTitle($xpath),
            'meta'          => $this->extractMeta($xpath),
            'render_mode'   => $renderMode,
            'landmarks'     => $this->detectLandmarks($xpath, $doc),
            'header'        => $header,
            'mega_menu'     => $this->detectMegaMenu($xpath),
            'hero'          => $hero,
            'grid_layouts'  => $this->detectGridLayouts($xpath),
            'cards'         => $this->detectCards($xpath),
            'features'      => $this->detectFeatureSections($xpath),
            'cta_sections'  => $this->detectCtaSections($xpath),
            'pricing'       => $this->detectPricingSection($xpath),
            'testimonials'  => $this->detectTestimonials($xpath),
            'faq'           => $this->detectFaq($xpath),
            'blog'          => $this->detectBlogSection($xpath),
            'footer'        => $footer,
            'sections_count'=> $this->countSemanticSections($xpath),
            'depth_map'     => $this->buildDepthMap($xpath),
            'extracted_at'  => now()->toISOString(),
        ];
    }

    /**
     * Extract from AI Vision result (when screenshot is the input).
     */
    public function extractFromVision(array $visionResult): array
    {
        $sections = $visionResult['sections_detected'] ?? [];
        $colors   = $visionResult['colors'] ?? [];

        return [
            'url'          => '',
            'title'        => $visionResult['site_name'] ?? '',
            'meta'         => ['description' => $visionResult['description'] ?? ''],
            'landmarks'    => array_map(fn ($s) => ['type' => $s, 'confidence' => 80], $sections),
            'header'       => ['detected' => in_array('header', $sections), 'has_logo' => true, 'nav_items' => count($visionResult['nav_items'] ?? [])],
            'mega_menu'    => ['detected' => false],
            'hero'         => ['detected' => in_array('hero', $sections), 'has_cta' => true, 'headline' => $visionResult['hero_text'] ?? ''],
            'grid_layouts' => [],
            'cards'        => ['types' => [], 'count' => 0],
            'features'     => ['detected' => in_array('features', $sections), 'layout' => 'grid'],
            'cta_sections' => ['detected' => true, 'count' => 1],
            'pricing'      => ['detected' => ($visionResult['has_pricing'] ?? false), 'tiers' => 3],
            'testimonials' => ['detected' => ($visionResult['has_testimonials'] ?? false), 'count' => 3],
            'faq'          => ['detected' => false, 'items' => 0],
            'blog'         => ['detected' => ($visionResult['has_blog'] ?? false), 'grid_cols' => 3],
            'footer'       => ['detected' => in_array('footer', $sections), 'columns' => 4],
            'sections_count' => count($sections),
            'depth_map'    => [],
            'extracted_at' => now()->toISOString(),
            'source'       => 'vision',
            'visual_hints' => ['colors' => $colors, 'style' => $visionResult['style'] ?? 'modern'],
        ];
    }

    /**
     * Extract from text prompt.
     */
    public function extractFromPrompt(string $prompt): array
    {
        $lower = strtolower($prompt);

        $detectedSections = [];
        $sectionMap = [
            'hero'         => ['hero', 'banner', 'بنر', 'هیرو', 'landing'],
            'features'     => ['feature', 'ویژگی', 'امکانات', 'قابلیت'],
            'pricing'      => ['price', 'pricing', 'قیمت', 'تعرفه', 'طرح'],
            'faq'          => ['faq', 'سوال', 'question'],
            'testimonials' => ['testimonial', 'review', 'نظر', 'بازخورد'],
            'blog'         => ['blog', 'وبلاگ', 'article', 'مقاله'],
            'products'     => ['product', 'shop', 'فروشگاه', 'محصول'],
            'cta'          => ['signup', 'register', 'get started', 'ثبت‌نام', 'شروع'],
        ];

        foreach ($sectionMap as $section => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $detectedSections[] = $section;
                    break;
                }
            }
        }

        $detectedSections = array_unique(array_merge(['header', 'hero', 'cta', 'footer'], $detectedSections));

        return [
            'url'          => '',
            'title'        => '',
            'meta'         => [],
            'landmarks'    => array_map(fn ($s) => ['type' => $s, 'confidence' => 60], $detectedSections),
            'header'       => ['detected' => true, 'has_logo' => true, 'nav_items' => 5],
            'mega_menu'    => ['detected' => false],
            'hero'         => ['detected' => true, 'has_cta' => true, 'headline' => ''],
            'grid_layouts' => [],
            'cards'        => ['types' => [], 'count' => 0],
            'features'     => ['detected' => in_array('features', $detectedSections), 'layout' => 'grid'],
            'cta_sections' => ['detected' => true, 'count' => 1],
            'pricing'      => ['detected' => in_array('pricing', $detectedSections), 'tiers' => 3],
            'testimonials' => ['detected' => in_array('testimonials', $detectedSections), 'count' => 3],
            'faq'          => ['detected' => in_array('faq', $detectedSections), 'items' => 5],
            'blog'         => ['detected' => in_array('blog', $detectedSections), 'grid_cols' => 3],
            'footer'       => ['detected' => true, 'columns' => 4],
            'sections_count' => count($detectedSections),
            'depth_map'    => [],
            'extracted_at' => now()->toISOString(),
            'source'       => 'prompt',
        ];
    }

    // ── Private extraction methods ────────────────────────────────────────────

    private function extractTitle(\DOMXPath $x): string
    {
        $n = $x->query('//title');
        return $n && $n->length > 0 ? trim($n->item(0)->textContent) : '';
    }

    private function extractMeta(\DOMXPath $x): array
    {
        return [
            'description' => $this->xAttr($x, '//meta[@name="description"]', 'content'),
            'keywords'    => $this->xAttr($x, '//meta[@name="keywords"]', 'content'),
            'og_title'    => $this->xAttr($x, '//meta[@property="og:title"]', 'content'),
            'og_image'    => $this->xAttr($x, '//meta[@property="og:image"]', 'content'),
            'theme_color' => $this->xAttr($x, '//meta[@name="theme-color"]', 'content'),
            'viewport'    => $this->xAttr($x, '//meta[@name="viewport"]', 'content'),
        ];
    }

    private function detectLandmarks(\DOMXPath $x, \DOMDocument $doc): array
    {
        $landmarks = [];

        foreach (self::LANDMARK_PATTERNS as $type => $selectors) {
            $found = false;
            foreach ($selectors as $selector) {
                // Convert CSS selector to XPath (simplified)
                $xpathQuery = $this->cssToXpath($selector);
                $nodes = @$x->query($xpathQuery);
                if ($nodes && $nodes->length > 0) {
                    $found = true;
                    $landmarks[] = [
                        'type'       => $type,
                        'selector'   => $selector,
                        'confidence' => 85,
                        'count'      => $nodes->length,
                    ];
                    break;
                }
            }
            if (!$found) {
                $landmarks[] = ['type' => $type, 'selector' => null, 'confidence' => 10, 'count' => 0];
            }
        }

        return $landmarks;
    }

    private function analyzeHeader(\DOMXPath $x): array
    {
        // Check header element first, then standalone nav (e.g. Apple uses <nav> at top without <header>)
        $navNodes  = $x->query('//header//nav | //nav[1] | //header[1]');
        $logoNode  = $x->query('//header//img | //header//*[contains(@class,"logo")] | //nav//img[1]');
        $linkCount = 0;
        if ($navNodes && $navNodes->length > 0) {
            $links = $x->query('.//a', $navNodes->item(0));
            $linkCount = $links ? $links->length : 0;
        }

        // Apple / modern sites: top-level nav as first element
        $topNav = $x->query('//nav[1] | //header[1]');
        $detected = ($navNodes && $navNodes->length > 0) || ($topNav && $topNav->length > 0);

        $hasSticky   = $this->hasClassKeyword($x, 'header', ['sticky', 'fixed', 'affix', 'stuck']);
        $hasMegaMenu = $this->hasClassKeyword($x, 'nav', ['mega', 'dropdown', 'mega-menu', 'nav-mega']);

        return [
            'detected'     => $detected,
            'has_logo'     => ($logoNode && $logoNode->length > 0),
            'nav_items'    => $linkCount,
            'is_sticky'    => $hasSticky,
            'has_mega_menu'=> $hasMegaMenu,
            'has_search'   => $this->hasClassKeyword($x, '*', ['search-box', 'search-bar', 'header-search']),
            'has_cta'      => $this->hasClassKeyword($x, 'header', ['cta', 'btn-header', 'header-btn', 'signup', 'get-started']),
        ];
    }

    private function detectMegaMenu(\DOMXPath $x): array
    {
        $megaNodes = $x->query('//*[contains(@class,"mega") or contains(@class,"flyout") or contains(@class,"nav-dropdown")]');
        $detected  = ($megaNodes && $megaNodes->length > 0);

        $columns = 0;
        if ($detected && $megaNodes->length > 0) {
            $cols = $x->query('.//*[contains(@class,"col")]', $megaNodes->item(0));
            $columns = $cols ? $cols->length : 0;
        }

        return [
            'detected' => $detected,
            'columns'  => min($columns, 6),
            'has_icons'=> $this->hasClassKeyword($x, '.mega', ['icon', 'svg', 'fa-']),
            'has_images'=> false,
        ];
    }

    private function analyzeHero(\DOMXPath $x): array
    {
        $heroNodes = $x->query(
            '//section[contains(@class,"hero")] | //*[contains(@class,"banner")] | //*[contains(@class,"jumbotron")] | //section[1]'
        );

        $detected = ($heroNodes && $heroNodes->length > 0);
        $h1Nodes  = $x->query('//h1');
        $headline = '';
        if ($h1Nodes && $h1Nodes->length > 0) {
            $headline = trim($h1Nodes->item(0)->textContent);
        }

        $hasVideo  = ($x->query('//video | //*[contains(@class,"video-bg")]') !== null
                      && $x->query('//video | //*[contains(@class,"video-bg")]')->length > 0);
        $hasBg     = $this->hasClassKeyword($x, '.hero', ['bg-', 'background']);
        $hasCta    = ($x->query('//h1/following-sibling::*//a[@class] | //h1/following::a[1]') !== null);

        return [
            'detected'      => $detected,
            'headline'      => mb_substr($headline, 0, 100),
            'has_video_bg'  => $hasVideo,
            'has_bg_image'  => $hasBg,
            'has_cta'       => $hasCta,
            'layout'        => $this->detectHeroLayout($x),
            'has_social_proof' => $this->hasClassKeyword($x, '.hero', ['social-proof', 'trust', 'customers', 'users']),
        ];
    }

    private function detectHeroLayout(\DOMXPath $x): string
    {
        $splitNodes = $x->query('//*[contains(@class,"hero-split") or contains(@class,"hero-two-col") or contains(@class,"split-hero")]');
        if ($splitNodes && $splitNodes->length > 0) return 'split';

        $centerNodes = $x->query('//*[contains(@class,"text-center") or contains(@class,"hero-center")]');
        if ($centerNodes && $centerNodes->length > 0) return 'centered';

        return 'standard';
    }

    private function detectGridLayouts(\DOMXPath $x): array
    {
        $grids = [];

        foreach (self::GRID_PATTERNS as $type => $classes) {
            $count = 0;
            foreach ($classes as $class) {
                $nodes = $x->query("//*[contains(@class,'{$class}')]");
                if ($nodes) $count += $nodes->length;
            }
            if ($count > 0) {
                $grids[] = [
                    'type'  => $type,
                    'count' => $count,
                    'is_responsive' => true,
                ];
            }
        }

        // Also detect CSS grid containers
        $gridContainers = $x->query('//*[contains(@class,"grid") or contains(@class,"row") or contains(@class,"flex")]');
        $total = $gridContainers ? $gridContainers->length : 0;

        return [
            'detected_types'  => $grids,
            'total_containers'=> $total,
            'primary_layout'  => count($grids) > 0 ? $grids[0]['type'] : 'col-1',
        ];
    }

    private function detectCards(\DOMXPath $x): array
    {
        $cardNodes = $x->query(
            '//*[contains(@class,"card") or contains(@class,"tile") or contains(@class,"item-card") or contains(@class,"product-card")]'
        );
        $count = $cardNodes ? $cardNodes->length : 0;

        $types = [];
        if ($this->hasClassKeyword($x, '*', ['product-card', 'product-item', 'shop-item'])) $types[] = 'product';
        if ($this->hasClassKeyword($x, '*', ['blog-card', 'post-card', 'article-card']))    $types[] = 'blog';
        if ($this->hasClassKeyword($x, '*', ['feature-card', 'service-card', 'icon-box']))  $types[] = 'feature';
        if ($this->hasClassKeyword($x, '*', ['testimonial-card', 'review-card']))           $types[] = 'testimonial';
        if ($this->hasClassKeyword($x, '*', ['team-card', 'member-card']))                  $types[] = 'team';
        if ($this->hasClassKeyword($x, '*', ['pricing-card', 'plan-card', 'price-box']))    $types[] = 'pricing';

        return [
            'count' => $count,
            'types' => $types,
            'has_hover_effects'  => $this->hasClassKeyword($x, '.card', ['hover', 'transition', 'animate']),
            'has_badges'         => $this->hasClassKeyword($x, '*', ['badge', 'tag', 'label', 'ribbon']),
        ];
    }

    private function detectFeatureSections(\DOMXPath $x): array
    {
        $featureNodes = $x->query(
            '//*[contains(@class,"features") or contains(@class,"benefits") or contains(@class,"why-us") or contains(@class,"feature-section")]'
        );
        $detected = ($featureNodes && $featureNodes->length > 0);

        // Count feature items (icon + title + desc pattern)
        $iconBoxes = $x->query('//*[contains(@class,"icon-box") or contains(@class,"feature-item") or contains(@class,"benefit-item")]');
        $itemCount = $iconBoxes ? $iconBoxes->length : 0;

        return [
            'detected'   => $detected,
            'item_count' => $itemCount,
            'layout'     => $itemCount > 0 ? ($itemCount >= 4 ? 'grid-4' : 'grid-3') : 'grid-3',
            'has_icons'  => $this->hasClassKeyword($x, '.features', ['icon', 'svg', 'fa-']),
            'has_images' => $this->hasClassKeyword($x, '.features', ['img', 'illustration']),
            'style'      => 'cards',
        ];
    }

    private function detectCtaSections(\DOMXPath $x): array
    {
        $ctaNodes = $x->query(
            '//*[contains(@class,"cta") or contains(@class,"call-to-action") or contains(@class,"signup-section") or contains(@class,"get-started")]'
        );
        $count    = $ctaNodes ? $ctaNodes->length : 0;

        $hasEmail = ($x->query('//input[@type="email"]') !== null && $x->query('//input[@type="email"]')->length > 0);

        return [
            'detected'     => $count > 0,
            'count'        => $count,
            'has_email_form' => $hasEmail,
            'style'        => $this->hasClassKeyword($x, '.cta', ['gradient', 'dark', 'colored']) ? 'gradient' : 'standard',
        ];
    }

    private function detectPricingSection(\DOMXPath $x): array
    {
        $pricingNodes = $x->query(
            '//*[contains(@class,"pricing") or contains(@class,"plans") or contains(@class,"price-table") or contains(@class,"subscription")]'
        );
        $detected = ($pricingNodes && $pricingNodes->length > 0);

        // Count pricing tiers
        $tierNodes = $x->query('//*[contains(@class,"pricing-card") or contains(@class,"plan-card") or contains(@class,"price-box")]');
        $tiers     = $tierNodes ? $tierNodes->length : 0;

        // Detect billing toggle (monthly/annual)
        $toggleNodes = $x->query('//*[contains(@class,"billing-toggle") or contains(@class,"price-toggle") or @data-billing]');
        $hasToggle   = ($toggleNodes && $toggleNodes->length > 0);

        return [
            'detected'      => $detected,
            'tiers'         => $tiers ?: 3,
            'has_toggle'    => $hasToggle,
            'has_highlight' => $this->hasClassKeyword($x, '.pricing', ['popular', 'featured', 'recommended', 'highlight']),
            'has_feature_list' => true,
        ];
    }

    private function detectTestimonials(\DOMXPath $x): array
    {
        $testNodes = $x->query(
            '//*[contains(@class,"testimonial") or contains(@class,"reviews") or contains(@class,"feedback") or contains(@class,"client-say")]'
        );
        $detected = ($testNodes && $testNodes->length > 0);

        $quoteNodes = $x->query('//blockquote | //*[@class and contains(@class,"quote")]');
        $count      = $quoteNodes ? $quoteNodes->length : 0;

        $hasSlider = $this->hasClassKeyword($x, '.testimonials', ['slider', 'swiper', 'carousel', 'owl']);
        $hasRating = $this->hasClassKeyword($x, '.testimonials', ['star', 'rating', 'stars']);

        return [
            'detected'   => $detected,
            'count'      => max($count, $detected ? 3 : 0),
            'layout'     => $hasSlider ? 'slider' : 'grid',
            'has_rating' => $hasRating,
            'has_avatar' => $this->hasClassKeyword($x, '.testimonial', ['avatar', 'photo', 'user-img']),
        ];
    }

    private function detectFaq(\DOMXPath $x): array
    {
        $faqNodes = $x->query(
            '//*[contains(@class,"faq") or contains(@class,"accordion") or contains(@class,"questions")]'
        );
        $detected = ($faqNodes && $faqNodes->length > 0);

        $itemNodes = $x->query(
            '//*[contains(@class,"faq-item") or contains(@class,"accordion-item") or contains(@class,"question-item")]'
        );
        $count = $itemNodes ? $itemNodes->length : 0;

        return [
            'detected'      => $detected,
            'items'         => max($count, $detected ? 5 : 0),
            'style'         => 'accordion',
            'has_search'    => $this->hasClassKeyword($x, '.faq', ['search', 'filter']),
            'has_categories'=> $this->hasClassKeyword($x, '.faq', ['category', 'tab', 'group']),
        ];
    }

    private function detectBlogSection(\DOMXPath $x): array
    {
        $blogNodes = $x->query(
            '//*[contains(@class,"blog") or contains(@class,"news") or contains(@class,"articles") or contains(@class,"post-grid")]'
        );
        $detected = ($blogNodes && $blogNodes->length > 0);

        $postNodes = $x->query(
            '//*[contains(@class,"blog-card") or contains(@class,"post-card") or contains(@class,"article-card")]'
        );
        $count = $postNodes ? $postNodes->length : 0;

        return [
            'detected'    => $detected,
            'count'       => max($count, $detected ? 3 : 0),
            'grid_cols'   => 3,
            'has_category'=> $this->hasClassKeyword($x, '.blog', ['category', 'tag', 'filter']),
            'has_date'    => $this->hasClassKeyword($x, '.blog', ['date', 'time', 'published']),
            'has_author'  => $this->hasClassKeyword($x, '.blog', ['author', 'writer', 'by ']),
        ];
    }

    private function analyzeFooter(\DOMXPath $x): array
    {
        $footerNode = $x->query('//footer | //*[contains(@class,"footer")]');
        $detected   = ($footerNode && $footerNode->length > 0);

        $colNodes   = $x->query('//footer//*[contains(@class,"col")] | //footer//*[contains(@class,"column")]');
        $columns    = $colNodes ? min($colNodes->length, 5) : 0;

        $socialNodes = $x->query(
            '//footer//*[contains(@class,"social")] | //footer//a[contains(@href,"twitter") or contains(@href,"facebook") or contains(@href,"instagram")]'
        );
        $hasSocial = ($socialNodes && $socialNodes->length > 0);

        $newsletterNodes = $x->query('//footer//input[@type="email"] | //footer//*[contains(@class,"newsletter")]');
        $hasNewsletter   = ($newsletterNodes && $newsletterNodes->length > 0);

        return [
            'detected'        => $detected,
            'columns'         => max($columns, $detected ? 4 : 0),
            'has_social'      => $hasSocial,
            'has_newsletter'  => $hasNewsletter,
            'has_logo'        => $this->hasClassKeyword($x, 'footer', ['logo', 'brand']),
            'has_copyright'   => $this->containsText($x, '//footer', ['©', 'copyright', 'کپی‌رایت', 'حقوق']),
            'has_contact_info'=> $this->hasClassKeyword($x, 'footer', ['address', 'phone', 'email', 'contact']),
        ];
    }

    private function countSemanticSections(\DOMXPath $x): int
    {
        $sections = $x->query('//section | //article | //main/*');
        $count = $sections ? $sections->length : 0;
        // Also count direct children of body that look like sections
        if ($count === 0) {
            $bodyChildren = $x->query('//body/*');
            $count = $bodyChildren ? min($bodyChildren->length, 10) : 0;
        }
        return $count;
    }

    private function buildDepthMap(\DOMXPath $x): array
    {
        $map = [];
        $headings = $x->query('//h1 | //h2 | //h3');
        if (!$headings) return $map;

        foreach ($headings as $h) {
            $tag = strtolower($h->nodeName);
            $map[] = [
                'level' => (int) substr($tag, 1),
                'text'  => mb_substr(trim($h->textContent), 0, 80),
            ];
            if (count($map) >= 15) break;
        }
        return $map;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function xAttr(\DOMXPath $x, string $q, string $attr): string
    {
        $n = $x->query($q);
        return $n && $n->length > 0 ? trim($n->item(0)->getAttribute($attr)) : '';
    }

    private function hasClassKeyword(\DOMXPath $x, string $element, array $keywords): bool
    {
        // If element is a CSS class selector (e.g. ".hero"), convert to wildcard + class filter
        if (str_starts_with($element, '.')) {
            $cls = substr($element, 1);
            $base = "//*[contains(@class,'{$cls}')]";
        } elseif ($element === '*') {
            $base = '//*';
        } else {
            $base = "//{$element}";
        }

        foreach ($keywords as $kw) {
            $query = str_starts_with($element, '.') || $element === '*'
                ? "//*[contains(@class,'{$kw}')]"
                : "{$base}[contains(@class,'{$kw}')]";
            $nodes = @$x->query($query);
            if ($nodes && $nodes->length > 0) return true;
        }
        return false;
    }

    private function containsText(\DOMXPath $x, string $query, array $keywords): bool
    {
        $nodes = $x->query($query);
        if (!$nodes || $nodes->length === 0) return false;
        $text  = strtolower($nodes->item(0)->textContent);
        foreach ($keywords as $kw) {
            if (str_contains($text, strtolower($kw))) return true;
        }
        return false;
    }

    /**
     * Detect rendering mode: SSR, SPA, Next.js, etc.
     */
    private function detectRenderMode(string $html, string $url): array
    {
        $isNextJs     = str_contains($html, '_next/') || str_contains($html, '__NEXT_DATA__') || str_contains($html, 'self.__next_s');
        $isReact      = str_contains($html, 'react') || str_contains($html, 'React') || str_contains($html, 'data-reactroot');
        $isVue        = str_contains($html, 'vue') || str_contains($html, '__vue_');
        $isAngular    = str_contains($html, 'ng-version') || str_contains($html, 'angular');
        $htmlSize     = strlen($html);
        $isMinimalSpa = $htmlSize < 8000;

        $mode = 'ssr';
        if ($isMinimalSpa && ($isNextJs || $isReact)) $mode = 'spa_shell';
        elseif ($isNextJs) $mode = 'nextjs_ssr';
        elseif ($isReact)  $mode = 'react_ssr';
        elseif ($isVue)    $mode = 'vue_ssr';

        // Extract __NEXT_DATA__ if present
        $nextData = [];
        if (preg_match('/<script id="__NEXT_DATA__"[^>]*>(.+?)<\/script>/s', $html, $m)) {
            $nextData = json_decode($m[1], true) ?? [];
        }

        return [
            'mode'           => $mode,
            'is_spa_shell'   => $isMinimalSpa,
            'is_nextjs'      => $isNextJs,
            'is_react'       => $isReact,
            'html_size'      => $htmlSize,
            'next_data'      => $nextData,
            'has_minified_css'=> $isNextJs || $isReact,
        ];
    }

    /**
     * Semantic HTML5 fallback — used when class-based detection finds nothing.
     * Scans for raw HTML5 structural tags.
     */
    private function semanticFallback(\DOMXPath $x, string $html): array
    {
        $hasNav    = (bool) ($x->query('//nav') !== null && $x->query('//nav')->length > 0);
        $hasHeader = (bool) ($x->query('//header') !== null && $x->query('//header')->length > 0);
        $hasFooter = (bool) ($x->query('//footer') !== null && $x->query('//footer')->length > 0);
        $hasMain   = (bool) ($x->query('//main') !== null && $x->query('//main')->length > 0);
        $sections  = $x->query('//section');
        $sCount    = $sections ? $sections->length : 0;
        $articles  = $x->query('//article');
        $aCount    = $articles ? $articles->length : 0;

        // If even semantic tags are empty, site is likely a SPA shell
        $sectionsFound = (int)($hasNav) + (int)($hasHeader) + (int)($hasFooter) + (int)($hasMain) + $sCount + $aCount;

        return [
            'has_nav'       => $hasNav || (substr_count($html, '<nav') > 0),
            'has_header'    => $hasHeader || (substr_count($html, '<header') > 0),
            'has_footer'    => $hasFooter || (substr_count($html, '<footer') > 0),
            'has_main'      => $hasMain || (substr_count($html, '<main') > 0),
            'section_count' => $sCount,
            'article_count' => $aCount,
            'sections_found'=> $sectionsFound,
        ];
    }

    private function cssToXpath(string $selector): string
    {
        // Simplified CSS → XPath converter for common patterns
        $selector = trim($selector);

        if (str_starts_with($selector, '.')) {
            $class = substr($selector, 1);
            return "//*[contains(@class,'{$class}')]";
        }
        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);
            return "//*[@id='{$id}']";
        }
        if (str_starts_with($selector, '[role="')) {
            preg_match('/\[role="([^"]+)"\]/', $selector, $m);
            return "//*[@role='{$m[1]}']";
        }
        // Tag selector
        if (ctype_alpha($selector)) {
            return "//{$selector}";
        }
        // Compound like "nav:first-of-type"
        if (str_contains($selector, ':first-of-type')) {
            $tag = explode(':', $selector)[0];
            return "//{$tag}[1]";
        }

        return "//{$selector}";
    }
}
