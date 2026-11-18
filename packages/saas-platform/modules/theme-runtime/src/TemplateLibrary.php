<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * Canvas overhaul Unit 5 (ADR-075): the ready-made template library both
 * hosts merge into their TB6 template modal.
 *
 * Templates are code, not rows: one JSON document per template under
 * resources/library/templates, with media shipped beside them in
 * resources/library/media and served by this package's own route, so the
 * library is byte-identical on every host, locally and on the server, and
 * needs no seeding, no publishing step and no tenant media IDs.
 *
 * Two tokens keep the documents portable, each replacing a whole string
 * value:
 *   "@media:<file>"  the media file's versioned, host-relative URL.
 *   "@link:<route>"  an entity link {type: entity, entity: route, id, label,
 *                    url}. customer-site's EntityLinkCatalog resolves it to
 *                    the live permalink (and to no link while the route's
 *                    module is off); every other renderer reads its `url`.
 */
final class TemplateLibrary
{
    public const ID_PREFIX = 'lib:';

    public const MEDIA_ROUTE = 'theme-runtime.library.media';

    /** The route vocabulary of "@link:" tokens: key => [label, fallback url]. */
    public const ROUTES = [
        'home' => ['صفحه اصلی', '/'],
        'shop' => ['فروشگاه', '/shop'],
        'blog' => ['وبلاگ', '/blog'],
        'courses' => ['دوره‌ها', '/courses'],
        'contact' => ['تماس با ما', '/contact'],
        // PLATFORM-THEME-SPEC-v1 (ADR-083). About is the dynamic page with the
        // slug `about` (customer-site resolves it through page.show), not a
        // fixed route. A products page is the shop: the spec's /products
        // blueprint links through "@link:shop".
        'about' => ['درباره ما', '/page/about'],
        'projects' => ['پروژه‌ها', '/page/projects'],
        // ADR-084: the header's sign-in and sign-up buttons.
        'login' => ['ورود', '/login'],
        'register' => ['ثبت‌نام', '/register'],
        'account' => ['حساب کاربری', '/login'],
        'download_center' => ['مرکز دانلود', '/page/download-center'],
        'hybrid' => ['سیستم خورشیدی هیبرید', '/page/hybrid'],
        'offgrid' => ['سیستم خورشیدی منفصل از شبکه', '/page/offgrid'],
        'ongrid' => ['سیستم خورشیدی متصل به شبکه', '/page/ongrid'],
        'solar_calculator' => ['محاسبه‌گر خورشیدی', '/page/solar-calculator'],
    ];

    /** Template type => the editor zone its blocks belong to. */
    private const ZONES = ['header' => 'header', 'footer' => 'footer'];

    private const TYPE_LABELS = [
        'section' => 'بخش', 'page' => 'صفحه', 'header' => 'هدر', 'footer' => 'فوتر',
    ];

    private const CATEGORY_LABELS = [
        'navigation' => 'ناوبری', 'hero' => 'هیرو', 'features' => 'ویژگی‌ها', 'product' => 'محصول',
        'blog' => 'وبلاگ', 'testimonials' => 'اعتماد و آمار', 'pricing' => 'قیمت‌گذاری', 'faq' => 'سؤالات متداول',
        'store' => 'فروشگاه', 'corporate' => 'شرکتی',
    ];

    /** @var array<string, array>|null */
    private static ?array $documents = null;

    public static function isLibraryId(mixed $id): bool
    {
        return is_string($id) && str_starts_with($id, self::ID_PREFIX)
            && isset(self::documents()[substr($id, strlen(self::ID_PREFIX))]);
    }

    /**
     * Modal cards, in library order, filtered like the hosts' own
     * template lists (type, category, free-text term).
     *
     * @param ?string $activeThemeSlug When provided, only returns universal templates
     *   plus templates explicitly matching this theme's slug (isolating theme components).
     * @return list<array<string, mixed>>
     */
    public static function list(?string $type = null, ?string $category = null, ?string $term = null, ?string $activeThemeSlug = null): array
    {
        $term = trim((string) $term);
        $cards = [];
        foreach (self::documents() as $key => $doc) {
            $docThemeSlug = self::themeSlugForDoc($key, $doc);

            // Theme isolation: if document belongs to a specific theme and activeThemeSlug does not match, omit it.
            if ($activeThemeSlug !== null && $docThemeSlug !== null && $docThemeSlug !== $activeThemeSlug) {
                continue;
            }

            if (($type && $doc['type'] !== $type) || ($category && $doc['category'] !== $category)) {
                continue;
            }
            if ($term !== '' && !str_contains(mb_strtolower($doc['name'] . ' ' . ($doc['description'] ?? '') . ' ' . implode(' ', $doc['tags'] ?? [])), mb_strtolower($term))) {
                continue;
            }
            $cards[] = [
                'id' => self::ID_PREFIX . $key,
                'source' => 'library',
                'name' => $doc['name'],
                'description' => $doc['description'] ?? '',
                'type' => $doc['type'],
                'type_label' => self::TYPE_LABELS[$doc['type']] ?? $doc['type'],
                'category' => $doc['category'],
                'category_label' => self::CATEGORY_LABELS[$doc['category']] ?? $doc['category'],
                'zone' => self::zone($doc['type']),
                'tags' => $doc['tags'] ?? [],
                'theme_slug' => $docThemeSlug,
                'thumbnail_url' => isset($doc['thumbnail']) ? self::mediaUrl($doc['thumbnail']) : null,
            ];
        }

        return $cards;
    }

    /** Resolve which theme a library template belongs to, or null if universal. */
    public static function themeSlugForDoc(string $key, array $doc): ?string
    {
        if (!empty($doc['theme_slug'])) {
            return (string) $doc['theme_slug'];
        }
        if (str_starts_with($key, 'solar-') || str_starts_with($key, 'auth-solar-') || ($doc['spec'] ?? '') === 'SOLAR-INDUSTRY-v1') {
            return 'solar-industry';
        }
        return null;
    }

    /**
     * A template's blocks with every token resolved and every node given
     * an id and a children list. Hosts still regenerate ids on insert.
     *
     * @return array{zone: string, blocks: list<array>}|null
     */
    public static function find(string $id): ?array
    {
        if (!self::isLibraryId($id)) {
            return null;
        }
        $key = substr($id, strlen(self::ID_PREFIX));

        return ['zone' => self::zone(self::documents()[$key]['type']), 'blocks' => array_map([self::class, 'node'], self::rawBlocks($key))];
    }

    /**
     * A page template may `compose` section templates by key instead of
     * repeating their blocks, so a section is authored exactly once.
     *
     * @return list<array>
     */
    private static function rawBlocks(string $key): array
    {
        $doc = self::documents()[$key] ?? throw new \InvalidArgumentException("Unknown template library key [{$key}].");
        if (!isset($doc['compose'])) {
            return $doc['blocks'];
        }

        return array_merge(...array_map(fn (string $part) => self::rawBlocks($part), $doc['compose']));
    }

    /** The editor zone (page, header, footer) a template type inserts into. */
    public static function zone(string $type): string
    {
        return self::ZONES[$type] ?? 'page';
    }

    /** @return array<string, string> media file => absolute path */
    public static function mediaFiles(): array
    {
        $files = [];
        foreach (glob(self::root() . '/media/*.webp') ?: [] as $path) {
            $files[basename($path)] = $path;
        }

        return $files;
    }

    /** Host-relative and content-versioned, so caches can keep it forever. */
    public static function mediaUrl(string $file): string
    {
        $path = self::mediaFiles()[$file] ?? null;
        if ($path === null) {
            throw new \InvalidArgumentException("Unknown template library media [{$file}].");
        }

        return '/theme-runtime/library/' . rawurlencode($file) . '?v=' . substr(hash_file('sha256', $path), 0, 12);
    }

    /** The entity link an "@link:" token stands for. */
    public static function link(string $route): array
    {
        [$label, $url] = self::ROUTES[$route] ?? throw new \InvalidArgumentException("Unknown template library route [{$route}].");

        return ['type' => 'entity', 'entity' => 'route', 'id' => $route, 'label' => $label, 'url' => $url];
    }

    /** @return array<string, array> key => raw document */
    public static function documents(): array
    {
        if (self::$documents !== null) {
            return self::$documents;
        }
        $docs = [];
        foreach (glob(self::root() . '/templates/*.json') ?: [] as $path) {
            $doc = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $docs[basename($path, '.json')] = $doc;
        }
        uasort($docs, fn (array $a, array $b) => [$a['order'] ?? 999, $a['name']] <=> [$b['order'] ?? 999, $b['name']]);

        return self::$documents = $docs;
    }

    /**
     * PLATFORM-THEME-SPEC-v1 (ADR-083): token presets under
     * resources/library/tokens — key => {name, description, global_styles}.
     * `global_styles` is exactly the shape ThemeTokenNormalizer reads, so a
     * preset applies by copying it onto a Theme; the blueprints themselves
     * carry no colour and follow whichever preset the Theme uses.
     *
     * @return array<string, array>
     */
    public static function tokenPresets(): array
    {
        $presets = [];
        foreach (glob(self::root() . '/tokens/*.json') ?: [] as $path) {
            $preset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $presets[basename($path, '.json')] = $preset;
        }
        ksort($presets);

        return $presets;
    }

    private static function node(array $block): array
    {
        $block['id'] ??= 'lib_' . $block['type'];
        $block['props'] = self::tokens($block['props'] ?? []);
        // Authored as arrays; stored the way the registry declares each
        // repeater (ADR-060), exactly as the Canvas editor would save it.
        foreach (BlockRegistry::all()[$block['type']]['schema'] ?? [] as $prop => $def) {
            if (($def['storage'] ?? null) === 'json' && is_array($block['props'][$prop] ?? null)) {
                $block['props'][$prop] = json_encode($block['props'][$prop], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $block['children'] = array_map([self::class, 'node'], $block['children'] ?? []);

        return $block;
    }

    private static function tokens(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'tokens'], $value);
        }
        if (is_string($value) && str_starts_with($value, '@media:')) {
            return self::mediaUrl(substr($value, 7));
        }
        if (is_string($value) && str_starts_with($value, '@link:')) {
            return self::link(substr($value, 6));
        }

        return $value;
    }

    private static function root(): string
    {
        return dirname(__DIR__) . '/resources/library';
    }
}
