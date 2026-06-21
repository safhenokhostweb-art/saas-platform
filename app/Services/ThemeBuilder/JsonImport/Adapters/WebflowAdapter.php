<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Support\Str;

/**
 * Converts Webflow Export JSON to normalized ThemeBuilder structure.
 *
 * Webflow export structure:
 *   { type: '@webflow/XscpData', payload: { site, pages, styles, assets } }
 *   OR simplified: { pages: [...], styles: [...] }
 *
 * Each page has a `dsl` or component tree under `body`.
 */
class WebflowAdapter
{
    public function __construct(private readonly WidgetMapper $mapper) {}

    public function convert(array $json): array
    {
        $payload = $json['payload'] ?? $json;
        $site    = $payload['site'] ?? [];
        $pages   = $payload['pages'] ?? [];
        $styles  = $payload['styles'] ?? [];
        $assets  = $payload['assets'] ?? [];

        $title        = $site['name'] ?? $json['name'] ?? 'Webflow Site';
        $globalStyles = $this->extractGlobalStyles($styles);

        if (empty($pages)) {
            // Minimal export — treat as single page
            $pages = [['name' => 'Home', 'slug' => '', 'body' => $payload['body'] ?? []]];
        }

        $convertedPages = [];
        $headerBlocks   = [];
        $footerBlocks   = [];
        $first          = true;

        foreach ($pages as $page) {
            $pageName = $page['name'] ?? $page['title'] ?? 'صفحه';
            $pageSlug = $page['slug'] ?? Str::slug($pageName);
            $isHome   = $first || $pageSlug === '' || in_array(strtolower($pageSlug), ['home', 'index', '/']);
            $body     = $page['body'] ?? $page['components'] ?? $page['dsl'] ?? [];

            $blocks = $this->processNodes($body);

            [$h, $f, $bodyBlocks] = $this->splitHeaderFooter($blocks);
            if ($first) {
                $headerBlocks = $h;
                $footerBlocks = $f;
            }

            $key = $isHome ? 'home' : $pageSlug;
            $convertedPages[$key] = [
                'title'  => $pageName,
                'slug'   => $isHome ? '' : $pageSlug,
                'blocks' => $bodyBlocks,
            ];
            $first = false;
        }

        return [
            'pages'         => $convertedPages,
            'global_styles' => $globalStyles,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'meta' => ['source' => 'webflow', 'title' => $title],
        ];
    }

    // ── Node processing ───────────────────────────────────────────────────────

    private function processNodes(array $nodes): array
    {
        $blocks = [];
        foreach ($nodes as $node) {
            $block = $this->processNode($node);
            if ($block) $blocks[] = $block;
        }
        return $blocks;
    }

    private function processNode(array $node): ?array
    {
        $type     = $node['type'] ?? $node['_type'] ?? '';
        $tag      = $node['tag'] ?? '';
        $data     = $node['data'] ?? $node['settings'] ?? [];
        $text     = $node['text'] ?? $node['v'] ?? '';

        // Webflow uses type OR tag to indicate element type
        $nodeType = !empty($type) ? $type : $this->tagToWebflowType($tag);

        if (empty($nodeType) || in_array($nodeType, ['Body', 'Page', 'Slot'])) {
            // Recurse into children only
            return $this->wrapChildrenInSection($node);
        }

        // Text nodes
        if ($nodeType === 'TextNode' || (!empty($text) && empty($node['children']))) {
            return null; // Text content is carried by parent
        }

        // Collect text from children for leaf nodes
        if (!empty($text)) {
            $data['text'] = $text;
        }

        $children = $this->processNodes($node['children'] ?? $node['nodes'] ?? []);

        return $this->mapper->mapNode('webflow', $nodeType, $data, $children);
    }

    private function wrapChildrenInSection(array $node): ?array
    {
        $children = $this->processNodes($node['children'] ?? $node['nodes'] ?? []);
        if (empty($children)) return null;

        if (count($children) === 1) return $children[0];

        return [
            'id'    => Str::random(8),
            'type'  => 'section',
            'props' => ['padding' => 'lg', 'background' => 'transparent', 'container' => true, 'blocks' => $children],
            'blocks'=> $children,
        ];
    }

    private function tagToWebflowType(string $tag): string
    {
        return match (strtolower($tag)) {
            'section', 'article', 'main' => 'Section',
            'div'                        => 'Div Block',
            'h1', 'h2', 'h3', 'h4'      => 'Heading',
            'p'                          => 'Paragraph',
            'img'                        => 'Image',
            'video'                      => 'Video',
            'a'                          => 'Link',
            'button'                     => 'Button',
            'ul', 'ol'                   => 'List',
            'li'                         => 'List Item',
            'form'                       => 'Form Wrapper',
            'nav', 'header'              => 'NavbarWrapper',
            'footer'                     => 'footer',
            default                      => 'Div Block',
        };
    }

    // ── Header / footer split ────────────────────────────────────────────────

    private function splitHeaderFooter(array $blocks): array
    {
        if (empty($blocks)) return [[], [], []];

        $header = [];
        $footer = [];
        $body   = [];

        $first = $blocks[0];
        $last  = end($blocks);

        // First block as navbar type → header
        if (in_array($first['type'] ?? '', ['heading', 'container']) && isset($blocks[0])) {
            $header[] = array_shift($blocks);
        }

        // Last block → footer
        if (!empty($blocks)) {
            $lastBlock = end($blocks);
            $footer[]  = array_pop($blocks);
        }

        return [$header, $footer, $blocks];
    }

    // ── Global styles ─────────────────────────────────────────────────────────

    private function extractGlobalStyles(array $styles): array
    {
        $primary   = '#6366f1';
        $secondary = '#8b5cf6';
        $bg        = '#ffffff';
        $text      = '#1f2937';

        foreach ($styles as $style) {
            $props = $style['style'] ?? $style['properties'] ?? [];
            if (!empty($props['background-color']) && $bg === '#ffffff') {
                $bg = $props['background-color'];
            }
            if (!empty($props['color']) && $text === '#1f2937') {
                $text = $props['color'];
            }
        }

        return [
            'primaryColor'    => $primary,
            'secondaryColor'  => $secondary,
            'backgroundColor' => $bg,
            'textColor'       => $text,
            'fontFamily'      => 'Vazirmatn',
            'direction'       => 'rtl',
        ];
    }
}
