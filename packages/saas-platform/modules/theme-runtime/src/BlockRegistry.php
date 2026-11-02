<?php
namespace SaaSPlatform\ThemeRuntime;

/**
 * Phase 2h (ADR-029) — the ONE canonical registry of block types shared by
 * the saas-platform Builder preview and customer-site tenant runtime. Used
 * by editor (schema + defaults) and by renderer (type → render). RTL and
 * Persian optimized.
 *
 * Not `final`: a host may register genuinely host-specific, live-data types
 * (e.g. customer-site's real product/course/blog listing blocks) via
 * registerHostType() — an additive extension point, not a fork. Canonical
 * type definitions below are the one shared authority and are never
 * overridden by a host registration of the same key.
 */
class BlockRegistry
{
    private static ?array $blocks = null;
    private static array $hostTypes = [];
    private static ?array $canonicalOnly = null;

    /**
     * Adds a genuinely host-specific block type definition (e.g. a
     * live-data listing block only one host's runtime can render) without
     * forking this class. Never overrides a canonical type defined below.
     *
     * Phase 3E-B (ADR-030): fails closed, not silently. A colliding
     * registration throws BlockTypeAlreadyRegisteredException and never
     * mutates registry state — the prior silent "canonical always wins the
     * merge" behavior left a caller with no way to know its registration
     * was ignored. Re-registering the SAME type with an identical
     * definition is a no-op (not an error): idempotent boot-time
     * registration is a real, legitimate case (e.g. under a persistent
     * worker where a service provider's boot() runs more than once in the
     * same process) and must not fail merely because it repeats.
     */
    public static function registerHostType(string $type, array $definition): void
    {
        self::all(); // ensure the canonical-only snapshot is computed first

        if (array_key_exists($type, self::$canonicalOnly)) {
            throw BlockTypeAlreadyRegisteredException::canonicalCollision($type);
        }

        if (array_key_exists($type, self::$hostTypes) && self::$hostTypes[$type] !== $definition) {
            throw BlockTypeAlreadyRegisteredException::hostCollision($type);
        }

        self::$hostTypes[$type] = $definition;
        self::$blocks = null; // force recomputation on next all()
    }

    /** True if `$type` is one of the canonical definitions below (never a host/extension registration). */
    public static function isCanonicalType(string $type): bool
    {
        self::all();
        return array_key_exists($type, self::$canonicalOnly);
    }

    /**
     * Phase 3F-B — the one canonical/shared answer to "does this type accept
     * child blocks?", for CANONICAL types only (host-registered and
     * extension types declare this through their own host-side capability
     * contracts — e.g. customer-site's HasContainerCapability — layered on
     * top of this by each host, never duplicated here). Declarative: reads
     * the explicit `is_container` flag on the type's own definition above,
     * never inferred from schema field types (several canonical types
     * declare a `type => 'blocks'` schema field — e.g. `slider`'s `slides`
     * — without being authoring-time containers today; inferring from that
     * would silently change their behavior, which this method must not do).
     * Unknown or unregistered types fail closed to false.
     */
    public static function isContainerType(string $type): bool
    {
        return (bool) (self::all()[$type]['is_container'] ?? false);
    }

    /** True if `$type` was added via registerHostType() (not a canonical definition). */
    public static function isHostRegisteredType(string $type): bool
    {
        return array_key_exists($type, self::$hostTypes);
    }

    /** Test-only: clears host-registered types between test cases. */
    public static function resetHostTypes(): void
    {
        self::$hostTypes = [];
        self::$blocks = null;
        self::$canonicalOnly = null;
    }

    public const CATEGORY_LAYOUT = 'layout';
    public const CATEGORY_CONTENT = 'content';
    public const CATEGORY_DYNAMIC = 'dynamic';
    public const CATEGORY_ECOMMERCE = 'ecommerce';
    public const CATEGORY_MARKETING = 'marketing';
    /** Phase 3C/M3 — named constant for the existing 'forms' category slug
     *  (already whitelisted in WidgetDefinition::CATEGORIES and used by the
     *  interactive_form host widget); previously only referenced as a bare
     *  string. */
    public const CATEGORY_FORMS = 'forms';
    public const CATEGORY_UTILITY = 'utility';
    public const CATEGORY_MODULE = 'module';

    /**
     * Phase 2f (ADR-027) — the durable, registry-level distinction
     * RenderIntegrityCheck's render.empty_output finding is driven by
     * (see BlockRegistry::emptyOutputPolicy()), so that check never needs a
     * hardcoded list of block-type names to know which empty renders are
     * accidental vs. expected:
     *
     *   EMPTY_POLICY_FLAGGED (the default when a type declares none) —
     *     this type's content is fully author-supplied; genuinely empty
     *     output means required commercial content is missing. Treated as
     *     a BLOCKING defect, not a warning — an authored-but-content-free
     *     canonical block is not a passable commercial state.
     *
     *   EMPTY_POLICY_PLACEHOLDER — this type's renderer contractually never
     *     returns true emptiness: when the real (often runtime-sourced)
     *     content isn't available, it substitutes an honest, non-fabricated
     *     placeholder instead (see renderTeam()/renderClientLogos()/
     *     renderBlogPosts()/renderTestimonials()/renderPriceBox()/
     *     renderCountdown()). If a type declared this way is EVER observed
     *     to render truly empty, that is a broken contract — still
     *     BLOCKING, because it means the renderer regressed, not that the
     *     content is legitimately absent.
     *
     *   EMPTY_POLICY_OPTIONAL — this type is a deliberately optional
     *     accent with no other field that could signal authorial intent
     *     (e.g. promo_banner: text is its only content field). Empty output
     *     is the ordinary, expected way to leave it unused — reported as an
     *     explicit, non-blocking, informational fact, never as a defect and
     *     never hidden by suppressing the finding entirely.
     */
    public const EMPTY_POLICY_FLAGGED = 'flagged';
    public const EMPTY_POLICY_PLACEHOLDER = 'placeholder';
    public const EMPTY_POLICY_OPTIONAL = 'optional';

    /**
     * Phase 0.2c (ADR-062): the spacing-scale tokens BlockRenderer::
     * spacingClass() accepts, as select options. section.padding and
     * grid/columns.gap store one of these tokens; they were typed
     * `spacing`, which the Inspector contract reserves for a structured
     * four-side value, so neither editor rendered any control for them.
     */
    public const SPACING_OPTIONS = [
        'none' => 'بدون فاصله',
        'xs'   => 'خیلی کم',
        'sm'   => 'کم',
        'md'   => 'متوسط',
        'lg'   => 'زیاد',
        'xl'   => 'خیلی زیاد',
    ];

    /**
     * @return string One of the EMPTY_POLICY_* constants — EMPTY_POLICY_FLAGGED
     *   when the type is unregistered or declares no explicit policy.
     */
    public static function emptyOutputPolicy(string $type): string
    {
        return self::all()[$type]['emptyOutputPolicy'] ?? self::EMPTY_POLICY_FLAGGED;
    }

    /**
     * ADR-076: the social/messenger link list shared by nav_header and
     * nav_footer. Its `network` options come from Support\SocialNetworks, the
     * same rows the renderer draws icons from.
     */
    private static function socialLinksField(string $label, ?string $group = null): array
    {
        $field = ['type' => 'repeater', 'label' => $label, 'item_label' => 'شبکه', 'storage' => 'array', 'fields' => [
            'url' => ['type' => 'link', 'label' => 'آدرس', 'default' => '#'],
            'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
            'network' => ['type' => 'select', 'label' => 'شبکه (آیکون)', 'options' => Support\SocialNetworks::options(), 'default' => 'auto'],
        ], 'default' => []];

        return $group === null ? $field : $field + ['group' => $group];
    }

    /** ADR-076: mono icons follow the block's text colour; brand fills each with its network colour. */
    private static function socialStyleField(?string $group = null): array
    {
        $field = ['type' => 'select', 'ui' => 'segmented', 'label' => 'رنگ آیکون‌ها', 'options' => ['mono' => 'هم‌رنگ متن', 'brand' => 'رنگ برند هر شبکه'], 'default' => 'mono'];

        return $group === null ? $field : $field + ['group' => $group];
    }

    /**
     * All block definitions: type => [ name, category, icon, schema, defaultProps ]
     */
    public static function all(): array
    {
        if (self::$blocks !== null) {
            return self::$blocks;
        }

        self::$blocks = [
            // ─── LAYOUT ─────────────────────────────────────────────────────
            'section' => [
                'name' => 'بخش (Section)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'layout-section',
                'is_container' => true,
                'schema' => [
                    'padding' => ['type' => 'select', 'label' => 'فاصله داخلی', 'options' => self::SPACING_OPTIONS, 'default' => 'md'],
                    'background' => ['type' => 'string', 'label' => 'رنگ پس‌زمینه', 'default' => 'transparent'],
                    'background_image' => ['type' => 'image', 'label' => 'تصویر پس‌زمینه', 'default' => ''],
                    'container' => ['type' => 'boolean', 'label' => 'محتوا در عرض استاندارد', 'default' => true],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['padding' => 'md', 'background' => 'transparent', 'container' => true, 'blocks' => []],
            ],
            'container' => [
                'name' => 'کانتینر',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'container',
                'is_container' => true,
                'schema' => [
                    'maxWidth' => ['type' => 'string', 'label' => 'حداکثر عرض', 'default' => '7xl'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['maxWidth' => '7xl', 'blocks' => []],
            ],
            'grid' => [
                'name' => 'گرید',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'grid',
                'is_container' => true,
                'schema' => [
                    'columns' => ['type' => 'number', 'label' => 'تعداد ستون', 'default' => 3],
                    'gap' => ['type' => 'select', 'label' => 'فاصله', 'options' => self::SPACING_OPTIONS, 'default' => 'md'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['columns' => 3, 'gap' => 'md', 'blocks' => []],
            ],
            'columns' => [
                'name' => 'ستون‌ها',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'columns',
                'is_container' => true,
                'schema' => [
                    // value => label map, the same options shape as every other
                    // select here (InspectorFieldRegistry::normalizeSelect allowlists
                    // by key). `ui` asks the Canvas to render the visual column
                    // picker instead of a plain dropdown — one control, never both.
                    'ratios' => [
                        'type' => 'select',
                        'ui' => 'column_picker',
                        'label' => 'چیدمان ستون‌ها',
                        'options' => [
                            '1' => '۱ ستون کامل (تمام‌عرض)',
                            '1/2,1/2' => '۲ ستون مساوی (۵۰ / ۵۰)',
                            '1/3,1/3,1/3' => '۳ ستون مساوی (۳۳ / ۳۳ / ۳۳)',
                            '1/4,1/4,1/4,1/4' => '۴ ستون مساوی (۲۵ / ۲۵ / ۲۵ / ۲۵)',
                        ],
                        'default' => '1/2,1/2',
                    ],
                    'gap' => ['type' => 'select', 'label' => 'فاصله ستون‌ها', 'options' => self::SPACING_OPTIONS, 'default' => 'md'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['ratios' => '1/2,1/2', 'gap' => 'md', 'blocks' => []],
            ],
            'flex_container' => [
                'name'     => 'فلکس کانتینر',
                'category' => self::CATEGORY_LAYOUT,
                'icon'     => 'flex-container',
                'is_container' => true,
                'schema'   => [
                    'label'          => ['type' => 'string',  'label' => 'نام',               'default' => 'کانتینر'],
                    'direction'      => ['type' => 'select',  'label' => 'جهت',               'default' => 'column',
                                         'options' => ['column' => 'عمودی', 'row' => 'افقی']],
                    'gap'            => ['type' => 'select',  'label' => 'فاصله بین بلوک‌ها', 'default' => 'md',
                                         'options' => ['none' => 'هیچ', 'xs' => 'خیلی کم', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد', 'xl' => 'خیلی زیاد']],
                    'alignItems'     => ['type' => 'select',  'label' => 'تراز (align)',       'default' => 'stretch',
                                         'options' => ['stretch' => 'کشیده', 'flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها']],
                    'justifyContent' => ['type' => 'select',  'label' => 'توزیع (justify)',    'default' => 'flex-start',
                                         'options' => ['flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها', 'space-between' => 'فاصله برابر', 'space-around' => 'فاصله دور']],
                    'wrap'           => ['type' => 'boolean', 'label' => 'شکست خط',            'default' => false],
                    'minHeight'      => ['type' => 'number',  'label' => 'ارتفاع حداقل (px)',  'default' => 0],
                ],
                'defaultProps' => [
                    'label' => 'کانتینر', 'direction' => 'column', 'gap' => 'md',
                    'alignItems' => 'stretch', 'justifyContent' => 'flex-start',
                    'wrap' => false, 'minHeight' => 0,
                ],
            ],
            // TABS (Full Product Commercial Audit, TASK 2A — tabs container
            // authoring fix): `tabs` used to carry its per-tab content in a
            // `tabs` prop shaped `[{title, blocks}]` — a second, JSON-only
            // content tree with no real authoring UI (no repeater/array
            // control existed for it, and it was never the canonical block
            // tree). It is now a real structural container (`is_container`,
            // exactly like section/container/grid above): each tab is a
            // real `tab_panel` CHILD block in the canonical tree, authored
            // with the same add/remove/reorder/edit-children controls every
            // other container already has — one content authority, no JSON.
            // Legacy `tabs`-prop data is migrated to real `tab_panel`
            // children client-side on load (canvas-editor.blade.php's
            // normalizeBlock()) and, for any legacy data never reopened in
            // the editor, still renders identically via renderTabs()'s own
            // read-compat fallback below — see BlockRenderer::renderTabs().
            'tabs' => [
                'name' => 'تب‌ها',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'tabs',
                'is_container' => true,
                'schema' => [
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['blocks' => []],
            ],
            // One real child block per tab — title is real prop-panel-
            // authored metadata, content is the tab_panel's own canonical
            // children (itself a container, so it can hold anything any
            // other container can).
            'tab_panel' => [
                'name' => 'پنل تب',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'tabs',
                'is_container' => true,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان تب', 'default' => 'تب'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['title' => 'تب', 'blocks' => []],
            ],
            'accordion' => [
                'name' => 'آکوردئون',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'accordion',
                'schema' => [
                    'items' => ['type' => 'array', 'label' => 'موارد آکاردئون', 'default' => [['title' => 'عنوان', 'blocks' => []]]],
                ],
                'defaultProps' => ['items' => [['title' => 'عنوان', 'blocks' => []]]],
            ],
            // Universal Multipurpose Slider Overhaul: additive-only schema
            // growth — 'transition'/'boxed'/'aspect_ratio'/'overlay' are new
            // and all default to the exact visual behavior the renderer
            // already shipped before this change (slide transition,
            // full-width, 16/9, dark overlay on), so existing authored
            // content (including per-slide image/title/subtitle/link/
            // cta_text, an existing plain-data shape — see
            // BlockRenderer::renderSlider()) renders identically unless an
            // admin explicitly opts into a new setting.
            'slider' => [
                'name' => 'اسلایدر تصویری',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'slider',
                'schema' => [
                    // Phase 0.2: promoted from customer-site's tested definition
                    // (was `type: blocks`, which no editor could author).
                    'slides' => ['type' => 'repeater', 'label' => 'اسلایدها', 'item_label' => 'اسلاید', 'storage' => 'array', 'default' => [], 'fields' => [
                        'image'    => ['type' => 'image', 'label' => 'تصویر اسلاید', 'default' => ''],
                        'title'    => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                        'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                        'link'     => ['type' => 'link', 'label' => 'لینک (اختیاری)', 'default' => ''],
                        'cta_text' => ['type' => 'string', 'label' => 'متن دکمه CTA (اختیاری)', 'default' => ''],
                    ]],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                    'interval' => ['type' => 'number', 'label' => 'فاصله زمانی (میلی‌ثانیه)', 'default' => 5000],
                    'transition' => ['type' => 'select', 'label' => 'نوع گذار', 'options' => ['slide' => 'اسلاید', 'fade' => 'محو شدن'], 'default' => 'slide'],
                    'boxed' => ['type' => 'boolean', 'label' => 'حالت جعبه‌ای (Boxed)', 'default' => false],
                    'aspect_ratio' => ['type' => 'select', 'label' => 'نسبت تصویر', 'options' => ['16/9' => '16:9', '21/9' => '21:9', '4/3' => '4:3', '1/1' => '1:1'], 'default' => '16/9'],
                    'overlay' => ['type' => 'boolean', 'label' => 'گرادیان روی تصویر', 'default' => true],
                ],
                // Phase 0.2c: two starter slides (customer-site's tested
                // default) — renderSlider() outputs nothing for zero
                // slides, so a freshly inserted slider was invisible.
                'defaultProps' => [
                    'slides' => [
                        ['image' => '', 'title' => 'عنوان اسلاید ۱', 'subtitle' => '', 'link' => '', 'cta_text' => ''],
                        ['image' => '', 'title' => 'عنوان اسلاید ۲', 'subtitle' => '', 'link' => '', 'cta_text' => ''],
                    ],
                    'autoplay' => true, 'interval' => 5000, 'transition' => 'slide', 'boxed' => false, 'aspect_ratio' => '16/9', 'overlay' => true,
                ],
            ],
            'modal' => [
                'name' => 'مودال',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'modal',
                // BLD-04..09: `blocks` is real nested dialog content, the
                // same shape every other container type here has —
                // `is_container` is what BOTH this host's own dispatch
                // (BlockRenderer::isStructuralContainer(), via the parent::
                // call every host override chains through) AND customer-
                // site's registry-driven BlockRegistry::isContainerType()
                // read to decide whether to populate $inner automatically.
                'is_container' => true,
                'schema' => [
                    'trigger_text' => ['type' => 'string', 'label' => 'متن دکمهٔ باز کردن', 'default' => 'باز کردن'],
                    'blocks' => ['type' => 'blocks', 'default' => []],
                ],
                'defaultProps' => ['trigger_text' => 'باز کردن', 'blocks' => []],
            ],
            'hero' => [
                'name' => 'بخش قهرمان (Hero)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان اصلی', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => ''],
                    'button_url' => ['type' => 'link', 'label' => 'لینک دکمه', 'default' => ''],
                    'background' => ['type' => 'string', 'label' => 'پس‌زمینه', 'default' => 'primary'],
                    'background_image' => ['type' => 'image', 'label' => 'تصویر پس‌زمینه', 'default' => ''],
                    'align' => ['type' => 'string', 'label' => 'تراز', 'default' => 'center'],
                ],
                'defaultProps' => [
                    'title' => '', 'subtitle' => '', 'button_text' => '', 'button_url' => '',
                    'background' => 'primary', 'background_image' => '', 'align' => 'center',
                ],
            ],

            // ─── Phase 3B — professional Persian hero family ────────────
            // Additive, non-duplicating (rationale per the spacer/divider
            // pattern): the flat `hero` above is preserved VERBATIM for all
            // existing authored content and the release ledger; this
            // container family provides the granular, fully
            // Inspector-editable composition the flat type cannot, without
            // replacing it. Leaves stay composed from existing primitives
            // (badge/heading/text/button) — no second title/button type, no
            // parallel vocabulary. All consume the 2B Inspector contract
            // and 2C structural pipeline unchanged.
            'hero_section' => [
                'name' => 'بخش قهرمان حرفه‌ای (Hero Section)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero',
                'is_container' => true,
                'schema' => [
                    'background_type'  => ['type' => 'select', 'label' => 'نوع پس‌زمینه',
                        'options' => ['color' => 'رنگ ساده', 'gradient' => 'گرادیان', 'image' => 'تصویر'],
                        'default' => 'gradient'],
                    'bg_color'         => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => 'var(--tb-primary, #4f46e5)'],
                    'gradient_from'    => ['type' => 'color', 'label' => 'گرادیان — ابتدا', 'default' => 'var(--tb-primary, #4f46e5)'],
                    'gradient_to'      => ['type' => 'color', 'label' => 'گرادیان — انتها', 'default' => 'var(--tb-secondary, #7c3aed)'],
                    'bg_image'         => ['type' => 'image', 'label' => 'تصویر پس‌زمینه', 'default' => ''],
                    'overlay_opacity'  => ['type' => 'number', 'label' => 'شفافیت پوشش تصویر (٪)', 'min' => 0, 'max' => 90, 'default' => 40],
                    'min_height'       => ['type' => 'number', 'label' => 'حداقل ارتفاع (px)', 'min' => 280, 'max' => 1200, 'default' => 520],
                    'padding_v'        => ['type' => 'number', 'label' => 'پدینگ عمودی (px)', 'min' => 0, 'max' => 240, 'default' => 96],
                    // Inspector UI renders select/number/string/color/boolean/link
                    // controls (canvas.blade.php loop); alignment/range have no
                    // control template yet. Logical start/center/end values are
                    // kept — the SELECT offers only logical choices, and
                    // direction resolution stays in CSS (never stored physical).
                    'text_align'       => ['type' => 'select', 'label' => 'چینش متن',
                        'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'انتها'], 'default' => 'start'],
                    'v_align'          => ['type' => 'select', 'label' => 'تراز عمودی محتوا',
                        'options' => ['start' => 'بالا', 'center' => 'وسط', 'end' => 'پایین'], 'default' => 'center'],
                    'content_width'    => ['type' => 'select', 'label' => 'عرض محتوا',
                        'options' => ['narrow' => 'باریک', 'normal' => 'معمولی', 'wide' => 'پهن', 'full' => 'تمام‌عرض'], 'default' => 'normal'],
                ],
                'defaultProps' => [
                    'background_type' => 'gradient', 'bg_color' => 'var(--tb-primary, #4f46e5)',
                    'gradient_from' => 'var(--tb-primary, #4f46e5)', 'gradient_to' => 'var(--tb-secondary, #7c3aed)',
                    'bg_image' => '', 'overlay_opacity' => 40, 'min_height' => 520,
                    'padding_v' => 96, 'text_align' => 'start', 'v_align' => 'center', 'content_width' => 'normal',
                ],
            ],
            'hero_container' => [
                'name' => 'قهرمان — کانتینر',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'container',
                'is_container' => true,
                'schema' => [
                    'max_width' => ['type' => 'number', 'label' => 'حداکثر عرض (px، ۰ = تمام‌عرض)', 'min' => 0, 'max' => 1600, 'default' => 1200],
                    'gap'       => ['type' => 'number', 'label' => 'فاصله ستون‌ها (px)', 'min' => 0, 'max' => 96, 'default' => 32],
                ],
                'defaultProps' => ['max_width' => 1200, 'gap' => 32],
            ],
            'hero_content_wrapper' => [
                'name' => 'قهرمان — محتوا',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'text',
                'is_container' => true,
                'schema' => [
                    'gap'         => ['type' => 'number', 'label' => 'فاصله عناصر (px)', 'min' => 0, 'max' => 64, 'default' => 20],
                    'max_width'   => ['type' => 'number', 'label' => 'حداکثر عرض متن (px)', 'min' => 0, 'max' => 900, 'default' => 640],
                    'title_size'  => ['type' => 'number', 'label' => 'اندازه عنوان (rem)', 'min' => 1.5, 'max' => 6, 'step' => 0.25, 'default' => 3.25],
                    'title_color' => ['type' => 'color', 'label' => 'رنگ عنوان', 'default' => 'var(--tb-on-primary, #ffffff)'],
                ],
                'defaultProps' => ['gap' => 20, 'max_width' => 640, 'title_size' => 3.25, 'title_color' => 'var(--tb-on-primary, #ffffff)'],
            ],
            // The only genuinely new leaf-adjacent type: a CTA row owning
            // button spacing/radius as Inspector props (no existing
            // container does). Children constrained to the canonical
            // `button` primitive — structural validation stays with the 2C
            // pipeline, never re-implemented here.
            'hero_actions' => [
                'name' => 'قهرمان — دکمه‌های اقدام',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'button',
                'is_container' => true,
                'allowedChildren' => ['button'],
                'schema' => [
                    'gap'    => ['type' => 'number', 'label' => 'فاصله دکمه‌ها (px)', 'min' => 0, 'max' => 48, 'default' => 16],
                    'radius' => ['type' => 'number', 'label' => 'گردی گوشه‌ها (px)', 'min' => 0, 'max' => 32, 'default' => 12],
                    'align'  => ['type' => 'select', 'label' => 'چینش ردیف',
                        'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'انتها'], 'default' => 'start'],
                ],
                'defaultProps' => ['gap' => 16, 'radius' => 12, 'align' => 'start'],
            ],

            // ─── CONTENT ─────────────────────────────────────────────────────
            'heading' => [
                'name' => 'عنوان',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'heading',
                'schema' => [
                    'text' => ['type' => 'string', 'label' => 'متن عنوان', 'default' => ''],
                    // renderHeading() clamps to h1–h6; stored values are ints
                    // (or numeric strings from older select controls).
                    'level' => ['type' => 'select', 'label' => 'سطح عنوان', 'options' => [
                        1 => 'H1 — عنوان اصلی صفحه', 2 => 'H2 — عنوان بخش', 3 => 'H3', 4 => 'H4', 5 => 'H5', 6 => 'H6',
                    ], 'default' => 2],
                ],
                'defaultProps' => ['level' => 2, 'text' => ''],
            ],
            'text' => [
                'name' => 'متن',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'text',
                'schema' => [
                    'content' => ['type' => 'textarea', 'label' => 'محتوا', 'default' => ''],
                ],
                'defaultProps' => ['content' => ''],
            ],
            'image' => [
                'name' => 'تصویر',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'image',
                'schema' => [
                    'url'     => ['type' => 'image',  'label' => 'تصویر',   'default' => ''],
                    'alt'     => ['type' => 'string', 'label' => 'متن جایگزین', 'default' => ''],
                    'caption' => ['type' => 'string', 'label' => 'کپشن',    'default' => ''],
                    'align'   => ['type' => 'select', 'label' => 'تراز',    'options' => ['right' => 'راست', 'center' => 'وسط', 'left' => 'چپ'], 'default' => 'center'],
                ],
                'defaultProps' => ['url' => '', 'alt' => '', 'caption' => '', 'align' => 'center'],
            ],
            'gallery' => [
                'name' => 'گالری',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'gallery',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان گالری', 'default' => ''],
                    // Phase 0.2: promoted from customer-site (renderGallery reads {url, alt}).
                    'items' => ['type' => 'repeater', 'label' => 'تصاویر گالری', 'item_label' => 'تصویر', 'storage' => 'array', 'default' => [], 'fields' => [
                        'url' => ['type' => 'image', 'label' => 'تصویر', 'default' => ''],
                        'alt' => ['type' => 'string', 'label' => 'متن جایگزین', 'default' => ''],
                    ]],
                ],
                'defaultProps' => ['title' => '', 'items' => []],
            ],
            'video' => [
                'name' => 'ویدیو',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'video',
                // Phase 0.2c: poster/autoplay/loop/controls promoted from
                // customer-site — renderVideo() already honored all four.
                // `url` is a media field (the media library stores
                // {media_id}); media_kind tells editors not to preview it
                // as an <img>.
                'schema' => [
                    'url' => ['type' => 'image', 'media_kind' => 'video', 'label' => 'فایل ویدیو', 'default' => ''],
                    'poster' => ['type' => 'image', 'label' => 'تصویر پیش‌نمایش', 'default' => ''],
                    'caption' => ['type' => 'string', 'label' => 'کپشن', 'default' => ''],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار (بی‌صدا)', 'default' => false],
                    'loop' => ['type' => 'boolean', 'label' => 'تکرار', 'default' => false],
                    'controls' => ['type' => 'boolean', 'label' => 'نمایش کنترل‌ها', 'default' => true],
                ],
                'defaultProps' => ['url' => '', 'poster' => '', 'caption' => '', 'autoplay' => false, 'loop' => false, 'controls' => true],
            ],
            'button' => [
                'name' => 'دکمه',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'button',
                'schema' => [
                    'text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => ''],
                    // TASK 3/3 Phase 6.5C (BLD-28) — internal-link-capable
                    // (typed {internal_page, custom_url} target, resolved
                    // server-side via InternalLinkResolver). See
                    // BlockRenderer::escapeUrl() callers — unchanged;
                    // resolution happens before render, same pattern as
                    // {media_id}.
                    'url' => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    // Only the variants renderButton() allowlists; customer-
                    // site's former `outline` option rendered as primary.
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['primary' => 'اصلی', 'secondary' => 'ثانویه'], 'default' => 'primary'],
                ],
                'defaultProps' => ['text' => '', 'url' => '#', 'style' => 'primary'],
            ],
            'divider' => [
                'name' => 'جداکننده',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'divider',
                'schema' => [],
                'defaultProps' => [],
            ],
            // ─── Phase 3A basic commercial foundation set ────────────────
            // Additive, non-duplicating types (Step 4): `spacer` fills the
            // rhythm/whitespace gap (divider draws a rule; spacer is pure
            // logical spacing); `rich_text` is the sanitized rich variant
            // of `text` (shared allowlist sanitizer); `icon_box`/
            // `image_box` are the canonical card primitives every section
            // library composes from; `badge`/`list` round out the basic
            // vocabulary. All consume the 2B Inspector contract and the
            // 2C structural pipeline unchanged.
            'spacer' => [
                'name' => 'فاصله‌گذار (Spacer)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'spacer',
                'schema' => [
                    'height' => ['type' => 'number', 'label' => 'ارتفاع (px)', 'default' => 48],
                ],
                'defaultProps' => ['height' => 48],
            ],
            'rich_text' => [
                'name' => 'متن غنی (Rich Text)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'rich-text',
                'schema' => [
                    'content' => ['type' => 'rich_text', 'label' => 'محتوا', 'default' => ''],
                ],
                'defaultProps' => ['content' => ''],
            ],
            'icon_box' => [
                'name' => 'جعبه آیکون (Icon Box)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'icon-box',
                'schema' => [
                    'icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => 'star'],
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'text' => ['type' => 'text', 'label' => 'متن', 'default' => ''],
                    'link' => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    'align' => ['type' => 'select', 'label' => 'تراز', 'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'انتها'], 'default' => 'start'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['plain' => 'ساده', 'card' => 'کارت', 'outline' => 'خط‌دار', 'solid' => 'پررنگ'], 'default' => 'card'],
                ],
                'defaultProps' => ['icon' => 'star', 'title' => '', 'text' => '', 'link' => '', 'align' => 'start', 'style' => 'card'],
            ],
            'image_box' => [
                'name' => 'جعبه تصویر (Image Box)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'image-box',
                'schema' => [
                    'url' => ['type' => 'image', 'label' => 'تصویر', 'default' => ''],
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'عنوان تصویر'],
                    'text' => ['type' => 'text', 'label' => 'متن', 'default' => ''],
                    'link' => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    'ratio' => ['type' => 'select', 'label' => 'نسبت تصویر', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1'], 'default' => '4/3'],
                    'align' => ['type' => 'select', 'label' => 'تراز متن', 'options' => ['start' => 'ابتدا', 'center' => 'وسط'], 'default' => 'start'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['plain' => 'ساده', 'card' => 'کارت'], 'default' => 'card'],
                ],
                'defaultProps' => ['url' => '', 'title' => 'عنوان تصویر', 'text' => '', 'link' => '', 'ratio' => '4/3', 'align' => 'start', 'style' => 'card'],
            ],
            'badge' => [
                'name' => 'نشان (Badge)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'badge',
                'schema' => [
                    'text' => ['type' => 'string', 'label' => 'متن نشان', 'default' => 'جدید'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['primary' => 'اصلی', 'secondary' => 'ثانویه', 'accent' => 'تأکیدی', 'outline' => 'خط‌دار'], 'default' => 'primary'],
                ],
                'defaultProps' => ['text' => 'جدید', 'style' => 'primary'],
            ],
            'list' => [
                'name' => 'لیست (List)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'list',
                'schema' => [
                    'items' => ['type' => 'repeater', 'label' => 'آیتم‌ها', 'item_label' => 'آیتم', 'storage' => 'array', 'default' => [], 'fields' => [
                        'text' => ['type' => 'string', 'label' => 'متن', 'default' => ''],
                    ]],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['disc' => 'نقطه‌ای', 'ordered' => 'شماره‌ای', 'check' => 'تیک‌دار', 'plain' => 'ساده'], 'default' => 'disc'],
                ],
                'defaultProps' => ['items' => [['text' => '']]],
            ],
            'icon' => [
                'name' => 'آیکون',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'icon',
                'schema' => [
                    'name' => ['type' => 'icon', 'label' => 'آیکون', 'default' => ''],
                    'size' => ['type' => 'string', 'label' => 'اندازهٔ آیکون', 'default' => 'md'],
                ],
                'defaultProps' => ['name' => '', 'size' => 'md'],
            ],
            'counter' => [
                'name' => 'شمارنده',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'counter',
                'schema' => [
                    'value' => ['type' => 'number', 'label' => 'عدد', 'default' => 0],
                    'suffix' => ['type' => 'string', 'label' => 'پسوند', 'default' => ''],
                    'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => ''],
                ],
                'defaultProps' => ['value' => 0, 'suffix' => '', 'label' => ''],
            ],
            'progress_bar' => [
                'name' => 'نوار پیشرفت',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'progress',
                'schema' => [
                    'value' => ['type' => 'number', 'label' => 'مقدار پیشرفت (درصد)', 'default' => 70],
                    'label' => ['type' => 'string', 'label' => 'عنوان نوار', 'default' => ''],
                ],
                'defaultProps' => ['value' => 70, 'label' => ''],
            ],

            // ─── DYNAMIC ─────────────────────────────────────────────────────
            'blog_posts' => [
                'name' => 'پست‌های وبلاگ',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'blog',
                // Phase 0.2c: layout/source/category_id/manual_ids/autoplay/
                // interval promoted from customer-site, whose tenant-data
                // renderer reads them. The shared renderer has no post data
                // and keeps its honest placeholder — same convention as
                // product_slider's source fields below.
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'آخرین مطالب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد نوشته', 'default' => 6],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست', 'slider' => 'اسلایدر'], 'default' => 'grid'],
                    'source' => ['type' => 'select', 'label' => 'منبع', 'options' => ['latest' => 'جدیدترین', 'category' => 'یک دسته‌بندی خاص', 'manual' => 'انتخاب دستی'], 'default' => 'latest'],
                    'category_id' => ['type' => 'number', 'label' => 'شناسه دسته‌بندی (در حالت دسته‌بندی)', 'default' => null],
                    'manual_ids' => ['type' => 'array', 'label' => 'شناسه مقالات (در حالت انتخاب دستی)', 'default' => []],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار (در حالت اسلایدر)', 'default' => true],
                    'interval' => ['type' => 'number', 'label' => 'فاصله زمانی (میلی‌ثانیه)', 'default' => 5000],
                ],
                'defaultProps' => ['title' => 'آخرین مطالب', 'limit' => 6, 'layout' => 'grid', 'source' => 'latest', 'autoplay' => true, 'interval' => 5000],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'products' => [
                'name' => 'محصولات',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'products',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'محصولات'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد محصولات', 'default' => 8],
                    'source' => ['type' => 'string', 'label' => 'منبع محصولات', 'default' => 'recent'],
                ],
                'defaultProps' => ['title' => 'محصولات', 'limit' => 8, 'source' => 'recent'],
            ],
            'testimonials' => [
                'name' => 'نظرات مشتریان',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'quote',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'نظرات'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد نمایش', 'default' => 5],
                ],
                'defaultProps' => ['title' => 'نظرات', 'limit' => 5],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'faq' => [
                'name' => 'سوالات متداول',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'faq',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'سوالات متداول'],
                    // Same item shape renderFaq() and customer-site's FAQPage
                    // JSON-LD (CanvasStructuredData) already read.
                    'items' => [
                        'type' => 'repeater',
                        'label' => 'پرسش‌ها',
                        'item_label' => 'پرسش',
                        'storage' => 'array',
                        'default' => [],
                        'fields' => [
                            'question' => ['type' => 'string',   'label' => 'پرسش', 'default' => 'پرسش جدید'],
                            'answer'   => ['type' => 'textarea', 'label' => 'پاسخ', 'default' => ''],
                        ],
                    ],
                    // Phase 3 (ADR-068) — see BlockRenderer::renderFaq().
                    'exclusive' => ['type' => 'boolean', 'label' => 'فقط یک پاسخ باز باشد', 'default' => false],
                    'open_first' => ['type' => 'boolean', 'label' => 'باز بودن پرسش اول', 'default' => false],
                ],
                'defaultProps' => ['title' => 'سوالات متداول', 'items' => [], 'exclusive' => false, 'open_first' => false],
            ],
            'team' => [
                'name' => 'اعضای تیم',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'users',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'تیم ما'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد اعضا', 'default' => 6],
                ],
                'defaultProps' => ['title' => 'تیم ما', 'limit' => 6],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],

            // ─── ECOMMERCE ───────────────────────────────────────────────────
            'product_grid' => [
                'name' => 'گرید محصولات',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'grid',
                'schema' => [
                    'columns' => ['type' => 'number', 'label' => 'تعداد ستون', 'default' => 4],
                    'limit' => ['type' => 'number', 'label' => 'تعداد نمایش', 'default' => 8],
                ],
                'defaultProps' => ['columns' => 4, 'limit' => 8],
            ],
            // Universal Multipurpose Slider Overhaul: 'source'/'category_id'/
            // 'manual_ids' are consumed only by a host's real-data override
            // (customer-site's ThemeCanvasRenderer::renderProductSlider());
            // the canonical shared renderer has no database access and
            // always shows its demo fixture regardless of these — see that
            // method's own doc comment. Additive-only: 'limit'/'columns'
            // default unchanged from before.
            'product_slider' => [
                'name' => 'اسلایدر محصولات',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'slider',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'محصولات منتخب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد نمایش', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون (دسکتاپ)', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'source' => ['type' => 'select', 'label' => 'منبع محصولات', 'options' => ['latest' => 'جدیدترین', 'new' => 'دارای برچسب جدید', 'category' => 'یک دسته‌بندی خاص', 'manual' => 'انتخاب دستی'], 'default' => 'latest'],
                    'category_id' => ['type' => 'number', 'label' => 'شناسه دسته‌بندی (در حالت دسته‌بندی)', 'default' => null],
                    'manual_ids' => ['type' => 'array', 'label' => 'شناسه محصولات (در حالت انتخاب دستی)', 'default' => []],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                    'interval' => ['type' => 'number', 'label' => 'فاصله زمانی (میلی‌ثانیه)', 'default' => 5000],
                ],
                'defaultProps' => ['title' => 'محصولات منتخب', 'limit' => 6, 'columns' => '3', 'source' => 'latest', 'autoplay' => true, 'interval' => 5000],
            ],
            // Universal Multipurpose Slider Overhaul: new type — previously
            // did not exist at all (confirmed absent from both registry
            // sources during audit). Demo-fixture-rendered by the shared
            // package (BlockRenderer::renderCategorySlider()); a host may
            // override with a real category query (customer-site's
            // ThemeCanvasRenderer binds to the real ProductCategory model).
            'category_slider' => [
                'name' => 'اسلایدر دسته‌بندی',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'grid',
                'schema' => [
                    'title'    => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'دسته‌بندی‌ها'],
                    'layout'   => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['carousel' => 'اسلایدر', 'grid' => 'گرید ثابت'], 'default' => 'carousel'],
                    'columns'  => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴', '6' => '۶'], 'default' => '4'],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                    'interval' => ['type' => 'number', 'label' => 'فاصله زمانی (میلی‌ثانیه)', 'default' => 5000],
                    // Consumed only by a host's real-data override, same
                    // convention as product_slider's category_id/manual_ids.
                    'source'   => ['type' => 'select', 'label' => 'منبع', 'options' => ['all' => 'همه دسته‌بندی‌ها', 'manual' => 'انتخاب دستی'], 'default' => 'all'],
                    'manual_ids' => ['type' => 'array', 'label' => 'شناسه دسته‌بندی‌ها (در حالت انتخاب دستی)', 'default' => []],
                ],
                'defaultProps' => ['title' => 'دسته‌بندی‌ها', 'layout' => 'carousel', 'columns' => '4', 'autoplay' => true, 'interval' => 5000, 'source' => 'all'],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'cart_icon' => [
                'name' => 'آیکون سبد',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'cart',
                'schema' => [],
                'defaultProps' => [],
            ],
            'add_to_cart' => [
                'name' => 'دکمه افزودن به سبد',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'cart',
                'schema' => [
                    'product_id' => ['type' => 'string', 'label' => 'شناسهٔ محصول', 'default' => ''],
                    'text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'افزودن به سبد'],
                ],
                'defaultProps' => ['product_id' => '', 'text' => 'افزودن به سبد'],
            ],
            'price_box' => [
                'name' => 'جعبه قیمت',
                'category' => self::CATEGORY_ECOMMERCE,
                'icon' => 'price',
                'schema' => [
                    'product_id' => ['type' => 'string', 'label' => 'شناسهٔ محصول', 'default' => ''],
                ],
                'defaultProps' => ['product_id' => ''],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],

            // ─── MARKETING ───────────────────────────────────────────────────
            'cta' => [
                'name' => 'دعوت به اقدام (CTA)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'cta',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => ''],
                    'button_url' => ['type' => 'link', 'label' => 'لینک دکمه', 'default' => ''],
                    // A theme token (primary/secondary/…) or a CSS colour —
                    // renderCta() handles both (Phase 0.2c).
                    'background' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => 'primary'],
                ],
                'defaultProps' => ['title' => '', 'subtitle' => '', 'button_text' => '', 'button_url' => '', 'background' => 'primary'],
            ],
            'countdown' => [
                'name' => 'شمارش معکوس',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'clock',
                // Phase 3 (ADR-068): Jalali end dates in Tehran time — see
                // BlockRenderer::renderCountdown().
                'schema' => [
                    'end_date' => ['type' => 'string', 'label' => 'تاریخ پایان (شمسی، مثل ۱۴۰۵/۰۸/۱۵)', 'default' => ''],
                    'end_time' => ['type' => 'string', 'label' => 'ساعت پایان (مثل ۲۳:۵۹، به وقت تهران)', 'default' => ''],
                    'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => 'پایان پیشنهاد'],
                    'expired_text' => ['type' => 'string', 'label' => 'متن پس از پایان', 'default' => 'این پیشنهاد به پایان رسیده است.'],
                    'style' => ['type' => 'select', 'label' => 'نمایش', 'options' => ['card' => 'کارت', 'banner' => 'نوار تبلیغاتی'], 'default' => 'card'],
                    'show_end_date' => ['type' => 'boolean', 'label' => 'نمایش تاریخ پایان', 'default' => true],
                ],
                'defaultProps' => ['end_date' => '', 'end_time' => '', 'label' => 'پایان پیشنهاد', 'expired_text' => 'این پیشنهاد به پایان رسیده است.', 'style' => 'card', 'show_end_date' => true],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'email_capture' => [
                'name' => 'فرم ثبت ایمیل',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'mail',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'در خبرنامه عضو شوید'],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'ثبت'],
                ],
                'defaultProps' => ['title' => 'در خبرنامه عضو شوید', 'button_text' => 'ثبت'],
            ],
            'promo_banner' => [
                'name' => 'بنر تبلیغاتی',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'megaphone',
                'schema' => [
                    'text' => ['type' => 'string', 'label' => 'متن', 'default' => ''],
                    'link' => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    // The variants the shared CSS contract styles
                    // (.tb-promo-info/-sale/-urgent).
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['info' => 'اطلاع‌رسانی', 'sale' => 'فروش ویژه', 'urgent' => 'فوری'], 'default' => 'info'],
                ],
                'defaultProps' => ['text' => '', 'link' => '', 'style' => 'info'],
                // Phase 2f (ADR-027): a deliberately optional accent — see
                // EMPTY_POLICY_OPTIONAL's docblock above.
                'emptyOutputPolicy' => self::EMPTY_POLICY_OPTIONAL,
            ],

            // ─── UTILITY ────────────────────────────────────────────────────
            'breadcrumb' => [
                'name' => 'مسیر (Breadcrumb)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'breadcrumb',
                'schema' => [],
                'defaultProps' => [],
            ],
            'search_bar' => [
                'name' => 'جستجو',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'search',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'label' => 'متن راهنمای جستجو', 'default' => 'جستجو...'],
                    // Phase 0.7 (ADR-066): which results the search opens.
                    'scope' => ['type' => 'select', 'label' => 'محدودهٔ جستجو', 'options' => ['all' => 'همه (محصولات و مطالب)', 'products' => 'فقط محصولات', 'posts' => 'فقط مطالب'], 'default' => 'all'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو...', 'scope' => 'all'],
            ],
            'nav_menu' => [
                'name' => 'منوی ناوبری',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'menu',
                'schema' => [
                    'location' => ['type' => 'select', 'label' => 'موقعیت منو', 'options' => ['header' => 'سربرگ (هدر)', 'footer' => 'پابرگ (فوتر)'], 'default' => 'header'],
                ],
                'defaultProps' => ['location' => 'header'],
            ],
            // Phase 0.2c: `links` is a real list editor (was a schema-less
            // array) and `style` is promoted from customer-site. With no
            // links, a host may fall back to its site-wide social links.
            'social_icons' => [
                'name' => 'شبکه‌های اجتماعی',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'share',
                'schema' => [
                    'links' => ['type' => 'repeater', 'label' => 'لینک‌ها', 'item_label' => 'لینک', 'storage' => 'array', 'default' => [], 'fields' => [
                        'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                        'url'   => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    ]],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['icons' => 'آیکون', 'buttons' => 'دکمه', 'text' => 'متن'], 'default' => 'icons'],
                ],
                'defaultProps' => ['links' => [], 'style' => 'icons'],
            ],
            'language_switcher' => [
                'name' => 'تعویض زبان',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'globe',
                'schema' => [],
                'defaultProps' => [],
            ],
            'login_register' => [
                'name' => 'ورود / ثبت‌نام',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'user',
                'schema' => [],
                'defaultProps' => [],
            ],
            // Phase 2e (ADR-026): the site header, authored as one
            // self-contained block by 24 of the 28 canonical themes —
            // distinct from composing nav_menu/search_bar/cart_icon
            // separately, which remains valid for themes that prefer that.
            'nav_header' => [
                'name' => 'هدر سایت (Nav Header)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'menu',
                // ADR-076: the slots this header marks with data-widget-slot.
                'slots' => ['logo', 'menu', 'actions', 'quick-access'],
                'schema' => [
                    // ADR-076: an uploaded logo image (falls back to logo_text,
                    // then to the site's global logo), its height per breakpoint
                    // and its alt text.
                    'logo_image'         => ['type' => 'image', 'label' => 'تصویر لوگو', 'default' => '', 'group' => 'لوگو و برند'],
                    'logo_alt'           => ['type' => 'string', 'label' => 'متن جایگزین لوگو (برای صفحه‌خوان و موتور جستجو)', 'default' => '', 'group' => 'لوگو و برند'],
                    'logo_height'        => ['type' => 'number', 'label' => 'ارتفاع لوگو در دسکتاپ (پیکسل)', 'default' => 40, 'min' => 20, 'max' => 120, 'step' => 1, 'group' => 'لوگو و برند'],
                    'logo_height_mobile' => ['type' => 'number', 'label' => 'ارتفاع لوگو در موبایل (پیکسل)', 'default' => 32, 'min' => 16, 'max' => 96, 'step' => 1, 'group' => 'لوگو و برند'],
                    'logo_text'          => ['type' => 'string', 'label' => 'متن لوگو', 'default' => '', 'group' => 'لوگو و برند'],
                    'logo_url'           => ['type' => 'link', 'label' => 'لینک لوگو', 'default' => '/', 'group' => 'لوگو و برند'],
                    // ADR-076: where the menu sits between the logo and the actions.
                    // 'end' is the original layout (links next to the actions).
                    'menu_align'         => ['type' => 'select', 'ui' => 'segmented', 'label' => 'چیدمان منو', 'options' => ['start' => 'راست (کنار لوگو)', 'center' => 'وسط', 'end' => 'چپ (کنار دکمه‌ها)'], 'default' => 'end', 'group' => 'منو'],
                    // TASK 3/3 Phase 6.5B — nav_source lets this header use
                    // the customer's own real, canonical primary Menu
                    // (/admin/menus, Menu-location "primary") instead of a
                    // second, disconnected label+url list. Default stays
                    // 'custom' so every existing theme/instance keeps its
                    // current nav_links behavior unchanged — this is an
                    // opt-in convergence, not a breaking default flip. See
                    // renderNavHeader() below for the render-time branch.
                    'nav_source'         => ['type' => 'select', 'label' => 'منبع لینک‌های ناوبری', 'options' => ['custom' => 'لینک‌های سفارشی این بلوک', 'menu' => 'منوی اصلی سایت (/admin/menus)'], 'default' => 'custom', 'group' => 'منو'],
                    'nav_links'          => ['type' => 'repeater', 'label' => 'لینک‌های منو', 'item_label' => 'لینک', 'storage' => 'array', 'fields' => ['label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'], 'mega_columns' => ['type' => 'repeater', 'label' => 'ستون‌های Mega Menu (اختیاری — برای منوی چندستونی)', 'item_label' => 'ستون', 'fields' => ['title' => ['type' => 'string', 'label' => 'عنوان ستون', 'default' => ''], 'items' => ['type' => 'repeater', 'label' => 'آیتم‌های این ستون', 'item_label' => 'آیتم', 'fields' => ['label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#'], 'icon' => ['type' => 'icon', 'label' => 'آیکون (اختیاری)', 'default' => ''], 'image' => ['type' => 'image', 'label' => 'تصویر آیتم (اختیاری، جای آیکون را می‌گیرد)', 'default' => '']]]]], 'mega_banner_image' => ['type' => 'image', 'label' => 'تصویر بنر Mega Menu (اختیاری)', 'default' => ''], 'mega_banner_url' => ['type' => 'link', 'label' => 'لینک بنر', 'default' => '#'], 'mega_banner_text' => ['type' => 'string', 'label' => 'متن روی بنر', 'default' => ''], 'mega_banner_cta' => ['type' => 'string', 'label' => 'متن دکمهٔ بنر (اختیاری)', 'default' => ''], 'mega_show_desktop' => ['type' => 'boolean', 'label' => 'نمایش Mega Menu در دسکتاپ', 'default' => true], 'mega_mobile_behavior' => ['type' => 'select', 'label' => 'رفتار در موبایل', 'options' => ['accordion' => 'آکاردئونی (پیش‌فرض)', 'flat' => 'فهرست تخت بدون ستون‌بندی', 'hidden' => 'مخفی در موبایل'], 'default' => 'accordion']], 'default' => [], 'group' => 'منو'],
                    // ADR-076: quick-access social and messenger icons in the actions slot.
                    'social_links'       => self::socialLinksField('آیکون‌های دسترسی سریع (شبکه‌ها و پیام‌رسان‌ها)', 'دسترسی سریع و اکشن‌ها'),
                    'social_style'       => self::socialStyleField('دسترسی سریع و اکشن‌ها'),
                    'social_show_mobile' => ['type' => 'boolean', 'label' => 'نمایش آیکون‌های دسترسی سریع در موبایل', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'phone'              => ['type' => 'string', 'label' => 'شماره تماس (اختیاری)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'cta_text'           => ['type' => 'string', 'label' => 'متن دکمه CTA (اختیاری)', 'default' => '', 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'cta_url'            => ['type' => 'link', 'label' => 'لینک دکمه CTA', 'default' => '#', 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'show_search'        => ['type' => 'boolean', 'label' => 'نمایش جستجو', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'search_placeholder' => ['type' => 'string', 'label' => 'متن جای‌گزین جستجو', 'default' => 'جستجو...', 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'search_scope'       => ['type' => 'select', 'label' => 'محدودهٔ جستجو', 'options' => ['all' => 'همه (محصولات و مطالب)', 'products' => 'فقط محصولات', 'posts' => 'فقط مطالب'], 'default' => 'all', 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'show_cart'          => ['type' => 'boolean', 'label' => 'نمایش سبد خرید', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'show_account'       => ['type' => 'boolean', 'label' => 'نمایش حساب کاربری', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
                    // Commercial Productization fase-4: a real, usable dark
                    // mode toggle — opt-in per theme (default false) since
                    // it is only meaningful for a theme that actually
                    // configured global_styles.dark_mode; a theme with no
                    // dark palette would show a toggle that visually does
                    // nothing. See BlockRenderer::renderNavHeader() for the
                    // real button + the head-bootstrap script (both hosts'
                    // layout templates) that prevents a flash of the wrong
                    // theme on load.
                    'show_dark_toggle'   => ['type' => 'boolean', 'label' => 'نمایش تغییر حالت روشن/تاریک', 'default' => false, 'group' => 'دسترسی سریع و اکشن‌ها'],
                    'sticky'             => ['type' => 'boolean', 'label' => 'چسبان به بالا (با کوچک‌شدن نرم هنگام اسکرول)', 'default' => false, 'group' => 'رفتار و رنگ'],
                    // Phase 1 (ADR-067): smart sticky and transparent-over-hero; the
                    // behaviour runs on the page's banner element (blocks/site-header.js).
                    'sticky_hide_on_scroll' => ['type' => 'boolean', 'label' => 'هوشمند: پنهان هنگام اسکرول به پایین، نمایان هنگام اسکرول به بالا', 'default' => false, 'group' => 'رفتار و رنگ'],
                    'transparent_over_hero' => ['type' => 'boolean', 'label' => 'شفاف روی بنر بالای صفحه', 'default' => false, 'group' => 'رفتار و رنگ'],
                    // '' = automatic: the script reads the hero under the header and picks light or dark text.
                    'transparent_text_color' => ['type' => 'color', 'label' => 'رنگ متن در حالت شفاف (خالی = تشخیص خودکار کنتراست)', 'default' => '', 'group' => 'رفتار و رنگ'],
                    'bg_color'           => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ffffff', 'group' => 'رفتار و رنگ'],
                    'text_color'         => ['type' => 'color', 'label' => 'رنگ متن', 'default' => '#111827', 'group' => 'رفتار و رنگ'],
                ],
                'defaultProps' => ['logo_text' => '', 'logo_url' => '/', 'nav_links' => [], 'sticky' => false, 'bg_color' => '#ffffff', 'text_color' => '#111827'],
            ],
            'header_modern_v1' => [
                'name' => 'هدر مدرن RTL',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'menu',
                'schema' => [
                    'bgColor'    => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ffffff'],
                    'textColor'  => ['type' => 'color', 'label' => 'رنگ متن و منو', 'default' => '#172033'],
                    'ctaColor'   => ['type' => 'color', 'label' => 'رنگ دکمه اصلی', 'default' => '#4f46e5'],
                    'logoUrl'    => ['type' => 'string', 'label' => 'نشانی تصویر لوگو', 'default' => ''],
                    'menuItems'  => ['type' => 'array', 'label' => 'آیتم‌های منو', 'default' => []],
                    'brandName'  => ['type' => 'string', 'label' => 'نام برند', 'default' => 'برند شما'],
                    'homeUrl'    => ['type' => 'link', 'label' => 'لینک صفحه اصلی', 'default' => '/'],
                    'ctaText'    => ['type' => 'string', 'label' => 'متن دکمه اصلی', 'default' => 'شروع کنید'],
                    'ctaUrl'     => ['type' => 'link', 'label' => 'لینک دکمه اصلی', 'default' => '#'],
                ],
                'defaultProps' => [
                    'bgColor' => '#ffffff',
                    'textColor' => '#172033',
                    'ctaColor' => '#4f46e5',
                    'logoUrl' => '',
                    'menuItems' => [
                        ['label' => 'خانه', 'url' => '/'],
                        ['label' => 'امکانات', 'url' => '#features'],
                        ['label' => 'تعرفه‌ها', 'url' => '#pricing'],
                        ['label' => 'تماس با ما', 'url' => '#contact'],
                    ],
                    'brandName' => 'برند شما',
                    'homeUrl' => '/',
                    'ctaText' => 'شروع کنید',
                    'ctaUrl' => '#',
                ],
            ],
            // Phase 2e (ADR-026): the site footer, the self-contained
            // counterpart to nav_header. Superset schema — a theme with no
            // social_links/tagline/legal-links data simply omits those
            // sections rather than fabricating them.
            'nav_footer' => [
                'name' => 'فوتر سایت (Nav Footer)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'layout-footer',
                // ADR-076: the slots this footer marks with data-widget-slot.
                'slots' => ['brand', 'columns', 'bottom', 'social'],
                'schema' => [
                    // TASK 3/3 Phase 6.5C (BLD-26) — footer counterpart of
                    // nav_header's nav_source. See renderNavFooter() for
                    // the render-time branch. Default 'custom' — zero
                    // behavior change for any existing theme/instance.
                    'nav_source'   => ['type' => 'select', 'label' => 'منبع لینک‌های فوتر', 'options' => ['custom' => 'ستون‌های سفارشی این بلوک', 'menu' => 'منوی فوتر سایت (/admin/menus)'], 'default' => 'custom'],
                    'columns'      => ['type' => 'repeater', 'label' => 'ستون‌های لینک', 'item_label' => 'ستون', 'storage' => 'array', 'fields' => ['title' => ['type' => 'string', 'label' => 'عنوان ستون', 'default' => ''], 'links' => ['type' => 'repeater', 'label' => 'لینک‌های ستون', 'item_label' => 'لینک', 'storage' => 'array', 'default' => [], 'fields' => ['label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#']]]], 'default' => []],
                    'links'        => ['type' => 'repeater', 'label' => 'لینک‌های حقوقی (اختیاری)', 'item_label' => 'لینک', 'storage' => 'array', 'fields' => ['label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'آدرس لینک', 'default' => '#']], 'default' => []],
                    // ADR-076: one network list (Support\SocialNetworks), shared with nav_header.
                    'social_links' => self::socialLinksField('شبکه‌های اجتماعی و پیام‌رسان‌ها (اختیاری)'),
                    'social_style' => self::socialStyleField(),
                    'logo_text'    => ['type' => 'string', 'label' => 'متن لوگو', 'default' => ''],
                    'tagline'      => ['type' => 'string', 'label' => 'شعار برند (اختیاری)', 'default' => ''],
                    'copyright'    => ['type' => 'string', 'label' => 'متن کپی‌رایت', 'default' => ''],
                    'bg_color'     => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#111827'],
                    'text_color'   => ['type' => 'color', 'label' => 'رنگ متن', 'default' => '#e5e7eb'],
                    // Phase 1 (ADR-067): high-conversion footer — newsletter on the
                    // Phase 0.7 endpoint, trust seals rebuilt from their ids only,
                    // app download badges.
                    'newsletter_enabled'     => ['type' => 'boolean', 'label' => 'نمایش عضویت در خبرنامه', 'default' => false],
                    'newsletter_title'       => ['type' => 'string', 'label' => 'عنوان خبرنامه', 'default' => 'عضویت در خبرنامه'],
                    'newsletter_text'        => ['type' => 'textarea', 'label' => 'توضیح خبرنامه', 'default' => 'از تخفیف‌ها و محصولات تازه زودتر از همه باخبر شوید.'],
                    'newsletter_placeholder' => ['type' => 'string', 'label' => 'متن راهنمای فیلد', 'default' => 'ایمیل یا شماره موبایل'],
                    'newsletter_button'      => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'عضویت'],
                    'enamad_id'      => ['type' => 'string', 'label' => 'اینماد: شناسه (یا کل کد HTML اینماد)', 'default' => ''],
                    'enamad_code'    => ['type' => 'string', 'label' => 'اینماد: Code', 'default' => ''],
                    'samandehi_id'   => ['type' => 'string', 'label' => 'ساماندهی: شناسه (یا کل کد HTML ساماندهی)', 'default' => ''],
                    'samandehi_code' => ['type' => 'string', 'label' => 'ساماندهی: کد p', 'default' => ''],
                    'app_links'      => ['type' => 'repeater', 'label' => 'دریافت اپلیکیشن (اختیاری)', 'item_label' => 'فروشگاه', 'storage' => 'array', 'fields' => ['store' => ['type' => 'select', 'label' => 'فروشگاه', 'options' => ['google_play' => 'گوگل‌پلی', 'app_store' => 'اپ‌استور', 'bazaar' => 'کافه‌بازار', 'myket' => 'مایکت', 'direct' => 'دانلود مستقیم'], 'default' => 'bazaar'], 'url' => ['type' => 'link', 'label' => 'آدرس', 'default' => '#']], 'default' => []],
                ],
                'defaultProps' => ['columns' => [], 'copyright' => '', 'bg_color' => '#111827', 'text_color' => '#e5e7eb'],
            ],

            // ─── MODULE: موشن گرافیک ─────────────────────────────────────────
            // Authoring-time only: this entry exists so the Theme Builder
            // canvas preview shows a proper "موشن گرافیک" label instead of
            // the raw block type. Actual resolution/rendering only ever
            // happens on customer-site's ThemeCanvasRenderer, against a
            // specific site's own authorized Motion assignment — see
            // docs/theme-builder-motion/TEMPLATE_MOTION_CONTRACT.md.
            'module:motion' => [
                'name' => 'موشن گرافیک',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'sparkles',
                'schema' => [
                    // `motion`: customer-site's picker over the site's own
                    // permitted assets; the platform Canvas shows the id.
                    'motion_asset_id' => ['type' => 'motion', 'label' => 'موشن انتخاب‌شده', 'default' => ''],
                    'placement' => ['type' => 'select', 'label' => 'جایگاه', 'options' => ['hero' => 'هیرو', 'section' => 'بخش', 'cta' => 'فراخوان به اقدام', 'background' => 'پس‌زمینه'], 'default' => 'section'],
                    'enabled' => ['type' => 'boolean', 'label' => 'فعال', 'default' => true],
                    'fallback_image' => ['type' => 'image', 'label' => 'تصویر جایگزین (در صورت نبود موشن)', 'default' => ''],
                ],
                'defaultProps' => ['motion_asset_id' => '', 'placement' => 'section', 'enabled' => true, 'fallback_image' => ''],
            ],

            // ─── MODULE: وبلاگ ───────────────────────────────────────────────
            'module:blog_posts' => [
                'name' => 'پست‌های وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'blog',
                'requires' => 'blog',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'آخرین مطالب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد پست', 'default' => 6],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست', 'slider' => 'اسلایدر'], 'default' => 'grid'],
                    'show_excerpt' => ['type' => 'boolean', 'label' => 'نمایش خلاصه', 'default' => true],
                    'show_date' => ['type' => 'boolean', 'label' => 'نمایش تاریخ', 'default' => true],
                    'category_slug' => ['type' => 'string', 'label' => 'فیلتر دسته‌بندی (اختیاری)', 'default' => ''],
                ],
                'defaultProps' => ['title' => 'آخرین مطالب', 'limit' => 6, 'layout' => 'grid', 'show_excerpt' => true, 'show_date' => true, 'category_slug' => ''],
            ],
            'module:blog_featured' => [
                'name' => 'پست ویژه وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'star',
                'requires' => 'blog',
                'schema' => [
                    'post_slug' => ['type' => 'string', 'label' => 'اسلاگ پست', 'default' => ''],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['full' => 'کامل', 'compact' => 'فشرده'], 'default' => 'full'],
                ],
                'defaultProps' => ['post_slug' => '', 'style' => 'full'],
            ],
            'module:blog_categories' => [
                'name' => 'دسته‌بندی‌های وبلاگ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'tags',
                'requires' => 'blog',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دسته‌بندی‌ها'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'list' => 'لیست', 'badges' => 'نشان'], 'default' => 'cards'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 8],
                ],
                'defaultProps' => ['title' => 'دسته‌بندی‌ها', 'style' => 'cards', 'limit' => 8],
            ],

            // ─── MODULE: فروشگاه ─────────────────────────────────────────────
            'module:shop_product_grid' => [
                'name' => 'گرید محصولات فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'grid',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'محصولات'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد محصول', 'default' => 8],
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲ ستون', '3' => '۳ ستون', '4' => '۴ ستون'], 'default' => '4'],
                    'source' => ['type' => 'select', 'label' => 'منبع', 'options' => ['recent' => 'جدیدترین', 'featured' => 'ویژه', 'bestselling' => 'پرفروش'], 'default' => 'recent'],
                    'category_slug' => ['type' => 'string', 'label' => 'دسته‌بندی (اختیاری)', 'default' => ''],
                ],
                'defaultProps' => ['title' => 'محصولات', 'limit' => 8, 'columns' => '4', 'source' => 'recent', 'category_slug' => ''],
            ],
            'module:shop_product_slider' => [
                'name' => 'اسلایدر محصولات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'slider',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'محصولات پیشنهادی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'source' => ['type' => 'select', 'label' => 'منبع', 'options' => ['recent' => 'جدیدترین', 'featured' => 'ویژه'], 'default' => 'featured'],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                ],
                'defaultProps' => ['title' => 'محصولات پیشنهادی', 'limit' => 6, 'source' => 'featured', 'autoplay' => true],
            ],
            'module:shop_categories' => [
                'name' => 'دسته‌بندی‌های فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'category',
                'requires' => 'shop',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دسته‌بندی‌ها'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['grid' => 'گرید', 'list' => 'لیست', 'horizontal' => 'افقی'], 'default' => 'grid'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'show_count' => ['type' => 'boolean', 'label' => 'نمایش تعداد محصول', 'default' => true],
                ],
                'defaultProps' => ['title' => 'دسته‌بندی‌ها', 'style' => 'grid', 'limit' => 6, 'show_count' => true],
            ],
            'module:shop_cart_button' => [
                'name' => 'دکمه سبد خرید',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'cart',
                'requires' => 'shop',
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['icon' => 'آیکون', 'button' => 'دکمه', 'full' => 'کامل با قیمت'], 'default' => 'button'],
                    'label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'سبد خرید'],
                ],
                'defaultProps' => ['style' => 'button', 'label' => 'سبد خرید'],
            ],
            // MOD-01 (Full Product Commercial Audit, TASK 2A): real,
            // already-working backend — ShopWishlistService (session+auth
            // dual-identity) for wishlist, session('shop_compare') for
            // compare — had no Builder block. `mode` covers both with one
            // block family (WooCommerce ships these as separate plugins;
            // here they're the same "toggle a product into a list, show a
            // counter+link" shape, so one configurable block is the
            // stronger, non-duplicated architecture).
            'module:shop_wishlist_icon' => [
                'name' => 'علاقه‌مندی‌ها / مقایسه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'heart',
                'requires' => 'shop',
                'schema' => [
                    'mode' => ['type' => 'select', 'label' => 'حالت', 'options' => ['wishlist' => 'علاقه‌مندی‌ها', 'compare' => 'مقایسه'], 'default' => 'wishlist'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['icon' => 'آیکون', 'full' => 'کامل با متن'], 'default' => 'icon'],
                    'label' => ['type' => 'string', 'label' => 'متن', 'default' => 'علاقه‌مندی‌ها'],
                ],
                'defaultProps' => ['mode' => 'wishlist', 'style' => 'icon', 'label' => 'علاقه‌مندی‌ها'],
            ],
            // MOD-03 (Full Product Commercial Audit, TASK 2A-CONTINUATION):
            // real, already-working backend — MembershipPointsService::
            // getBalance() (customer-site) — had no Builder block. No
            // 'requires' key: points/loyalty is a core account feature (no
            // separate license/module gate in MembershipPointsService
            // itself), so this defaults to '_core' like every other
            // always-available block. Guests have no balance by
            // construction (points are tied to a real user_id, never a
            // guest session) — the renderer hides the block entirely for
            // guests rather than showing a fabricated zero.
            'module:loyalty_points_badge' => [
                'name' => 'امتیاز باشگاه مشتریان',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'star',
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['icon' => 'آیکون', 'full' => 'کامل با متن'], 'default' => 'icon'],
                    'label' => ['type' => 'string', 'label' => 'متن', 'default' => 'امتیاز من'],
                ],
                'defaultProps' => ['style' => 'icon', 'label' => 'امتیاز من'],
            ],
            // MOD-02 (Full Product Commercial Audit, TASK 2A-CONTINUATION):
            // real, already-working backend — InstallmentService::
            // eligiblePlans()/InstallmentPlan::calculate() (customer-site)
            // — had no Builder block. `requires: shop` because eligibility
            // is always computed from a real Product's price; with no
            // active plan covering that price (or no product selected), the
            // renderer shows a real empty state rather than a fabricated
            // rate or amount.
            'module:installment_badge' => [
                'name' => 'خرید اقساطی (BNPL)',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'credit-card',
                'requires' => 'shop',
                'schema' => [
                    'product_id' => ['type' => 'string', 'label' => 'شناسه محصول', 'default' => ''],
                    'style'      => ['type' => 'select', 'label' => 'استایل', 'options' => ['badge' => 'نشان کوتاه', 'full' => 'فهرست طرح‌ها'], 'default' => 'badge'],
                ],
                'defaultProps' => ['product_id' => '', 'style' => 'badge'],
            ],
            'module:shop_countdown' => [
                'name' => 'شمارش معکوس تخفیف',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'clock',
                'requires' => 'shop',
                'schema' => [
                    'promo_text' => ['type' => 'string', 'label' => 'متن تبلیغ', 'default' => 'پیشنهاد ویژه — فرصت محدود!'],
                    'end_date' => ['type' => 'string', 'label' => 'تاریخ پایان (Y-m-d)', 'default' => ''],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ef4444'],
                ],
                'defaultProps' => ['promo_text' => 'پیشنهاد ویژه — فرصت محدود!', 'end_date' => '', 'bg_color' => '#ef4444'],
            ],
            'module:shop_search_box' => [
                'name' => 'جستجوی فروشگاه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'search',
                'requires' => 'shop',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'label' => 'متن placeholder', 'default' => 'جستجو در محصولات...'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['bar' => 'نوار', 'box' => 'باکس'], 'default' => 'bar'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو در محصولات...', 'style' => 'bar'],
            ],

            // ─── MODULE: دوره‌ها ─────────────────────────────────────────────
            'module:courses_grid' => [
                'name' => 'گرید دوره‌ها',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'courses',
                'requires' => 'courses',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'دوره‌های آموزشی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد دوره', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست'], 'default' => 'grid'],
                    'show_price' => ['type' => 'boolean', 'label' => 'نمایش قیمت', 'default' => true],
                    // Phase 0.2c: `show_instructor` removed — no renderer
                    // read it and Course has no instructor field.
                ],
                'defaultProps' => ['title' => 'دوره‌های آموزشی', 'limit' => 6, 'columns' => '3', 'layout' => 'grid', 'show_price' => true],
            ],
            'module:course_enroll_cta' => [
                'name' => 'دعوت به ثبت‌نام دوره',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'graduation',
                'requires' => 'courses',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'همین الان شروع کن!'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'به هزاران دانشجو بپیوند'],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'مشاهده دوره‌ها'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => 'var(--tb-primary, #4f46e5)'],
                ],
                'defaultProps' => ['title' => 'همین الان شروع کن!', 'subtitle' => 'به هزاران دانشجو بپیوند', 'button_text' => 'مشاهده دوره‌ها', 'bg_color' => 'var(--tb-primary, #4f46e5)'],
            ],
            'module:my_learning_link' => [
                'name' => 'لینک یادگیری من',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'bookmark',
                'requires' => 'courses',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن لینک', 'default' => 'یادگیری من'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['button' => 'دکمه', 'link' => 'لینک ساده', 'card' => 'کارت'], 'default' => 'button'],
                ],
                'defaultProps' => ['label' => 'یادگیری من', 'style' => 'button'],
            ],

            // ─── MODULE: فرم‌ها ──────────────────────────────────────────────
            'module:form_embed' => [
                'name' => 'درج فرم',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'form',
                'requires' => 'forms',
                'schema' => [
                    'form_id' => ['type' => 'string', 'label' => 'شناسه فرم (ID)', 'default' => ''],
                    'title' => ['type' => 'string', 'label' => 'عنوان نمایشی', 'default' => ''],
                    'show_title' => ['type' => 'boolean', 'label' => 'نمایش عنوان', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['card' => 'کارت', 'minimal' => 'مینیمال', 'full' => 'تمام عرض'], 'default' => 'card'],
                ],
                'defaultProps' => ['form_id' => '', 'title' => '', 'show_title' => true, 'style' => 'card'],
            ],
            'module:contact_quick' => [
                'name' => 'فرم تماس سریع',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'phone',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'تماس با ما'],
                    'show_phone' => ['type' => 'boolean', 'label' => 'نمایش شماره تلفن', 'default' => true],
                    'show_address' => ['type' => 'boolean', 'label' => 'نمایش آدرس', 'default' => true],
                    'show_map' => ['type' => 'boolean', 'label' => 'نمایش نقشه', 'default' => false],
                ],
                'defaultProps' => ['title' => 'تماس با ما', 'show_phone' => true, 'show_address' => true, 'show_map' => false],
            ],

            // ─── MODULE: اشتراک ──────────────────────────────────────────────
            'module:pricing_table' => [
                'name' => 'جدول اشتراک / تعرفه',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'price',
                'requires' => 'subscription',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'پلن‌های اشتراک'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'پلنی که مناسب شماست را انتخاب کنید'],
                    // Phase 0.2c: `highlight_plan` and `show_annual_toggle`
                    // removed — no renderer read them. A plan's own
                    // `highlighted` flag (subscription admin) is the one
                    // highlighting authority.
                ],
                'defaultProps' => ['title' => 'پلن‌های اشتراک', 'subtitle' => 'پلنی که مناسب شماست را انتخاب کنید'],
            ],
            'module:plan_cta' => [
                'name' => 'دعوت به اشتراک',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'crown',
                'requires' => 'subscription',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'به کاربران ویژه بپیوند'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'مشاهده پلن‌ها'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ', 'default' => 'var(--tb-secondary, #7c3aed)'],
                ],
                'defaultProps' => ['title' => 'به کاربران ویژه بپیوند', 'subtitle' => '', 'button_text' => 'مشاهده پلن‌ها', 'bg_color' => 'var(--tb-secondary, #7c3aed)'],
            ],
            'module:subscriber_stats' => [
                'name' => 'آمار مشترکین',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'users',
                'requires' => 'subscription',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => 'کاربر فعال'],
                    'show_count' => ['type' => 'boolean', 'label' => 'نمایش تعداد', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['badge' => 'نشان', 'card' => 'کارت'], 'default' => 'badge'],
                ],
                'defaultProps' => ['label' => 'کاربر فعال', 'show_count' => true, 'style' => 'badge'],
            ],

            // ─── MODULE: پشتیبانی و تعامل ───────────────────────────────────
            'module:support_button' => [
                'name' => 'دکمه پشتیبانی / تیکت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'support',
                'requires' => null,
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => 'پشتیبانی آنلاین'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['button' => 'دکمه ثابت', 'floating' => 'شناور'], 'default' => 'floating'],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ'], 'default' => 'bottom-right'],
                    'bg_color' => ['type' => 'color', 'label' => 'رنگ', 'default' => 'var(--tb-primary, #4f46e5)'],
                ],
                'defaultProps' => ['label' => 'پشتیبانی آنلاین', 'style' => 'floating', 'position' => 'bottom-right', 'bg_color' => 'var(--tb-primary, #4f46e5)'],
            ],
            'module:whatsapp_widget' => [
                'name' => 'ویجت واتساپ',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'whatsapp',
                'requires' => 'whatsapp',
                'schema' => [
                    'label' => ['type' => 'string', 'label' => 'متن', 'default' => 'پشتیبانی واتساپ'],
                    'phone' => ['type' => 'string', 'label' => 'شماره واتساپ', 'default' => ''],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['bottom-right' => 'پایین راست', 'bottom-left' => 'پایین چپ'], 'default' => 'bottom-right'],
                ],
                'defaultProps' => ['label' => 'پشتیبانی واتساپ', 'phone' => '', 'position' => 'bottom-right'],
            ],
            'module:reviews_grid' => [
                'name' => 'نمایش نظرات کاربران',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'star',
                'requires' => 'reviews',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'نظرات مشتریان'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    // `carousel` removed (Phase 0.2c): the reviews renderer
                    // only implements cards and minimal.
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'minimal' => 'مینیمال'], 'default' => 'cards'],
                    'show_avatar' => ['type' => 'boolean', 'label' => 'نمایش آواتار', 'default' => true],
                    // Phase 3b (ADR-069): one product's reviews (with its
                    // AggregateRating) or the latest of all; the summary is
                    // the Persian star breakdown.
                    'product_id' => ['type' => 'number', 'label' => 'شناسه محصول (۰ = همه)', 'default' => 0, 'min' => 0],
                    'show_summary' => ['type' => 'boolean', 'label' => 'نمایش خلاصهٔ امتیازها', 'default' => true],
                ],
                'defaultProps' => ['title' => 'نظرات مشتریان', 'limit' => 6, 'style' => 'cards', 'show_avatar' => true, 'product_id' => 0, 'show_summary' => true],
            ],
            'module:referral_widget' => [
                'name' => 'ویجت معرفی به دوستان',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'share',
                'requires' => 'referral',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'دوستانت را دعوت کن'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => 'با معرفی دوستان جایزه بگیر'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['banner' => 'بنر', 'card' => 'کارت', 'minimal' => 'مینیمال'], 'default' => 'banner'],
                    'show_link' => ['type' => 'boolean', 'label' => 'نمایش لینک معرفی', 'default' => true],
                ],
                'defaultProps' => ['title' => 'دوستانت را دعوت کن', 'subtitle' => 'با معرفی دوستان جایزه بگیر', 'style' => 'banner', 'show_link' => true],
            ],

            // ─── MODULE: محتوا و نمایش ──────────────────────────────────────
            'module:content_cards' => [
                'name' => 'کارت‌های محتوا (دستیار AI)',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'ai',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'مطالب'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 3],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'grid' => 'گرید', 'list' => 'لیست'], 'default' => 'cards'],
                    'show_excerpt' => ['type' => 'boolean', 'label' => 'نمایش خلاصه', 'default' => true],
                ],
                'defaultProps' => ['title' => 'مطالب', 'limit' => 3, 'style' => 'cards', 'show_excerpt' => true],
            ],
            'module:gallery_widget' => [
                'name' => 'گالری تصاویر',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'gallery',
                'requires' => 'gallery',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'گالری'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['masonry' => 'ماسونری', 'grid' => 'گرید', 'carousel' => 'چرخشی'], 'default' => 'grid'],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                ],
                'defaultProps' => ['title' => 'گالری', 'style' => 'grid', 'columns' => '3'],
            ],
            'module:portfolio_grid' => [
                'name' => 'گرید نمونه‌کار (پورتفولیو)',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'portfolio',
                'requires' => 'portfolio',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'نمونه کارها'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'show_filter' => ['type' => 'boolean', 'label' => 'نمایش فیلتر', 'default' => true],
                ],
                'defaultProps' => ['title' => 'نمونه کارها', 'limit' => 6, 'columns' => '3', 'show_filter' => true],
            ],
            'module:services_section' => [
                'name' => 'بخش خدمات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'services',
                'requires' => 'services',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'خدمات ما'],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['cards' => 'کارت', 'list' => 'لیست', 'icons' => 'آیکون‌دار'], 'default' => 'cards'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'columns' => ['type' => 'select', 'label' => 'ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                ],
                'defaultProps' => ['title' => 'خدمات ما', 'style' => 'cards', 'limit' => 6, 'columns' => '3'],
            ],
            'module:advertisement_zone' => [
                'name' => 'ناحیه تبلیغات',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'megaphone',
                'requires' => 'advertisements',
                'schema' => [
                    'zone_name' => ['type' => 'string', 'label' => 'نام ناحیه', 'default' => 'banner-main'],
                    'position' => ['type' => 'select', 'label' => 'موقعیت', 'options' => ['banner' => 'بنر', 'sidebar' => 'کنار', 'popup' => 'پاپ‌آپ'], 'default' => 'banner'],
                ],
                'defaultProps' => ['zone_name' => 'banner-main', 'position' => 'banner'],
            ],
            'module:faq_dynamic' => [
                'name' => 'سوالات متداول پویا',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'faq',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'سوالات متداول'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 10],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['accordion' => 'آکوردئون', 'list' => 'لیست'], 'default' => 'accordion'],
                    // Phase 0.2: promoted from customer-site (static Q/A authored alongside the module source).
                    'items' => ['type' => 'repeater', 'label' => 'سوالات', 'item_label' => 'سوال', 'storage' => 'array', 'default' => [], 'fields' => [
                        'question' => ['type' => 'string', 'label' => 'سوال', 'default' => ''],
                        'answer'   => ['type' => 'textarea', 'label' => 'پاسخ', 'default' => ''],
                    ]],
                ],
                'defaultProps' => ['title' => 'سوالات متداول', 'limit' => 10, 'style' => 'accordion'],
            ],
            'module:notifications_bar' => [
                'name' => 'نوار اعلان سایت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'bell',
                'requires' => null,
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['top-bar' => 'نوار بالا', 'floating' => 'شناور'], 'default' => 'top-bar'],
                    'show_dismiss' => ['type' => 'boolean', 'label' => 'دکمه بستن', 'default' => true],
                ],
                'defaultProps' => ['style' => 'top-bar', 'show_dismiss' => true],
            ],
            'module:account_quick_links' => [
                'name' => 'لینک‌های سریع حساب کاربری',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'user-circle',
                'requires' => null,
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['buttons' => 'دکمه', 'list' => 'لیست', 'dropdown' => 'منو کشویی'], 'default' => 'buttons'],
                    'show_avatar' => ['type' => 'boolean', 'label' => 'نمایش آواتار', 'default' => true],
                    'show_notifications' => ['type' => 'boolean', 'label' => 'نمایش اعلان‌ها', 'default' => true],
                ],
                'defaultProps' => ['style' => 'buttons', 'show_avatar' => true, 'show_notifications' => true],
            ],
            'module:social_links_bar' => [
                'name' => 'نوار شبکه‌های اجتماعی',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'share',
                'requires' => 'social_links',
                'schema' => [
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['horizontal' => 'افقی', 'vertical' => 'عمودی', 'icons-only' => 'فقط آیکون'], 'default' => 'horizontal'],
                    'color' => ['type' => 'select', 'label' => 'رنگ', 'options' => ['brand' => 'رنگ برند', 'white' => 'سفید', 'dark' => 'تیره'], 'default' => 'brand'],
                    'size' => ['type' => 'select', 'label' => 'اندازه', 'options' => ['sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ'], 'default' => 'md'],
                ],
                'defaultProps' => ['style' => 'horizontal', 'color' => 'brand', 'size' => 'md'],
            ],
            'module:site_search' => [
                'name' => 'جستجوی پیشرفته سایت',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'search',
                'requires' => 'advanced_search',
                'schema' => [
                    'placeholder' => ['type' => 'string', 'label' => 'متن راهنما', 'default' => 'جستجو در سایت...'],
                    'show_suggestions' => ['type' => 'boolean', 'label' => 'پیشنهاد جستجو', 'default' => true],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['bar' => 'نوار', 'card' => 'کارت', 'hero' => 'قهرمان'], 'default' => 'bar'],
                ],
                'defaultProps' => ['placeholder' => 'جستجو در سایت...', 'show_suggestions' => true, 'style' => 'bar'],
            ],
            'module:business_card_embed' => [
                'name' => 'کارت ویزیت دیجیتال',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'card',
                'requires' => 'business_card',
                'schema' => [
                    'card_slug' => ['type' => 'string', 'label' => 'اسلاگ کارت', 'default' => ''],
                    'style' => ['type' => 'select', 'label' => 'استایل', 'options' => ['full' => 'کامل', 'compact' => 'فشرده'], 'default' => 'full'],
                ],
                'defaultProps' => ['card_slug' => '', 'style' => 'full'],
            ],
            'module:edu_article_grid' => [
                'name' => 'مقالات آموزشی',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'book',
                'requires' => null,
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'مقالات آموزشی'],
                    'limit' => ['type' => 'number', 'label' => 'تعداد', 'default' => 6],
                    'layout' => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'list' => 'لیست'], 'default' => 'grid'],
                ],
                'defaultProps' => ['title' => 'مقالات آموزشی', 'limit' => 6, 'layout' => 'grid'],
            ],
            'module:invoice_quick_pay' => [
                'name' => 'پرداخت سریع فاکتور',
                'category' => self::CATEGORY_MODULE,
                'icon' => 'invoice',
                'requires' => 'invoice',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'پرداخت فاکتور'],
                    'placeholder' => ['type' => 'string', 'label' => 'راهنمای ورودی', 'default' => 'شماره فاکتور را وارد کنید'],
                ],
                'defaultProps' => ['title' => 'پرداخت فاکتور', 'placeholder' => 'شماره فاکتور را وارد کنید'],
            ],

            // ─── NEXUS PRO PREMIUM BLOCKS ────────────────────────────────────
            'hero_split' => [
                'name' => 'Hero دو ستونه (Split Hero)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero-split',
                'schema' => [
                    'badge'          => ['type' => 'string',  'label' => 'نشان بالای عنوان',  'default' => ''],
                    'title'          => ['type' => 'string',  'label' => 'عنوان اصلی',         'default' => 'عنوان اصلی شما اینجاست'],
                    'subtitle'       => ['type' => 'textarea','label' => 'زیرعنوان',            'default' => 'توضیح کوتاهی درباره محصول یا خدمات شما'],
                    'button_text'    => ['type' => 'string',  'label' => 'متن دکمه اول',        'default' => 'شروع کنید'],
                    'button_url'     => ['type' => 'link',    'label' => 'لینک دکمه اول',       'default' => '#'],
                    'button2_text'   => ['type' => 'string',  'label' => 'متن دکمه دوم',        'default' => 'بیشتر بدانید'],
                    'button2_url'    => ['type' => 'link',    'label' => 'لینک دکمه دوم',       'default' => '#'],
                    'image_url'      => ['type' => 'image',   'label' => 'تصویر',                'default' => ''],
                    'image_side'     => ['type' => 'select',  'label' => 'موقعیت تصویر',        'options' => ['right' => 'راست', 'left' => 'چپ'], 'default' => 'left'],
                    'background'     => ['type' => 'color',   'label' => 'رنگ پس‌زمینه',        'default' => '#ffffff'],
                ],
                'defaultProps' => ['badge' => '', 'title' => 'عنوان اصلی شما اینجاست', 'subtitle' => 'توضیح کوتاهی درباره محصول یا خدمات شما', 'button_text' => 'شروع کنید', 'button_url' => '#', 'button2_text' => 'بیشتر بدانید', 'button2_url' => '#', 'image_url' => '', 'image_side' => 'left', 'background' => '#ffffff'],
            ],
            'hero_gradient' => [
                'name' => 'Hero گرادیان بزرگ',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'hero-gradient',
                'schema' => [
                    'badge'          => ['type' => 'string',  'label' => 'نشان بالای عنوان',  'default' => 'معرفی می‌کنیم'],
                    'title'          => ['type' => 'string',  'label' => 'عنوان اصلی',         'default' => 'قدرت آینده در دستان شما'],
                    'subtitle'       => ['type' => 'textarea','label' => 'زیرعنوان',            'default' => 'پلتفرم چندمنظوره‌ای که برای موفقیت کسب‌وکار شما طراحی شده است'],
                    'button_text'    => ['type' => 'string',  'label' => 'متن دکمه اول',        'default' => 'رایگان شروع کنید'],
                    'button_url'     => ['type' => 'link',    'label' => 'لینک دکمه اول',       'default' => '#'],
                    'button2_text'   => ['type' => 'string',  'label' => 'متن دکمه دوم',        'default' => 'مشاهده دمو'],
                    'button2_url'    => ['type' => 'link',    'label' => 'لینک دکمه دوم',       'default' => '#'],
                    'gradient_from'  => ['type' => 'color',   'label' => 'رنگ گرادیان ابتدا',   'default' => 'var(--tb-primary, #4f46e5)'],
                    'gradient_to'    => ['type' => 'color',   'label' => 'رنگ گرادیان انتها',   'default' => 'var(--tb-secondary, #7c3aed)'],
                    'text_color'     => ['type' => 'color',   'label' => 'رنگ متن',             'default' => 'var(--tb-on-primary, #ffffff)'],
                ],
                'defaultProps' => ['badge' => 'معرفی می‌کنیم', 'title' => 'قدرت آینده در دستان شما', 'subtitle' => 'پلتفرم چندمنظوره‌ای که برای موفقیت کسب‌وکار شما طراحی شده است', 'button_text' => 'رایگان شروع کنید', 'button_url' => '#', 'button2_text' => 'مشاهده دمو', 'button2_url' => '#', 'gradient_from' => 'var(--tb-primary, #4f46e5)', 'gradient_to' => 'var(--tb-secondary, #7c3aed)', 'text_color' => 'var(--tb-on-primary, #ffffff)'],
            ],
            'features_grid' => [
                'name' => 'گرید ویژگی‌ها (Features)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'features',
                // Phase 3C: canonical compositional mode. The legacy
                // `features` JSON prop remains supported for already-authored
                // themes; new sections use independently editable icon_box
                // children through the ordinary Canvas tree contract.
                'is_container' => true,
                'allowedChildren' => ['icon_box'],
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',    'default' => 'چرا ما را انتخاب کنید؟'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',      'default' => 'ما بهترین راه‌حل را برای شما آماده کرده‌ایم'],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون',   'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'style'    => ['type' => 'select',  'label' => 'استایل کارت',  'options' => ['cards' => 'کارت سایه‌دار', 'bordered' => 'کارت خطی', 'minimal' => 'مینیمال'], 'default' => 'cards'],
                    'features' => ['type' => 'repeater', 'label' => 'ویژگی‌ها', 'item_label' => 'ویژگی', 'storage' => 'array', 'fields' => ['icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => 'sparkles'], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'text' => ['type' => 'textarea', 'label' => 'متن', 'default' => '']], 'default' => '[{"icon":"zap","title":"سریع و قدرتمند","text":"بهینه‌سازی شده برای بالاترین کارایی"},{"icon":"lock","title":"امن و مطمئن","text":"رمزنگاری پیشرفته برای حفاظت داده‌ها"},{"icon":"target","title":"دقیق و هوشمند","text":"هوش مصنوعی برای تصمیم‌های بهتر"},{"icon":"rocket","title":"مقیاس‌پذیر","text":"از یک کاربر تا میلیون‌ها کاربر"},{"icon":"lightbulb","title":"نوآورانه","text":"جدیدترین فناوری‌ها در خدمت شما"},{"icon":"globe","title":"چندزبانه","text":"پشتیبانی از فارسی و زبان‌های بین‌المللی"}]'],
                ],
                'defaultProps' => ['title' => 'چرا ما را انتخاب کنید؟', 'subtitle' => 'ما بهترین راه‌حل را برای شما آماده کرده‌ایم', 'columns' => '3', 'style' => 'cards', 'features' => '[{"icon":"zap","title":"سریع و قدرتمند","text":"بهینه‌سازی شده برای بالاترین کارایی"},{"icon":"lock","title":"امن و مطمئن","text":"رمزنگاری پیشرفته برای حفاظت داده‌ها"},{"icon":"target","title":"دقیق و هوشمند","text":"هوش مصنوعی برای تصمیم‌های بهتر"}]'],
            ],
            'stats_row' => [
                'name' => 'آمار و شمارنده (Stats Row)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'stats',
                // Phase 3C additive child mode; `stats` remains the legacy
                // fallback so existing documents render without migration.
                'is_container' => true,
                'allowedChildren' => ['stat_item'],
                'schema' => [
                    'title'      => ['type' => 'string',  'label' => 'عنوان (اختیاری)',  'default' => ''],
                    'stats'      => ['type' => 'repeater', 'label' => 'آمارها', 'item_label' => 'آمار', 'storage' => 'array', 'fields' => ['value' => ['type' => 'string', 'label' => 'عدد', 'default' => '0'], 'suffix' => ['type' => 'string', 'label' => 'پسوند (مثلا + یا %)', 'default' => ''], 'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => '']], 'default' => '[{"value":5000,"suffix":"+","label":"کاربر فعال"},{"value":99,"suffix":"%","label":"رضایت مشتری"},{"value":150,"suffix":"+","label":"پروژه موفق"},{"value":7,"suffix":"سال","label":"تجربه"}]'],
                    'background' => ['type' => 'color',   'label' => 'رنگ پس‌زمینه',    'default' => '#f8fafc'],
                    'background_image' => ['type' => 'image', 'label' => 'تصویر پس‌زمینه', 'default' => ''],
                    'dividers'   => ['type' => 'boolean', 'label' => 'خط جداکننده',     'default' => true],
                ],
                'defaultProps' => ['title' => '', 'stats' => '[{"value":5000,"suffix":"+","label":"کاربر فعال"},{"value":99,"suffix":"%","label":"رضایت مشتری"},{"value":150,"suffix":"+","label":"پروژه موفق"},{"value":7,"suffix":"سال","label":"تجربه"}]', 'background' => '#f8fafc', 'background_image' => '', 'dividers' => true],
            ],
            'stat_item' => [
                'name' => 'آیتم آمار (Stat Item)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'stat-item',
                'schema' => [
                    'value' => ['type' => 'string', 'label' => 'مقدار', 'default' => '100'],
                    'suffix' => ['type' => 'string', 'label' => 'پسوند', 'default' => '+'],
                    'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'مشتری راضی'],
                    'emphasis' => ['type' => 'boolean', 'label' => 'تأکید', 'default' => false],
                ],
                'defaultProps' => ['value' => '100', 'suffix' => '+', 'label' => 'مشتری راضی', 'emphasis' => false],
            ],
            'cta_band' => [
                'name' => 'نوار اقدام (CTA Band)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'cta',
                'is_container' => true,
                'allowedChildren' => ['heading', 'text', 'button'],
                'schema' => [
                    'background' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => 'var(--tb-primary, #4f46e5)'],
                    'text_color' => ['type' => 'color', 'label' => 'رنگ متن', 'default' => 'var(--tb-on-primary, #ffffff)'],
                    'align' => ['type' => 'select', 'label' => 'چینش', 'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'انتها'], 'default'=> 'center'],
                    'padding' => ['type' => 'number', 'label' => 'فاصله عمودی (px)', 'min' => 16, 'max' => 160, 'default' => 48],
                    'radius' => ['type' => 'number', 'label' => 'گردی گوشه (px)', 'min' => 0, 'max' => 48, 'default' => 16],
                ],
                'defaultProps' => ['background' => 'var(--tb-primary, #4f46e5)', 'text_color' => 'var(--tb-on-primary, #ffffff)', 'align' => 'center', 'padding' => 48, 'radius' => 16],
            ],
            'trust_badges_row' => [
                'name' => 'ردیف نشان‌های اعتماد (Trust Badges)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'trust',
                'is_container' => true,
                'allowedChildren' => ['trust_item'],
                'schema' => [
                    'columns' => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '4'],
                    'gap' => ['type' => 'number', 'label' => 'فاصله (px)', 'min' => 0, 'max' => 64, 'default' => 20],
                    'background' => ['type' => 'color', 'label' => 'رنگ پس‌زمینه', 'default' => '#ffffff'],
                ],
                'defaultProps' => ['columns' => '4', 'gap' => 20, 'background' => '#ffffff'],
            ],
            'trust_item' => [
                'name' => 'نشان اعتماد (Trust Item)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'trust-item',
                'schema' => [
                    'icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => 'badge-check'],
                    'label' => ['type' => 'string', 'label' => 'عنوان', 'default' => 'خرید مطمئن'],
                    'description' => ['type' => 'textarea', 'label' => 'توضیح', 'default' => 'تضمین کیفیت و اصالت'],
                    'url' => ['type' => 'link', 'label' => 'لینک', 'default' => ''],
                    'style' => ['type' => 'select', 'label' => 'سبک', 'options' => ['plain' => 'ساده', 'card' => 'کارت', 'outline' => 'خط‌دار'], 'default' => 'plain'],
                ],
                'defaultProps' => ['icon' => 'badge-check', 'label' => 'خرید مطمئن', 'description' => 'تضمین کیفیت و اصالت', 'url' => '', 'style' => 'plain'],
            ],
            // Universal Multipurpose Slider Overhaul: each `logos` item may
            // now optionally carry {image, url, alt} alongside the
            // pre-existing {name} — a bare string or {name}-only entry
            // (all existing authored content) still renders exactly as
            // before (a text badge). 'mode' defaults from the legacy
            // `scroll` boolean when absent, so old content's visual
            // behavior is unchanged; 'grayscale'/'speed'/'visible_count'
            // are new and only affect image logos.
            'client_logos' => [
                'name' => 'اسلایدر لوگوی مشتریان',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'logos',
                'schema' => [
                    'title'  => ['type' => 'string',  'label' => 'عنوان',       'default' => 'مورد اعتماد برندهای بزرگ'],
                    'logos'  => ['type' => 'repeater', 'label' => 'لوگوها', 'item_label' => 'لوگو', 'storage' => 'array', 'fields' => ['name' => ['type' => 'string', 'label' => 'نام برند', 'default' => ''], 'image' => ['type' => 'image', 'label' => 'تصویر لوگو (اختیاری)', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'لینک (اختیاری)', 'default' => ''], 'alt' => ['type' => 'string', 'label' => 'متن جایگزین تصویر (اختیاری)', 'default' => '']], 'default' => '[{"name":"برند اول"},{"name":"برند دوم"},{"name":"برند سوم"},{"name":"برند چهارم"},{"name":"برند پنجم"},{"name":"برند ششم"}]'],
                    'scroll' => ['type' => 'boolean', 'label' => 'حرکت خودکار (مود مرور - قدیمی)', 'default' => true],
                    'mode'   => ['type' => 'select', 'label' => 'نوع نمایش', 'options' => ['marquee' => 'نوار متحرک', 'carousel' => 'اسلایدر با کنترل', 'static' => 'ثابت (Grid)'], 'default' => 'marquee'],
                    'grayscale' => ['type' => 'boolean', 'label' => 'سیاه‌وسفید تا هاور', 'default' => true],
                    'speed' => ['type' => 'number', 'label' => 'سرعت (ثانیه)', 'default' => 25],
                    'visible_count' => ['type' => 'number', 'label' => 'تعداد نمایش هم‌زمان (حالت اسلایدر)', 'default' => 5],
                ],
                'defaultProps' => ['title' => 'مورد اعتماد برندهای بزرگ', 'logos' => '[{"name":"برند اول"},{"name":"برند دوم"},{"name":"برند سوم"},{"name":"برند چهارم"},{"name":"برند پنجم"},{"name":"برند ششم"}]', 'scroll' => true, 'mode' => 'marquee', 'grayscale' => true, 'speed' => 25, 'visible_count' => 5],
                // Phase 2f (ADR-027): 4 canonical themes have a real title
                // but no logos array — the renderer now shows an honest
                // placeholder rather than vanishing entirely.
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'process_steps' => [
                'name' => 'مراحل انجام کار (Process Steps)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'steps',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش', 'default' => 'چطور شروع کنیم؟'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',  'default' => ''],
                    'steps'    => ['type' => 'repeater', 'label' => 'مراحل', 'item_label' => 'مرحله', 'storage' => 'array', 'fields' => ['number' => ['type' => 'string', 'label' => 'شماره', 'default' => ''], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'text' => ['type' => 'textarea', 'label' => 'متن', 'default' => '']], 'default' => '[{"number":"۱","title":"ثبت‌نام رایگان","text":"در چند ثانیه حساب کاربری بسازید"},{"number":"۲","title":"تنظیم سایت","text":"قالب دلخواه را انتخاب کنید"},{"number":"۳","title":"راه‌اندازی","text":"سایت خود را به جهان معرفی کنید"}]'],
                ],
                'defaultProps' => ['title' => 'چطور شروع کنیم؟', 'subtitle' => '', 'steps' => '[{"number":"۱","title":"ثبت‌نام رایگان","text":"در چند ثانیه حساب کاربری بسازید"},{"number":"۲","title":"تنظیم سایت","text":"قالب دلخواه را انتخاب کنید"},{"number":"۳","title":"راه‌اندازی","text":"سایت خود را به جهان معرفی کنید"}]'],
            ],
            // Commercial Productization fase-2: Timeline was completely
            // absent from both registry and renderer — a real gap found
            // during the 22-element audit. Follows the exact
            // schema/JSON-textarea convention already established by
            // process_steps/team_cards, so shared canonical + customer-site
            // visual repeater adapter stay in sync the same way.
            'timeline' => [
                'name' => 'خط زمانی (Timeline)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'timeline',
                'schema' => [
                    'title'    => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'مسیر رشد ما'],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'layout'   => ['type' => 'select', 'label' => 'چیدمان', 'options' => ['vertical' => 'عمودی', 'horizontal' => 'افقی'], 'default' => 'vertical'],
                    'items'    => [
                        // Phase 0.2: promoted from customer-site; `json` storage
                        // keeps the JSON-string shape this field always had.
                        'type' => 'repeater',
                        'label' => 'رویدادها',
                        'item_label' => 'رویداد',
                        'storage' => 'json',
                        'fields' => [
                            'date'       => ['type' => 'string', 'label' => 'تاریخ / مرحله', 'default' => ''],
                            'title'      => ['type' => 'string', 'label' => 'عنوان رویداد', 'default' => ''],
                            'text'       => ['type' => 'textarea', 'label' => 'توضیح', 'default' => ''],
                            'icon'       => ['type' => 'icon', 'label' => 'آیکون (اختیاری)', 'default' => ''],
                            'image'      => ['type' => 'image', 'label' => 'تصویر (اختیاری، جای آیکون را می‌گیرد)', 'default' => ''],
                            'link_label' => ['type' => 'string', 'label' => 'متن دکمه (اختیاری)', 'default' => ''],
                            'link_url'   => ['type' => 'link', 'label' => 'لینک دکمه (اختیاری)', 'default' => ''],
                        ],
                        'default' => '[{"date":"۱۳۹۸","title":"تأسیس شرکت","text":"شروع فعالیت با یک تیم کوچک و ایدهٔ بزرگ.","icon":"rocket","image":"","link_label":"","link_url":""},{"date":"۱۴۰۰","title":"عرضهٔ نسخهٔ اول پلتفرم","text":"اولین نسخهٔ پلتفرم برای مشتریان منتشر شد.","icon":"package","image":"","link_label":"","link_url":""},{"date":"۱۴۰۲","title":"عبور از هزار مشتری فعال","text":"رشد پایدار و اعتماد کسب‌وکارهای بیشتر.","icon":"trending-up","image":"","link_label":"","link_url":""},{"date":"۱۴۰۴","title":"ورود به بازارهای جدید","text":"توسعهٔ خدمات و افزودن امکانات سازمانی.","icon":"globe","image":"","link_label":"مشاهدهٔ نقشهٔ راه","link_url":"/about"}]',
                    ],
                ],
                'defaultProps' => [
                    'title' => 'مسیر رشد ما',
                    'subtitle' => '',
                    'layout' => 'vertical',
                    'items' => '[{"date":"۱۳۹۸","title":"تأسیس شرکت","text":"شروع فعالیت با یک تیم کوچک و ایدهٔ بزرگ.","icon":"rocket","image":"","link_label":"","link_url":""},{"date":"۱۴۰۰","title":"عرضهٔ نسخهٔ اول پلتفرم","text":"اولین نسخهٔ پلتفرم برای مشتریان منتشر شد.","icon":"package","image":"","link_label":"","link_url":""},{"date":"۱۴۰۲","title":"عبور از هزار مشتری فعال","text":"رشد پایدار و اعتماد کسب‌وکارهای بیشتر.","icon":"trending-up","image":"","link_label":"","link_url":""}]',
                ],
                'emptyOutputPolicy' => self::EMPTY_POLICY_PLACEHOLDER,
            ],
            'team_cards' => [
                'name' => 'کارت اعضای تیم',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'team',
                'schema' => [
                    'title'   => ['type' => 'string',  'label' => 'عنوان بخش',   'default' => 'تیم ما'],
                    'columns' => ['type' => 'select',  'label' => 'تعداد ستون',  'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'members' => ['type' => 'repeater', 'label' => 'اعضای تیم', 'item_label' => 'عضو', 'storage' => 'json', 'fields' => ['name' => ['type' => 'string', 'label' => 'نام', 'default' => ''], 'role' => ['type' => 'string', 'label' => 'سمت', 'default' => ''], 'image' => ['type' => 'image', 'label' => 'تصویر عضو', 'default' => ''], 'bio' => ['type' => 'textarea', 'label' => 'توضیح', 'default' => '']], 'default' => '[{"name":"نام کامل","role":"مدیرعامل","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"مدیر فنی","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"طراح ارشد","image":"","bio":"توضیح کوتاه"}]'],
                ],
                'defaultProps' => ['title' => 'تیم ما', 'columns' => '3', 'members' => '[{"name":"نام کامل","role":"مدیرعامل","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"مدیر فنی","image":"","bio":"توضیح کوتاه"},{"name":"نام کامل","role":"طراح ارشد","image":"","bio":"توضیح کوتاه"}]'],
            ],
            'skills_bars' => [
                'name' => 'نوارهای مهارت (Skills)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'skills',
                'schema' => [
                    'title'  => ['type' => 'string',  'label' => 'عنوان',       'default' => 'مهارت‌های من'],
                    'skills' => ['type' => 'repeater', 'label' => 'مهارت‌ها', 'item_label' => 'مهارت', 'storage' => 'array', 'fields' => ['name' => ['type' => 'string', 'label' => 'نام مهارت', 'default' => ''], 'percent' => ['type' => 'string', 'label' => 'درصد (۰ تا ۱۰۰)', 'default' => '0']], 'default' => '[{"name":"طراحی رابط کاربری","percent":92},{"name":"برنامه‌نویسی فرانت‌اند","percent":88},{"name":"توسعه بک‌اند","percent":80},{"name":"مدیریت پروژه","percent":75}]'],
                ],
                'defaultProps' => ['title' => 'مهارت‌های من', 'skills' => '[{"name":"طراحی رابط کاربری","percent":92},{"name":"برنامه‌نویسی فرانت‌اند","percent":88},{"name":"توسعه بک‌اند","percent":80}]'],
            ],
            // Universal Multipurpose Slider Overhaul: fixes the real
            // schema/renderer mismatch found in audit — `columns` is now
            // genuinely honored by both display modes (see
            // BlockRenderer::renderTestimonialsSlider()). Each item in
            // `items` may now optionally add `company`/`avatar` — omitted
            // on existing content, rendering exactly as before (name/role/
            // text/rating + initials avatar).
            'testimonials_slider' => [
                'name' => 'اسلایدر نظرات مشتریان',
                'category' => self::CATEGORY_DYNAMIC,
                'icon' => 'testimonials',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',  'default' => 'مشتریان ما چه می‌گویند'],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون', 'options' => ['1' => '۱', '2' => '۲', '3' => '۳'], 'default' => '3'],
                    'mode'     => ['type' => 'select', 'label' => 'نوع نمایش', 'options' => ['carousel' => 'اسلایدر با کنترل', 'static' => 'ثابت (Grid)'], 'default' => 'carousel'],
                    'autoplay' => ['type' => 'boolean', 'label' => 'پخش خودکار', 'default' => true],
                    'interval' => ['type' => 'number', 'label' => 'فاصله زمانی (میلی‌ثانیه)', 'default' => 5000],
                    'show_summary' => ['type' => 'boolean', 'label' => 'نمایش خلاصهٔ امتیازها', 'default' => false],
                    // Was a JSON textarea. Legacy JSON-string values still load:
                    // the Canvas repeater parses them and the renderer accepts
                    // both shapes; the next edit persists the array shape.
                    'items'    => [
                        'type' => 'repeater',
                        'label' => 'نظرات',
                        'item_label' => 'نظر',
                        'storage' => 'array',
                        'fields' => [
                            'name'    => ['type' => 'string',   'label' => 'نام', 'default' => 'نام مشتری'],
                            'role'    => ['type' => 'string',   'label' => 'سمت', 'default' => ''],
                            'company' => ['type' => 'string',   'label' => 'شرکت (اختیاری)', 'default' => ''],
                            'avatar'  => ['type' => 'image',    'label' => 'تصویر (اختیاری)', 'default' => ''],
                            'text'    => ['type' => 'textarea', 'label' => 'متن نظر', 'default' => ''],
                            'rating'  => ['type' => 'number',   'label' => 'امتیاز (۰ تا ۵)', 'default' => 5, 'min' => 0, 'max' => 5, 'step' => 1],
                            // Phase 3b (ADR-069) — see reviewCardHtml().
                            'verified' => ['type' => 'boolean', 'label' => 'خریدار تاییدشده', 'default' => false],
                            'date'     => ['type' => 'string',  'label' => 'تاریخ (شمسی، مثل ۱۴۰۵/۰۸/۰۲)', 'default' => ''],
                        ],
                        'default' => [
                            ['name' => 'رضا احمدی', 'role' => 'مدیر شرکت', 'text' => 'این پلتفرم کسب‌وکار ما را متحول کرد. واقعاً عالی است!', 'rating' => 5],
                            ['name' => 'مریم نوری', 'role' => 'کارآفرین', 'text' => 'طراحی فوق‌العاده و پشتیبانی سریع. پیشنهاد می‌دهم!', 'rating' => 5],
                            ['name' => 'علی حسینی', 'role' => 'توسعه‌دهنده', 'text' => 'بهترین انتخاب برای ساخت سایت حرفه‌ای بود.', 'rating' => 5],
                        ],
                    ],
                ],
                'defaultProps' => ['title' => 'مشتریان ما چه می‌گویند', 'columns' => '3', 'mode' => 'carousel', 'autoplay' => true, 'interval' => 5000, 'show_summary' => false, 'items' => [
                    ['name' => 'رضا احمدی', 'role' => 'مدیر شرکت', 'text' => 'این پلتفرم کسب‌وکار ما را متحول کرد!', 'rating' => 5],
                    ['name' => 'مریم نوری', 'role' => 'کارآفرین', 'text' => 'طراحی فوق‌العاده و پشتیبانی سریع.', 'rating' => 5],
                    ['name' => 'علی حسینی', 'role' => 'توسعه‌دهنده', 'text' => 'بهترین انتخاب برای سایت حرفه‌ای.', 'rating' => 5],
                ]],
            ],
            'trust_badges' => [
                'name' => 'نشان‌های اعتماد (Trust Badges)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'trust',
                'schema' => [
                    'badges'     => ['type' => 'repeater', 'label' => 'نشان‌ها', 'item_label' => 'نشان', 'storage' => 'array', 'fields' => ['icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => 'badge-check'], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'text' => ['type' => 'string', 'label' => 'متن', 'default' => '']], 'default' => '[{"icon":"truck","title":"ارسال رایگان","text":"برای سفارش‌های بالای ۲۰۰ هزار تومان"},{"icon":"shield-check","title":"پرداخت امن","text":"درگاه‌های پرداخت معتبر ایران"},{"icon":"undo-2","title":"بازگشت آسان","text":"ضمانت بازگشت تا ۷ روز"},{"icon":"headset","title":"پشتیبانی ۲۴/۷","text":"همیشه در کنار شما هستیم"}]'],
                    'background' => ['type' => 'color',   'label' => 'رنگ پس‌زمینه','default' => '#f8fafc'],
                ],
                'defaultProps' => ['badges' => '[{"icon":"truck","title":"ارسال رایگان","text":"بالای ۲۰۰ هزار تومان"},{"icon":"shield-check","title":"پرداخت امن","text":"درگاه‌های معتبر"},{"icon":"undo-2","title":"بازگشت آسان","text":"تا ۷ روز"},{"icon":"headset","title":"پشتیبانی ۲۴/۷","text":"همیشه در دسترس"}]', 'background' => '#f8fafc'],
            ],
            'pricing_cards' => [
                'name' => 'کارت‌های تعرفه (Pricing Cards)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'pricing',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',  'default' => 'پلن‌های ما'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',    'default' => 'مناسب‌ترین پلن را انتخاب کنید'],
                    // Phase 3b (ADR-069): monthly / yearly switch — see renderPricingCards().
                    'billing_toggle' => ['type' => 'boolean', 'label' => 'دکمهٔ ماهانه / سالانه', 'default' => true],
                    'yearly_discount_label' => ['type' => 'string', 'label' => 'برچسب تخفیف سالانه', 'default' => '۲۰٪ تخفیف'],
                    'plans'    => ['type' => 'repeater', 'label' => 'پلن‌ها', 'item_label' => 'پلن', 'storage' => 'array', 'fields' => ['name' => ['type' => 'string', 'label' => 'نام پلن', 'default' => ''], 'price' => ['type' => 'string', 'label' => 'قیمت ماهانه', 'default' => ''], 'period' => ['type' => 'string', 'label' => 'دوره (مثلا / ماه)', 'default' => ''], 'price_yearly' => ['type' => 'string', 'label' => 'قیمت سالانه (برای دکمهٔ سالانه)', 'default' => ''], 'period_yearly' => ['type' => 'string', 'label' => 'دورهٔ سالانه', 'default' => '/ سال'], 'badge' => ['type' => 'string', 'label' => 'برچسب (اختیاری)', 'default' => ''], 'highlight' => ['type' => 'boolean', 'label' => 'پلن ویژه (روبان محبوب)', 'default' => false], 'features' => ['type' => 'repeater', 'label' => 'ویژگی‌ها', 'item_label' => 'ویژگی', 'storage' => 'array', 'default' => [], 'fields' => ['text' => ['type' => 'string', 'label' => 'متن ویژگی', 'default' => ''], 'included' => ['type' => 'boolean', 'label' => 'در این پلن هست', 'default' => true], 'tooltip' => ['type' => 'string', 'label' => 'توضیح (راهنمای کوچک)', 'default' => '']]], 'cta_text' => ['type' => 'string', 'label' => 'متن دکمه', 'default' => ''], 'cta_url' => ['type' => 'link', 'label' => 'لینک دکمه', 'default' => '#']], 'default' => '[{"name":"رایگان","price":"رایگان","period":"","badge":"","highlight":false,"features":["ویژگی اول","ویژگی دوم","ویژگی سوم"],"cta_text":"شروع رایگان","cta_url":"#"},{"name":"حرفه‌ای","price":"۱۵۰ هزار","period":"/ ماه","price_yearly":"۱٬۴۴۰ هزار","period_yearly":"/ سال","badge":"محبوب‌ترین","highlight":true,"features":["همه ویژگی‌های رایگان","ویژگی اضافه اول","ویژگی اضافه دوم","پشتیبانی اولویت‌دار"],"cta_text":"انتخاب این پلن","cta_url":"#"},{"name":"سازمانی","price":"۳۵۰ هزار","period":"/ ماه","price_yearly":"۳٬۳۶۰ هزار","period_yearly":"/ سال","badge":"","highlight":false,"features":["همه ویژگی‌های حرفه‌ای","حساب نامحدود","API اختصاصی","مدیر حساب"],"cta_text":"تماس بگیرید","cta_url":"#"}]'],
                ],
                'defaultProps' => ['title' => 'پلن‌های ما', 'subtitle' => 'مناسب‌ترین پلن را انتخاب کنید', 'billing_toggle' => true, 'yearly_discount_label' => '۲۰٪ تخفیف', 'plans' => '[{"name":"رایگان","price":"رایگان","period":"","badge":"","highlight":false,"features":["ویژگی اول","ویژگی دوم"],"cta_text":"شروع رایگان","cta_url":"#"},{"name":"حرفه‌ای","price":"۱۵۰ هزار","period":"/ ماه","price_yearly":"۱٬۴۴۰ هزار","period_yearly":"/ سال","badge":"محبوب‌ترین","highlight":true,"features":["همه ویژگی‌های رایگان","ویژگی اضافه"],"cta_text":"انتخاب این پلن","cta_url":"#"}]'],
            ],
            'newsletter_form' => [
                'name' => 'فرم خبرنامه (Newsletter)',
                'category' => self::CATEGORY_MARKETING,
                'icon' => 'newsletter',
                'schema' => [
                    'title'       => ['type' => 'string', 'label' => 'عنوان',        'default' => 'در خبرنامه ما عضو شوید'],
                    'subtitle'    => ['type' => 'string', 'label' => 'زیرعنوان',     'default' => 'جدیدترین اخبار و پیشنهادات ویژه را دریافت کنید'],
                    'placeholder' => ['type' => 'string', 'label' => 'placeholder',  'default' => 'ایمیل یا شماره موبایل شما'],
                    'button_text' => ['type' => 'string', 'label' => 'متن دکمه',     'default' => 'عضویت رایگان'],
                    'background'  => ['type' => 'color',  'label' => 'رنگ پس‌زمینه','default' => 'var(--tb-primary, #4f46e5)'],
                    'text_color'  => ['type' => 'color',  'label' => 'رنگ متن',      'default' => 'var(--tb-on-primary, #ffffff)'],
                ],
                'defaultProps' => ['title' => 'در خبرنامه ما عضو شوید', 'subtitle' => 'جدیدترین اخبار و پیشنهادات ویژه را دریافت کنید', 'placeholder' => 'ایمیل یا شماره موبایل شما', 'button_text' => 'عضویت رایگان', 'background' => 'var(--tb-primary, #4f46e5)', 'text_color' => 'var(--tb-on-primary, #ffffff)'],
            ],
            'bento_grid' => [
                'name' => 'گرید بنتو (Bento Grid)',
                'category' => self::CATEGORY_LAYOUT,
                'icon' => 'bento',
                'schema' => [
                    'title' => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => 'همه چیز در یک پلتفرم'],
                    // Canonical repeater contract (InspectorFieldRegistry): child
                    // controls live under `fields`, `storage` names the persisted
                    // shape. `size` values are exactly the renderer's allowlist
                    // (renderBentoGrid) — legacy 'normal' still renders as small.
                    // The gradient is an image overlay only: empty by default, and
                    // ignored by the renderer on cards without a bg_image.
                    'items' => [
                        'type' => 'repeater',
                        'label' => 'کارت‌های بنتو',
                        'item_label' => 'کارت',
                        'storage' => 'array',
                        // Phase 3b (ADR-069): `kind` turns a cell into a live
                        // product card, a Jalali countdown teaser or an icon
                        // metric; sizes span a 12-column grid (2 on tablets,
                        // 1 on phones) — see renderBentoGrid().
                        'fields' => [
                            'kind'          => ['type' => 'select',   'label' => 'نوع کارت', 'options' => [
                                'content'   => 'محتوا (متن و تصویر)',
                                'product'   => 'محصول زنده',
                                'countdown' => 'شمارش معکوس',
                                'metric'    => 'آمار با آیکون',
                            ], 'default' => 'content'],
                            'title'         => ['type' => 'string',   'label' => 'عنوان کارت', 'default' => 'کارت جدید'],
                            'text'          => ['type' => 'textarea', 'label' => 'توضیحات کارت', 'default' => ''],
                            'icon'          => ['type' => 'icon',     'label' => 'آیکون', 'default' => 'zap'],
                            'size'          => ['type' => 'select',   'label' => 'اندازه کارت', 'options' => [
                                'small'    => 'یک‌سوم (۴ از ۱۲)',
                                'half'     => 'نیمه (۶ از ۱۲)',
                                'large'    => 'دوسوم (۸ از ۱۲)',
                                'full'     => 'تمام‌عرض (۱۲ از ۱۲)',
                                'tall'     => 'بلند (۴ از ۱۲، دو ردیف)',
                                'featured' => 'ویژه (۸ از ۱۲، دو ردیف)',
                            ], 'default' => 'small'],
                            'product_id'    => ['type' => 'number',   'label' => 'شناسه محصول (۰ = جدیدترین)', 'default' => 0, 'min' => 0, 'show_if' => 'kind=product'],
                            'end_date'      => ['type' => 'string',   'label' => 'تاریخ پایان (شمسی، مثل ۱۴۰۵/۰۸/۱۵)', 'default' => '', 'show_if' => 'kind=countdown'],
                            'end_time'      => ['type' => 'string',   'label' => 'ساعت پایان (۲۳:۵۹)', 'default' => '', 'show_if' => 'kind=countdown'],
                            'metric_value'  => ['type' => 'string',   'label' => 'عدد آمار (مثل ۹۸٪ یا 12000+)', 'default' => '', 'show_if' => 'kind=metric'],
                            'bg_color'      => ['type' => 'color',   'label' => 'رنگ پس‌زمینه', 'default' => '#f8fafc'],
                            'text_color'    => ['type' => 'color',   'label' => 'رنگ متن', 'default' => '#0f172a'],
                            'link'          => ['type' => 'link',    'label' => 'لینک کارت (اختیاری)', 'default' => ''],
                            // Canvas overhaul Unit 2a: renderBentoGrid() has always
                            // shown `image` as the card's own picture (the
                            // `has-media` card the Universal QA theme seeds), but
                            // no control existed, so it could not be edited or
                            // localized on install.
                            'image'         => ['type' => 'image',   'label' => 'تصویر کارت (اختیاری)', 'default' => '', 'show_if' => 'kind=content'],
                            'bg_image'      => ['type' => 'image',   'label' => 'تصویر پس‌زمینه (اختیاری)', 'default' => ''],
                            'bg_color_from' => ['type' => 'color',   'label' => 'رنگ بالای پوشش تصویر', 'default' => ''],
                            'bg_color_to'   => ['type' => 'color',   'label' => 'رنگ پایین پوشش تصویر', 'default' => ''],
                            'glow_enabled'  => ['type' => 'boolean', 'label' => 'افکت درخشش حاشیه', 'default' => false],
                            'glow_color'    => ['type' => 'color',   'label' => 'رنگ درخشش', 'default' => 'var(--tb-primary, #6366f1)', 'show_if' => 'glow_enabled'],
                            'glow_speed'    => ['type' => 'number',  'label' => 'سرعت درخشش (ثانیه)', 'default' => 2, 'min' => 0.5, 'max' => 4, 'step' => 0.25, 'show_if' => 'glow_enabled'],
                        ],
                        'default' => [
                            ['title' => 'مدیریت هوشمند', 'text' => 'داشبورد جامع', 'icon' => 'chart-column', 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                            ['title' => 'امنیت پیشرفته', 'text' => 'رمزنگاری داده‌ها', 'icon' => 'lock', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                            ['title' => 'سرعت بالا', 'text' => 'CDN جهانی', 'icon' => 'zap', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                        ],
                    ],
                ],
                'defaultProps' => [
                    'title' => 'همه چیز در یک پلتفرم',
                    'items' => [
                        ['title' => 'مدیریت هوشمند', 'text' => 'داشبورد جامع', 'icon' => 'chart-column', 'size' => 'large', 'bg_color' => '#4f46e5', 'text_color' => '#ffffff', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                        ['title' => 'امنیت پیشرفته', 'text' => 'رمزنگاری داده‌ها', 'icon' => 'lock', 'size' => 'small', 'bg_color' => '#f0fdf4', 'text_color' => '#14532d', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                        ['title' => 'سرعت بالا', 'text' => 'CDN جهانی', 'icon' => 'zap', 'size' => 'small', 'bg_color' => '#fef9c3', 'text_color' => '#713f12', 'link' => '', 'bg_image' => '', 'bg_color_from' => '', 'bg_color_to' => '', 'glow_enabled' => false, 'glow_color' => '#6366f1', 'glow_speed' => 2],
                    ],
                ],
            ],
            'services_cards' => [
                'name' => 'کارت‌های خدمات (Services)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'services-cards',
                'schema' => [
                    'title'    => ['type' => 'string',  'label' => 'عنوان بخش',    'default' => 'خدمات ما'],
                    'subtitle' => ['type' => 'string',  'label' => 'زیرعنوان',      'default' => ''],
                    'columns'  => ['type' => 'select',  'label' => 'تعداد ستون',   'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'style'    => ['type' => 'select',  'label' => 'استایل',        'options' => ['bordered' => 'خطی', 'filled' => 'پر', 'minimal' => 'مینیمال'], 'default' => 'bordered'],
                    'services' => ['type' => 'repeater', 'label' => 'خدمات', 'item_label' => 'خدمت', 'storage' => 'array', 'fields' => ['icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => ''], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'text' => ['type' => 'textarea', 'label' => 'متن', 'default' => ''], 'link' => ['type' => 'link', 'label' => 'لینک', 'default' => '#'], 'link_text' => ['type' => 'string', 'label' => 'متن لینک', 'default' => 'بیشتر بدانید']], 'default' => '[{"icon":"palette","title":"طراحی رابط کاربری","text":"طراحی تجربه کاربری زیبا و کاربردی","link":"#","link_text":"بیشتر بدانید"},{"icon":"settings","title":"توسعه فنی","text":"پیاده‌سازی با جدیدترین فناوری‌ها","link":"#","link_text":"بیشتر بدانید"},{"icon":"trending-up","title":"بازاریابی دیجیتال","text":"افزایش حضور آنلاین کسب‌وکار شما","link":"#","link_text":"بیشتر بدانید"}]'],
                ],
                'defaultProps' => ['title' => 'خدمات ما', 'subtitle' => '', 'columns' => '3', 'style' => 'bordered', 'services' => '[{"icon":"palette","title":"طراحی","text":"رابط کاربری زیبا","link":"#","link_text":"بیشتر"},{"icon":"settings","title":"توسعه","text":"فناوری‌های جدید","link":"#","link_text":"بیشتر"},{"icon":"trending-up","title":"بازاریابی","text":"حضور آنلاین","link":"#","link_text":"بیشتر"}]'],
            ],
            'contact_split' => [
                'name' => 'تماس دو ستونه (Contact Split)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'contact',
                'schema' => [
                    'title'      => ['type' => 'string',  'label' => 'عنوان',       'default' => 'با ما در ارتباط باشید'],
                    'subtitle'   => ['type' => 'string',  'label' => 'زیرعنوان',    'default' => 'هر سوالی دارید، ما پاسخگو هستیم'],
                    'phone'      => ['type' => 'string',  'label' => 'شماره تلفن',  'default' => ''],
                    'email'      => ['type' => 'string',  'label' => 'ایمیل',        'default' => ''],
                    'address'    => ['type' => 'string',  'label' => 'آدرس',         'default' => ''],
                    'hours'      => ['type' => 'string',  'label' => 'ساعات کاری',  'default' => 'شنبه تا پنجشنبه، ۹ تا ۱۷'],
                    'show_form'  => ['type' => 'boolean', 'label' => 'نمایش فرم',   'default' => true],
                ],
                'defaultProps' => ['title' => 'با ما در ارتباط باشید', 'subtitle' => 'هر سوالی دارید، ما پاسخگو هستیم', 'phone' => '', 'email' => '', 'address' => '', 'hours' => 'شنبه تا پنجشنبه، ۹ تا ۱۷', 'show_form' => true],
            ],
            // ─── PHASE 2E — CANONICAL BLOCK RENDERING COVERAGE ─────────────────
            // See ADR-026. Real, distinct content shapes actually authored by
            // the canonical catalog — never forced into an existing type with
            // an incompatible schema merely to reduce the type count.
            'hero_trust' => [
                'name' => 'هیرو با اعتماد مشتری (Hero Trust)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'hero',
                'schema' => [
                    'badge'               => ['type' => 'string', 'label' => 'نشان', 'default' => ''],
                    'title'               => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'subtitle'            => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'rating'              => ['type' => 'string', 'label' => 'امتیاز (اختیاری)', 'default' => ''],
                    'rating_label'        => ['type' => 'string', 'label' => 'برچسب امتیاز', 'default' => ''],
                    'phone'               => ['type' => 'string', 'label' => 'شماره تماس (اختیاری)', 'default' => ''],
                    'btn_primary_text'    => ['type' => 'string', 'label' => 'متن دکمه اصلی', 'default' => ''],
                    'btn_primary_url'     => ['type' => 'link', 'label' => 'لینک دکمه اصلی', 'default' => '#'],
                    'btn_secondary_text'  => ['type' => 'string', 'label' => 'متن دکمه دوم', 'default' => ''],
                    'btn_secondary_url'   => ['type' => 'link', 'label' => 'لینک دکمه دوم', 'default' => '#'],
                ],
                'defaultProps' => ['badge' => '', 'title' => '', 'subtitle' => '', 'rating' => '', 'rating_label' => '', 'phone' => '', 'btn_primary_text' => '', 'btn_primary_url' => '#', 'btn_secondary_text' => '', 'btn_secondary_url' => '#'],
            ],
            'about_section' => [
                'name' => 'بخش درباره ما (About Section)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'info',
                'schema' => [
                    'badge'       => ['type' => 'string',  'label' => 'نشان', 'default' => ''],
                    'title'       => ['type' => 'string',  'label' => 'عنوان', 'default' => ''],
                    'description' => ['type' => 'textarea','label' => 'توضیحات', 'default' => ''],
                    'features'    => ['type' => 'repeater', 'label' => 'ویژگی‌ها', 'item_label' => 'ویژگی', 'storage' => 'array', 'fields' => ['label' => ['type' => 'string', 'label' => 'متن ویژگی', 'default' => '']], 'default' => []],
                    'stats'       => ['type' => 'repeater', 'label' => 'آمار (اختیاری)', 'item_label' => 'آمار', 'storage' => 'array', 'fields' => ['value' => ['type' => 'string', 'label' => 'عدد', 'default' => ''], 'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => '']], 'default' => []],
                    'btn_text'    => ['type' => 'string',  'label' => 'متن دکمه', 'default' => ''],
                    'btn_url'     => ['type' => 'link',  'label' => 'لینک دکمه', 'default' => '#'],
                ],
                'defaultProps' => ['badge' => '', 'title' => '', 'description' => '', 'features' => [], 'stats' => [], 'btn_text' => '', 'btn_url' => '#'],
            ],
            'service_cards' => [
                'name' => 'کارت‌های خدمات با لینک (Service Cards)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'services-cards',
                'schema' => [
                    'title'    => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'columns'  => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '3'],
                    'cards'    => ['type' => 'repeater', 'label' => 'کارت‌ها', 'item_label' => 'کارت', 'storage' => 'array', 'fields' => ['icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => ''], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'description' => ['type' => 'textarea', 'label' => 'توضیحات', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'لینک (اختیاری)', 'default' => '']], 'default' => []],
                ],
                'defaultProps' => ['title' => '', 'subtitle' => '', 'columns' => '3', 'cards' => []],
            ],
            'project_cards' => [
                'name' => 'کارت‌های نمونه‌کار (Project Cards)',
                'category' => self::CATEGORY_CONTENT,
                'icon' => 'gallery',
                'schema' => [
                    'title'    => ['type' => 'string', 'label' => 'عنوان بخش', 'default' => ''],
                    'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'columns'  => ['type' => 'select', 'label' => 'تعداد ستون', 'options' => ['2' => '۲', '3' => '۳', '4' => '۴'], 'default' => '2'],
                    'cards'    => ['type' => 'repeater', 'label' => 'کارت‌ها', 'item_label' => 'کارت', 'storage' => 'array', 'fields' => ['category' => ['type' => 'string', 'label' => 'دسته‌بندی', 'default' => ''], 'title' => ['type' => 'string', 'label' => 'عنوان', 'default' => ''], 'description' => ['type' => 'textarea', 'label' => 'توضیحات', 'default' => ''], 'url' => ['type' => 'link', 'label' => 'لینک (اختیاری)', 'default' => '']], 'default' => []],
                ],
                'defaultProps' => ['title' => '', 'subtitle' => '', 'columns' => '2', 'cards' => []],
            ],
            'contact_info' => [
                'name' => 'اطلاعات تماس (Contact Info)',
                'category' => self::CATEGORY_UTILITY,
                'icon' => 'contact',
                'schema' => [
                    'title'      => ['type' => 'string', 'label' => 'عنوان', 'default' => ''],
                    'subtitle'   => ['type' => 'string', 'label' => 'زیرعنوان', 'default' => ''],
                    'background' => ['type' => 'string', 'label' => 'رنگ پس‌زمینه (اختیاری)', 'default' => ''],
                    'items'      => ['type' => 'repeater', 'label' => 'موارد', 'item_label' => 'مورد', 'storage' => 'array', 'fields' => ['icon' => ['type' => 'icon', 'label' => 'آیکون', 'default' => ''], 'label' => ['type' => 'string', 'label' => 'برچسب', 'default' => ''], 'value' => ['type' => 'string', 'label' => 'مقدار', 'default' => '']], 'default' => []],
                ],
                'defaultProps' => ['title' => '', 'subtitle' => '', 'background' => '', 'items' => []],
            ],
        ];

        // Snapshot the canonical-only set before the host merge below, so
        // registerHostType()/isCanonicalType() can check collisions against
        // exactly the canonical definitions — never against a type that
        // only exists because a host already registered it.
        self::$canonicalOnly = self::$blocks;

        // Host-registered types are additive only — never override a
        // canonical definition above. PHP's `+` keeps the LEFT operand's
        // value for a duplicate key, so the canonical array (self::$blocks
        // as it stands here) must come first. registerHostType() itself
        // already rejects a colliding registration before it ever reaches
        // self::$hostTypes (Phase 3E-B/ADR-030), so this merge can no
        // longer silently hide one — it is now a redundant, harmless second
        // guarantee of the same already-enforced invariant.
        self::$blocks = self::$blocks + self::$hostTypes;

        return self::$blocks;
    }

    public static function get(string $type): ?array
    {
        $all = self::all();
        return $all[$type] ?? null;
    }

    public static function defaultProps(string $type): array
    {
        $def = self::get($type);
        return $def['defaultProps'] ?? [];
    }

    /** For editor: list by category. */
    public static function byCategory(): array
    {
        $byCat = [];
        foreach (self::all() as $type => $def) {
            $cat = $def['category'] ?? 'content';
            if (!isset($byCat[$cat])) {
                $byCat[$cat] = [];
            }
            $byCat[$cat][$type] = $def;
        }
        return $byCat;
    }

    /** Category labels (Persian). */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_LAYOUT    => 'چیدمان',
            self::CATEGORY_CONTENT   => 'محتوای ثابت',
            self::CATEGORY_DYNAMIC   => 'محتوای پویا',
            self::CATEGORY_ECOMMERCE => 'فروشگاه',
            self::CATEGORY_MARKETING => 'بازاریابی',
            self::CATEGORY_FORMS     => 'فرم‌ها',
            self::CATEGORY_UTILITY   => 'ابزار',
            self::CATEGORY_MODULE    => 'ماژول‌ها',
        ];
    }

    /** Category icons for canvas sidebar. */
    public static function categoryIcons(): array
    {
        return [
            self::CATEGORY_LAYOUT    => '📐',
            self::CATEGORY_CONTENT   => '📝',
            self::CATEGORY_DYNAMIC   => '🔄',
            self::CATEGORY_ECOMMERCE => '🛒',
            self::CATEGORY_MARKETING => '📣',
            self::CATEGORY_FORMS     => '📋',
            self::CATEGORY_UTILITY   => '🔧',
            self::CATEGORY_MODULE    => '🧩',
        ];
    }

    /** Get all module blocks grouped by their required module. */
    public static function moduleBlocksByModule(): array
    {
        $grouped = [];
        foreach (self::all() as $type => $def) {
            if ($def['category'] !== self::CATEGORY_MODULE) continue;
            $mod = $def['requires'] ?? '_core';
            $grouped[$mod][$type] = $def;
        }
        return $grouped;
    }
}
