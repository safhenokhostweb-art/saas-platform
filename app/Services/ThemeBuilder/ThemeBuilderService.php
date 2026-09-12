<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRevision;
use App\Models\Site;
use App\Services\ThemeBuilder\Extension\ExtensionCanvasPaletteAdapter;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemeBuilderService
{
    public function __construct(
        protected ThemeReleaseService $releaseService,
        protected ThemeCommercialQualityGate $qualityGate
    ) {
    }

    public function createTemplate(array $data): Theme
    {
        $data['site_id'] = null;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['type'] = $data['type'] ?? Theme::TYPE_FULL;
        $data['status'] = Theme::STATUS_DRAFT;
        $data['header_blocks'] = $data['header_blocks'] ?? [];
        $data['footer_blocks'] = $data['footer_blocks'] ?? [];
        $data['global_styles'] = $data['global_styles'] ?? $this->defaultGlobalStyles();
        $data['settings'] = $data['settings'] ?? [];
        return Theme::create($data);
    }

    public function createForSite(Site $site, array $data): Theme
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . $site->id;
        $data['type'] = $data['type'] ?? Theme::TYPE_FULL;
        $data['status'] = Theme::STATUS_DRAFT;
        $data['header_blocks'] = $data['header_blocks'] ?? [];
        $data['footer_blocks'] = $data['footer_blocks'] ?? [];
        $data['global_styles'] = $data['global_styles'] ?? $this->defaultGlobalStyles();
        $data['settings'] = $data['settings'] ?? [];
        return Theme::create($data);
    }

    public function updateTheme(Theme $theme, array $data): Theme
    {
        $theme->update(array_filter([
            'name' => $data['name'] ?? $theme->name,
            'slug' => $data['slug'] ?? $theme->slug,
            'description' => $data['description'] ?? $theme->description,
            'thumbnail' => $data['thumbnail'] ?? $theme->thumbnail,
            'header_blocks' => $data['header_blocks'] ?? $theme->header_blocks,
            'footer_blocks' => $data['footer_blocks'] ?? $theme->footer_blocks,
            'global_styles' => $data['global_styles'] ?? $theme->global_styles,
            'settings' => $data['settings'] ?? $theme->settings,
        ], fn ($v) => $v !== null));
        return $theme->fresh();
    }

    /**
     * Patch only Canvas-owned global-style paths into the existing payload.
     *
     * Theme.global_styles remains the single persistence contract. Starting
     * from its current value is intentional: raw-editor, plugin, legacy and
     * future keys that the visual panel does not own must survive unchanged.
     *
     * @param array<string, mixed> $managedPatch Dot-notated managed paths.
     */
    public function patchGlobalStyles(Theme $theme, array $managedPatch): Theme
    {
        $globalStyles = $theme->global_styles ?? [];

        foreach ($managedPatch as $path => $value) {
            Arr::set($globalStyles, $path, $value);
        }

        $theme->update(['global_styles' => $globalStyles]);

        return $theme->fresh();
    }

    /**
     * UI-C2D — the master draft checkpoint. Snapshot must contain every
     * mutable master-authoring field the canonical Canvas can change, so a
     * restore can reconstruct the draft exactly: `pages` was missing here
     * (audited, confirmed against ThemeCanvasController::save()/addPage()/
     * removePage(), the actual live write paths) even though ThemeRelease's
     * own snapshot (ADR-021) already includes it — this closes that gap.
     * `settings` already carries `extension_selections` (ADR-047) as one of
     * its keys, so capturing it whole also checkpoints Extension selections
     * without a second, parallel field.
     */
    public function saveRevision(Theme $theme, ?string $label = null, ?int $userId = null): ThemeRevision
    {
        $version = ($theme->revisions()->max('version') ?? 0) + 1;
        $snapshot = [
            'pages' => $theme->pages ?? $theme->getPageList(),
            'header_blocks' => $theme->header_blocks,
            'footer_blocks' => $theme->footer_blocks,
            'global_styles' => $theme->global_styles,
            'settings' => $theme->settings,
        ];
        return $theme->revisions()->create([
            'version' => $version,
            'label' => $label ?? 'نسخه ' . $version,
            'snapshot' => $snapshot,
            'user_id' => $userId,
        ]);
    }

    /**
     * Restore is content-only, exactly like ThemeRelease's own
     * restoreToDraft() (ADR-021 §4): it never touches Theme identity
     * (name/slug/status), never creates or mutates a ThemeRelease, and
     * customer-site's ThemeInstanceRevision is architecturally unreachable
     * from here (a different app, a different database).
     */
    public function restoreRevision(ThemeRevision $revision): Theme
    {
        $theme = $revision->theme;
        $snapshot = $revision->snapshot;
        $theme->update([
            'pages' => $snapshot['pages'] ?? [],
            'header_blocks' => $snapshot['header_blocks'] ?? [],
            'footer_blocks' => $snapshot['footer_blocks'] ?? [],
            'global_styles' => $snapshot['global_styles'] ?? [],
            'settings' => $snapshot['settings'] ?? [],
        ]);
        return $theme->fresh();
    }

    /**
     * The real, single commercial publish entry point (called by
     * ThemeBuilderController::publish()). Phase 2c — Mandatory Commercial
     * Theme Publish Gate: a Theme may become a commercial ThemeRelease only
     * after ThemeCommercialQualityGate has evaluated it and found no
     * blocking failure. publishIfCompliant() is the sole caller of
     * ThemeReleaseService::release() reachable from here — it persists the
     * evaluation report unconditionally (pass or fail, an audit fact either
     * way, see ThemeCommercialQualityGate::evaluate()), and only creates the
     * ThemeRelease — inside its own transaction — if the gate passed. If it
     * throws ThemeQualityGateFailedException, execution never reaches the
     * status/published_at update below: no ThemeRelease, no
     * current_release_id change, and no "published" status can result from a
     * blocked commercial publish. The caller (ThemeBuilderController)
     * decides how to present that failure; this service holds no quality
     * logic of its own, only orchestration.
     *
     * Phase 2b: the legacy Theme.version int bump that used to happen here
     * was removed — ThemeRelease.version is the sole commercial version
     * authority now (no active-customer compatibility reason remains).
     */
    public function publish(
        Theme $theme,
        ?string $version = null,
        ?string $changelog = null,
        ?string $releaseNotes = null,
        ?int $publishedBy = null
    ): Theme {
        $this->qualityGate->publishIfCompliant(
            $theme,
            $this->releaseService,
            $version ?? $this->releaseService->nextPatchVersion($theme),
            $changelog ?? 'Published via Theme Builder.',
            $releaseNotes,
            null, // compatibility — not populated in this phase
            null, // dependencies — reserved for the dependency phase, per THEME_PRODUCTIZATION_ARCHITECTURE.md
            $publishedBy
        );

        $theme->update([
            'status' => Theme::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return $theme->fresh();
    }

    public function unpublish(Theme $theme): Theme
    {
        $theme->update(['status' => Theme::STATUS_DRAFT]);
        return $theme->fresh();
    }

    /**
     * Resolve active theme for a site: site's assigned theme or first
     * commercially eligible platform template.
     *
     * Phase 2d (ADR-025): status=published alone no longer makes a theme
     * servable — Theme::isCommerciallyEligible() (valid current release,
     * belonging to this theme, itself published, backed by a passing
     * Commercial Quality Report) is the sole authority here. A site
     * explicitly assigned to a theme (site.theme_id) that is not
     * commercially eligible falls through to the platform-template fallback
     * below rather than serving an unapproved theme — fail-closed, not a
     * 500 or a broken render.
     */
    public function resolveForSite(?int $siteId): ?Theme
    {
        if ($siteId) {
            $site = Site::find($siteId);
            if ($site && $site->theme_id) {
                $theme = Theme::find($site->theme_id);
                if ($theme && $theme->isCommerciallyEligible()) {
                    return $theme;
                }
            }
        }
        return Theme::templates()->get()->first(fn (Theme $t) => $t->isCommerciallyEligible());
    }

    /**
     * Payload for front-end (site-config API): header, footer, global_styles.
     */
    public function payloadForSite(?int $siteId): array
    {
        $theme = $this->resolveForSite($siteId);
        if (!$theme) {
            return [
                'header_blocks' => [],
                'footer_blocks' => [],
                'global_styles' => $this->defaultGlobalStyles(),
                'settings' => [],
                'meta' => [
                    // Fallback metadata when no explicit theme is assigned.
                    'id' => 'default',
                    'name' => 'تم پیش‌فرض',
                    'version' => '1.0.0',
                    'release_date' => null,
                    'changelog' => [],
                    'compatibility_notes' => null,
                    'required_platform_version' => '1.0.0',
                    'supported_modules' => [],
                ],
            ];
        }
        return $theme->getPayload();
    }

    /**
     * UI-C2E (clone) — preserves master-authoring structure only: pages,
     * header, footer, global styles, and `settings` (which carries
     * `extension_selections` — ADR-047 — verbatim, so an allowed Extension
     * dependency selection clones with it). Never copies immutable release
     * identity/provenance (`current_release_id`/`status` reset to draft),
     * customer/site revision state (architecturally unreachable — a
     * different app/database), or any commercial ownership state (there is
     * none on `Theme` to copy). Identity is always new: a fresh slug, never
     * the source theme's.
     */
    public function cloneTheme(Theme $theme, string $newName, ?int $siteId = null): Theme
    {
        $data = [
            'name' => $newName,
            'slug' => Str::slug($newName) . '-' . ($siteId ?? 'copy') . '-' . uniqid(),
            'description' => $theme->description,
            'thumbnail' => $theme->thumbnail,
            'type' => $theme->type,
            'site_id' => $siteId,
            'pages' => $theme->pages ?? $theme->getPageList(),
            'header_blocks' => $theme->header_blocks,
            'footer_blocks' => $theme->footer_blocks,
            'global_styles' => $theme->global_styles,
            'settings' => $theme->settings,
            'status' => Theme::STATUS_DRAFT,
        ];
        return $theme->site_id === null
            ? $this->createTemplate($data)
            : $this->createForSite($theme->site, $data);
    }

    /**
     * UI-C2E (export) — the one canonical portable master-authoring format;
     * `importTheme()` below is its only reader. `version` is a schema/
     * compatibility marker for that reader, distinct from and never
     * confused with `ThemeRelease.version` (commercial semver) or the
     * retired legacy `Theme.version` (ADR-023). Bumped to 2 for this phase
     * (added `pages`) — `importTheme()` still accepts a version-1 file
     * (pre-existing exports with no `pages` key) by defaulting it to `[]`,
     * exactly like it already does for `header_blocks`/`footer_blocks`.
     *
     * Deliberately excludes: `site_id`, `thumbnail` (a local file path, not
     * portable), immutable `ThemeRelease` identity/provenance, and any
     * customer/site data — none of which this method ever reads in the
     * first place, so there is nothing to filter out at the point of export.
     */
    public function exportTheme(Theme $theme): array
    {
        return [
            'version' => 2,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'type' => $theme->type,
            'pages' => $theme->pages ?? $theme->getPageList(),
            'header_blocks' => $theme->header_blocks,
            'footer_blocks' => $theme->footer_blocks,
            'global_styles' => $theme->global_styles,
            'settings' => $theme->settings,
            'exported_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<int, string> supported `exportTheme()` schema versions. */
    public function supportedImportVersions(): array
    {
        return [1, 2];
    }

    /**
     * UI-C2E (import) — the sole consumer of `exportTheme()`'s format.
     * Fails closed before writing anything: an unsupported/invalid
     * `version`, a malformed `pages` shape, an unknown (non-canonical,
     * non-`ext:`) block type, or an Extension selection that does not
     * validate against `ExtensionCanvasPaletteAdapter`'s existing ownership
     * rules all reject the whole import with no `Theme` row created —
     * never a partially-imported theme. Reuses `BlockRegistry::all()` (the
     * one canonical type registry) and `ExtensionCanvasPaletteAdapter`'s
     * existing selection validator; neither is duplicated here.
     *
     * @throws \InvalidArgumentException on any validation failure.
     */
    public function importTheme(array $data, ?int $siteId = null): Theme
    {
        $version = $data['version'] ?? 1;
        if (!in_array($version, $this->supportedImportVersions(), true)) {
            throw new \InvalidArgumentException(
                'نسخه فایل خروجی پشتیبانی نمی‌شود: ' . (is_scalar($version) ? $version : 'نامعتبر')
            );
        }

        $pages = $data['pages'] ?? [];
        if (!is_array($pages)) {
            throw new \InvalidArgumentException('ساختار صفحات (pages) در فایل نامعتبر است.');
        }
        foreach ($pages as $key => $page) {
            if (!is_array($page) || !is_array($page['blocks'] ?? [])) {
                throw new \InvalidArgumentException("ساختار صفحه «{$key}» نامعتبر است.");
            }
        }

        $headerBlocks = $data['header_blocks'] ?? [];
        $footerBlocks = $data['footer_blocks'] ?? [];
        if (!is_array($headerBlocks) || !is_array($footerBlocks)) {
            throw new \InvalidArgumentException('ساختار بلوک‌های هدر/فوتر نامعتبر است.');
        }

        $selections = $data['settings']['extension_selections'] ?? [];
        if (!is_array($selections)) {
            throw new \InvalidArgumentException('ساختار انتخاب افزونه‌ها نامعتبر است.');
        }

        $unknown = $this->unknownBlockTypes($headerBlocks, $footerBlocks, $pages);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'نوع بلوک(های) زیر در رجیستری قالب‌ساز شناخته نمی‌شوند: ' . implode(', ', $unknown)
            );
        }

        $extValidation = ExtensionCanvasPaletteAdapter::validateBlocksAgainstSelections(
            array_merge($headerBlocks, $footerBlocks, ...array_map(fn ($p) => $p['blocks'] ?? [], array_values($pages))),
            $selections,
            [] // a fresh import has no prior instances — every ext:* block is a new authoring event
        );
        if (!$extValidation['ok']) {
            throw new \InvalidArgumentException($extValidation['error']);
        }

        $name = $data['name'] ?? 'تم وارد شده';
        $slug = ($data['slug'] ?? Str::slug($name)) . '-' . uniqid();
        $globalStyles = array_merge($this->defaultGlobalStyles(), $data['global_styles'] ?? []);

        return DB::transaction(fn () => Theme::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Theme::TYPE_FULL,
            'site_id' => $siteId,
            'pages' => $pages,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'global_styles' => $globalStyles,
            'settings' => $data['settings'] ?? [],
            'status' => Theme::STATUS_DRAFT,
        ]));
    }

    /**
     * @return array<int, string> block types used in $headerBlocks/$footerBlocks/$pages
     *   that are neither a canonical `BlockRegistry` type nor an `ext:*`
     *   Extension type (those are validated separately, for real ownership,
     *   not merely a naming-convention check).
     */
    private function unknownBlockTypes(array $headerBlocks, array $footerBlocks, array $pages): array
    {
        $registry = BlockRegistry::all();
        $unknown = [];

        $walk = function (array $blocks) use (&$walk, &$unknown, $registry): void {
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $type = $block['type'] ?? null;
                if (is_string($type) && $type !== '' && !isset($registry[$type]) && !str_starts_with($type, 'ext:')) {
                    $unknown[$type] = true;
                }
                $props = $block['props'] ?? $block['data'] ?? [];
                $props = is_array($props) ? $props : [];
                $children = $block['children'] ?? $block['blocks'] ?? ($props['blocks'] ?? []);
                if (is_array($children)) {
                    $walk($children);
                }
                foreach ((is_array($props['tabs'] ?? null) ? $props['tabs'] : []) as $tab) {
                    $walk(is_array($tab['blocks'] ?? null) ? $tab['blocks'] : []);
                }
                foreach ((is_array($props['items'] ?? null) ? $props['items'] : []) as $item) {
                    $walk(is_array($item['blocks'] ?? null) ? $item['blocks'] : []);
                }
                if (is_array($props['slides'] ?? null)) {
                    $walk($props['slides']);
                }
            }
        };

        $walk($headerBlocks);
        $walk($footerBlocks);
        foreach ($pages as $page) {
            $walk(is_array($page['blocks'] ?? null) ? $page['blocks'] : []);
        }

        return array_keys($unknown);
    }

    public function defaultGlobalStyles(): array
    {
        return [
            'colors' => [
                'primary' => '#4f46e5',
                'secondary' => '#6366f1',
                'accent' => '#818cf8',
                'background' => '#ffffff',
                'surface' => '#f9fafb',
                'text' => '#111827',
                'text_muted' => '#6b7280',
            ],
            'typography' => [
                'font_heading' => 'Vazirmatn, Tahoma, sans-serif',
                'font_body' => 'Vazirmatn, Tahoma, sans-serif',
                'scale' => 1.25,
            ],
            'spacing' => [
                'scale' => 1,
                'unit' => 'rem',
            ],
            'border_radius' => [
                'sm' => '0.25rem',
                'md' => '0.5rem',
                'lg' => '0.75rem',
            ],
            'shadows' => [
                'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
                'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            ],
            'dark_mode' => [
                'enabled' => true,
                'primary' => '#818cf8',
                'background' => '#111827',
                'surface' => '#1f2937',
                'text' => '#f9fafb',
            ],
        ];
    }
}
