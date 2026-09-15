<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Theme Builder: full visual theme (header, footer, global styles).
 * Multi-tenant: site_id null = platform template; set = tenant override.
 */
class Theme extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GENERATOR = 'generator';
    public const SOURCE_SEEDER = 'seeder';

    public const CATEGORY_CORPORATE     = 'corporate';
    public const CATEGORY_PERSONAL      = 'personal';
    public const CATEGORY_BLOG          = 'blog';
    public const CATEGORY_NEWS          = 'news';
    public const CATEGORY_EDUCATIONAL   = 'educational';
    public const CATEGORY_MEDICAL       = 'medical';
    public const CATEGORY_LEGAL         = 'legal';
    public const CATEGORY_LAWYER        = 'lawyer';
    public const CATEGORY_REAL_ESTATE   = 'real_estate';
    public const CATEGORY_RESTAURANT    = 'restaurant';
    public const CATEGORY_PORTFOLIO     = 'portfolio';
    public const CATEGORY_AGENCY        = 'agency';
    public const CATEGORY_MARKETPLACE   = 'marketplace';
    public const CATEGORY_STORE         = 'store';
    public const CATEGORY_SAAS          = 'saas';
    public const CATEGORY_LANDING       = 'landing';
    public const CATEGORY_BEAUTY        = 'beauty';
    public const CATEGORY_TRAVEL        = 'travel';
    public const CATEGORY_AUTOMOTIVE    = 'automotive';
    public const CATEGORY_INDUSTRIAL    = 'industrial';
    public const CATEGORY_CONSTRUCTION  = 'construction';
    public const CATEGORY_FASHION       = 'fashion';
    public const CATEGORY_ELECTRONICS   = 'electronics';
    public const CATEGORY_SINGLE_PRODUCT= 'single_product';
    public const CATEGORY_BUSINESS      = 'business';
    public const CATEGORY_SERVICES      = 'services';

    public const LICENSE_FREE     = 'free';
    public const LICENSE_PREMIUM  = 'premium';
    public const LICENSE_BUNDLE   = 'bundle';

    public static function categoryLabel(string $category): string
    {
        return [
            self::CATEGORY_CORPORATE      => 'شرکتی',
            self::CATEGORY_PERSONAL       => 'شخصی',
            self::CATEGORY_BLOG           => 'وبلاگ',
            self::CATEGORY_NEWS           => 'خبری',
            self::CATEGORY_EDUCATIONAL    => 'آموزشی',
            self::CATEGORY_MEDICAL        => 'پزشکی',
            self::CATEGORY_LEGAL          => 'حقوقی',
            self::CATEGORY_LAWYER         => 'وکیل',
            self::CATEGORY_REAL_ESTATE    => 'مسکن',
            self::CATEGORY_RESTAURANT     => 'رستوران',
            self::CATEGORY_PORTFOLIO      => 'پورتفولیو',
            self::CATEGORY_AGENCY         => 'آژانس',
            self::CATEGORY_MARKETPLACE    => 'مارکت‌پلیس',
            self::CATEGORY_STORE          => 'فروشگاه',
            self::CATEGORY_SAAS           => 'SaaS',
            self::CATEGORY_LANDING        => 'لندینگ پیج',
            self::CATEGORY_BEAUTY         => 'زیبایی',
            self::CATEGORY_TRAVEL         => 'گردشگری',
            self::CATEGORY_AUTOMOTIVE     => 'خودرو',
            self::CATEGORY_INDUSTRIAL     => 'صنعتی',
            self::CATEGORY_CONSTRUCTION   => 'ساختمان',
            self::CATEGORY_FASHION        => 'مد و فشن',
            self::CATEGORY_ELECTRONICS    => 'الکترونیک',
            self::CATEGORY_SINGLE_PRODUCT => 'محصول واحد',
            self::CATEGORY_BUSINESS       => 'کسب‌وکار',
            self::CATEGORY_SERVICES       => 'خدمات',
        ][$category] ?? $category;
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail',
        'preview_image',
        'type',
        'category',
        'source',
        'site_id',
        'header_blocks',
        'footer_blocks',
        'header_variants',
        'footer_variants',
        'pages',
        'global_styles',
        'settings',
        'status',
        'published_at',
        'is_marketplace',
        'marketplace_price',
        'license_type',
        'marketplace_badge',
        'installs_count',
    ];

    protected $casts = [
        'header_blocks'    => 'array',
        'footer_blocks'    => 'array',
        'header_variants'  => 'array',
        'footer_variants'  => 'array',
        'pages'            => 'array',
        'global_styles'    => 'array',
        'settings'         => 'array',
        'published_at'     => 'datetime',
        'is_marketplace'   => 'boolean',
        'marketplace_price'=> 'integer',
        'installs_count'   => 'integer',
    ];

    /**
     * Phase 2b root-cause fix: these JSON columns are nullable at the DB
     * level (no DEFAULT), so any authoring/seed path that simply omits one
     * of them — as AcoolHvacSeedCommand did for `settings` — silently
     * produced NULL instead of an empty array, which
     * ThemeCommercialQualityGate's StructuralIntegrityCheck correctly
     * treats as invalid shape. Rather than patch each authoring path
     * defensively, the canonical Theme definition itself now guarantees
     * every new instance starts with a real (empty) array before any
     * mass-assignment runs — the defect class is closed here once, for
     * every current and future creator (seed commands, ThemeBuilderService,
     * the AI generator, manual admin creation, imports).
     */
    protected $attributes = [
        'header_blocks' => '[]',
        'footer_blocks' => '[]',
        'header_variants' => '[]',
        'footer_variants' => '[]',
        'pages' => '[]',
        'global_styles' => '[]',
        'settings' => '[]',
    ];

    public const TYPE_FULL = 'full';
    public const TYPE_HEADER = 'header';
    public const TYPE_FOOTER = 'footer';
    public const TYPE_LAYOUT = 'layout';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function revisions()
    {
        return $this->hasMany(ThemeRevision::class)->orderByDesc('version');
    }

    /**
     * Append-only commercial release ledger (Phase 1a). Deliberately not
     * exposed via a `publish()`-style mutator here — see ThemeReleaseService.
     */
    public function releases()
    {
        return $this->hasMany(ThemeRelease::class)->orderByDesc('id');
    }

    /**
     * The current authoritative release, if any. `current_release_id` is
     * intentionally excluded from $fillable — it must only ever be moved by
     * ThemeReleaseService, never by generic mass-assignment.
     */
    public function currentRelease()
    {
        return $this->belongsTo(ThemeRelease::class, 'current_release_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeForSite($query, ?int $siteId)
    {
        if ($siteId === null) {
            return $query->whereNull('site_id');
        }
        return $query->where(function ($q) use ($siteId) {
            $q->whereNull('site_id')->orWhere('site_id', $siteId);
        });
    }

    public function scopeTemplates($query)
    {
        return $query->whereNull('site_id');
    }

    /**
     * Authoring/workflow state only (Phase 2d, ADR-025) — whether this
     * Theme's content is in the "published" editing state in the Theme
     * Builder. Deliberately NOT a commercial-eligibility check: a Theme can
     * be status=published and still have no valid current release (e.g. a
     * canonical seed command sets status at creation time, before any
     * release ever exists). Use isCommerciallyEligible() for every decision
     * about whether a Theme may be served, purchased, licensed, downloaded,
     * or assigned to a customer site.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Phase 2d (ADR-025) — the one authoritative commercial-eligibility rule.
     * Theme.status is never consulted here: it is authoring/workflow
     * metadata, not a commercial authority (see isPublished() above). A
     * Theme is commercially eligible only when all of the following hold:
     *
     *   1. current_release_id is set (a release has actually been chosen as
     *      authoritative — not merely that one exists somewhere in history).
     *   2. The referenced ThemeRelease genuinely belongs to this Theme
     *      (defends against a corrupted/mismatched pointer — under normal
     *      application flow current_release_id is only ever set by
     *      ThemeReleaseService from $theme->releases(), so this can only
     *      fail on a data-integrity defect, never in ordinary operation).
     *   3. That release's own status is "published" (not superseded/
     *      deprecated — rollback() is the only way a non-current release
     *      becomes current again, and it always flips status back to
     *      published when it does).
     *   4. A Commercial Quality Gate evaluation that PASSED is linked to
     *      that exact release (theme_quality_reports.theme_release_id —
     *      populated only by ThemeCommercialQualityGate::publishIfCompliant()
     *      on a passing evaluation, never by a failing one, never by mere
     *      release creation).
     *
     * Every consumer that decides whether a Theme may be resolved for a
     * site, listed as commercially ready, purchased, newly licensed, or
     * downloaded/installed must call this method — never re-derive the
     * condition ad hoc (see ADR-025).
     */
    public function isCommerciallyEligible(): bool
    {
        return empty($this->commercialIneligibilityReasons());
    }

    /**
     * @return string[] Empty when isCommerciallyEligible() is true. Reason
     *   codes only (no data or user-facing copy) — callers translate these
     *   into whatever error/log shape they need. Never fabricates a partial
     *   pass: the first structural failure short-circuits the rest, since
     *   later checks (e.g. "does a passing report exist") are meaningless
     *   without a valid release to check it against.
     */
    public function commercialIneligibilityReasons(): array
    {
        if ($this->current_release_id === null) {
            return ['no_current_release'];
        }

        $release = $this->currentRelease;
        if (!$release || $release->theme_id !== $this->id) {
            return ['release_theme_mismatch'];
        }

        if ($release->status !== ThemeRelease::STATUS_PUBLISHED) {
            return ['release_not_published'];
        }

        $hasPassingReport = $release->qualityReports()
            ->where('status', ThemeQualityReport::STATUS_PASSED)
            ->exists();

        return $hasPassingReport ? [] : ['no_passing_quality_report'];
    }

    // ─── Page Management ────────────────────────────────────────────────────

    public function getPageList(): array
    {
        $pages = $this->pages ?? [];
        if (empty($pages)) {
            return [
                'home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []],
            ];
        }
        return $pages;
    }

    public function getPageBlocks(string $key): array
    {
        return ($this->pages ?? [])[$key]['blocks'] ?? [];
    }

    public function setPageBlocks(string $key, array $blocks): void
    {
        $pages = $this->pages ?? [];
        if (!isset($pages[$key])) {
            $pages[$key] = ['title' => $key, 'slug' => $key, 'blocks' => []];
        }
        $pages[$key]['blocks'] = $blocks;
        $this->update(['pages' => $pages]);
    }

    public function addPage(string $key, string $title, string $slug = ''): void
    {
        $pages = $this->pages ?? [];
        $pages[$key] = ['title' => $title, 'slug' => $slug ?: $key, 'blocks' => []];
        $this->update(['pages' => $pages]);
    }

    public function removePage(string $key): void
    {
        $pages = $this->pages ?? [];
        unset($pages[$key]);
        $this->update(['pages' => $pages]);
    }

    public function scopeMarketplace($query)
    {
        return $query->where('is_marketplace', true)->published();
    }

    public function marketplaceMeta()
    {
        return $this->hasOne(ThemeMarketplace::class);
    }

    public function licenses()
    {
        return $this->hasMany(ThemeLicense::class);
    }

    public function hasLicenseForSite(int $siteId): bool
    {
        return $this->licenses()->where('site_id', $siteId)->exists();
    }

    /**
     * Phase 1i — the canonical ledger-aware aggregate. Reuses
     * ThemeLicense::isOutdatedByLedger()/isOutdated() per license rather
     * than re-implementing the comparison here, so there is exactly one
     * place (ThemeLicense) that ever decides what "outdated" means for a
     * single license — this method only aggregates that decision.
     *
     * Phase 2b: the legacy Theme.version fallback this method used to fall
     * back to (isOutdated()) was removed — ThemeRelease/installed_release_id
     * is now the sole commercial version authority (no active-customer
     * compatibility reason remains; see DECISIONS.md ADR amendment). A
     * license isOutdatedByLedger() can't yet answer for (no
     * installed_release_id, or the theme has no current release) is never
     * fabricated as outdated — it is simply not counted, same as before.
     *
     * Naturally scoped to this theme only — licenses() is a hasMany keyed on
     * theme_id, so another theme's licenses can never affect this count.
     */
    public function outdatedLicensesCountByLedger(): int
    {
        return $this->licenses()
            ->get()
            ->filter(fn (ThemeLicense $license) => $license->isOutdatedByLedger() === true)
            ->count();
    }

    /**
     * THEME-03 (Full Product Commercial Audit, TASK 2A): the canonical,
     * Theme-owned list of selectable Header variants. A Theme authored
     * before multi-variant support existed (or one that simply never
     * needed more than one) has no `header_variants` row — this
     * synthesizes its existing single `header_blocks` into one implicit
     * "default" variant with a stable key, so every Theme (old or new)
     * always has at least one real, selectable variant. Never fabricates
     * content: the synthesized variant's blocks are the Theme's own real,
     * already-authored header, not placeholder data.
     *
     * @return list<array{key:string,label:string,blocks:array,is_default:bool}>
     */
    public function getHeaderVariants(): array
    {
        $variants = $this->header_variants ?? [];
        if (!empty($variants)) {
            return $variants;
        }

        return [[
            'key' => 'default',
            'label' => 'پیش‌فرض',
            'blocks' => $this->header_blocks ?? [],
            'is_default' => true,
        ]];
    }

    /** @see getHeaderVariants() — identical contract, for footers. */
    public function getFooterVariants(): array
    {
        $variants = $this->footer_variants ?? [];
        if (!empty($variants)) {
            return $variants;
        }

        return [[
            'key' => 'default',
            'label' => 'پیش‌فرض',
            'blocks' => $this->footer_blocks ?? [],
            'is_default' => true,
        ]];
    }

    /** Get payload for front-end (header + footer + global_styles + metadata). */
    public function getPayload(): array
    {
        $settings = $this->settings ?? [];
        $metaFromSettings = $settings['meta'] ?? [];

        $meta = array_merge([
            'id' => $this->slug,
            'name' => $this->name,
            // Semantic versioning string; falls back to 1.0.0 if not set.
            'version' => $metaFromSettings['version']
                ?? ($settings['version'] ?? '1.0.0'),
            // Release date in Gregorian; callers should format to Jalali for display.
            'release_date' => $metaFromSettings['release_date']
                ?? ($settings['release_date'] ?? ($this->published_at?->toDateString())),
            // Map of version => list of changes (or a flat list for current version).
            'changelog' => $metaFromSettings['changelog']
                ?? ($settings['changelog'] ?? []),
            'compatibility_notes' => $metaFromSettings['compatibility_notes']
                ?? ($settings['compatibility_notes'] ?? null),
            'required_platform_version' => $metaFromSettings['required_platform_version']
                ?? ($settings['required_platform_version'] ?? '1.0.0'),
            // List of module slugs this theme is designed to work well with.
            'supported_modules' => $metaFromSettings['supported_modules']
                ?? ($settings['supported_modules'] ?? []),
        ], $metaFromSettings);

        return [
            'header_blocks' => $this->header_blocks ?? [],
            'footer_blocks' => $this->footer_blocks ?? [],
            'header_variants' => $this->getHeaderVariants(),
            'footer_variants' => $this->getFooterVariants(),
            'global_styles' => $this->global_styles ?? [],
            'settings' => $settings,
            'meta' => $meta,
        ];
    }
}
