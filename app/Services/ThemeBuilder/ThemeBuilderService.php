<?php
namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeRevision;
use App\Models\Site;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
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

    public function saveRevision(Theme $theme, ?string $label = null, ?int $userId = null): ThemeRevision
    {
        $version = ($theme->revisions()->max('version') ?? 0) + 1;
        $snapshot = [
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

    public function restoreRevision(ThemeRevision $revision): Theme
    {
        $theme = $revision->theme;
        $snapshot = $revision->snapshot;
        $theme->update([
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

    public function cloneTheme(Theme $theme, string $newName, ?int $siteId = null): Theme
    {
        $data = [
            'name' => $newName,
            'slug' => Str::slug($newName) . '-' . ($siteId ?? 'copy') . '-' . uniqid(),
            'description' => $theme->description,
            'thumbnail' => $theme->thumbnail,
            'type' => $theme->type,
            'site_id' => $siteId,
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

    public function exportTheme(Theme $theme): array
    {
        return [
            'version' => 1,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'type' => $theme->type,
            'header_blocks' => $theme->header_blocks,
            'footer_blocks' => $theme->footer_blocks,
            'global_styles' => $theme->global_styles,
            'settings' => $theme->settings,
            'exported_at' => now()->toIso8601String(),
        ];
    }

    public function importTheme(array $data, ?int $siteId = null): Theme
    {
        $data['name'] = $data['name'] ?? 'تم وارد شده';
        $data['slug'] = ($data['slug'] ?? Str::slug($data['name'])) . '-' . uniqid();
        $data['site_id'] = $siteId;
        $data['header_blocks'] = $data['header_blocks'] ?? [];
        $data['footer_blocks'] = $data['footer_blocks'] ?? [];
        $data['global_styles'] = array_merge($this->defaultGlobalStyles(), $data['global_styles'] ?? []);
        $data['settings'] = $data['settings'] ?? [];
        return Theme::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Theme::TYPE_FULL,
            'site_id' => $siteId,
            'header_blocks' => $data['header_blocks'],
            'footer_blocks' => $data['footer_blocks'],
            'global_styles' => $data['global_styles'],
            'settings' => $data['settings'],
            'status' => Theme::STATUS_DRAFT,
        ]);
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
