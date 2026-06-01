<?php

namespace App\Services\ThemeEngine;

use App\Models\ThemeProfile;
use App\Models\TenantThemeOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages theme profile lifecycle: activate, clone, edit, delete, reset, preview.
 * Tenant isolation guaranteed: site-level operations never affect other sites.
 */
class ThemeProfileManager
{
    private const PLATFORM_CACHE_KEY = 'theme_engine:platform:active';
    private const TENANT_CACHE_PREFIX = 'theme_engine:tenant:';
    private const CACHE_TTL = 3600;

    public function __construct(private ThemePresetRegistry $presets) {}

    // ── Resolution ───────────────────────────────────────────────────────────

    /**
     * Resolve the active ThemeProfile for a given context.
     * Returns platform active profile if siteId is null or tenant has no override.
     */
    public function resolve(?int $siteId = null): ?ThemeProfile
    {
        if ($siteId !== null) {
            $key = self::TENANT_CACHE_PREFIX . $siteId;
            return Cache::remember($key, self::CACHE_TTL, function () use ($siteId) {
                return TenantThemeOverride::forSite($siteId)
                    ?? $this->resolvePlatform();
            });
        }
        return $this->resolvePlatform();
    }

    public function resolvePlatform(): ?ThemeProfile
    {
        return Cache::remember(self::PLATFORM_CACHE_KEY, self::CACHE_TTL, function () {
            return ThemeProfile::activePlatform()
                ?? ThemeProfile::defaultPreset();
        });
    }

    // ── Activation ───────────────────────────────────────────────────────────

    /**
     * Activate a theme profile as the platform default.
     * Deactivates the previously active profile atomically.
     */
    public function activatePlatform(ThemeProfile $profile): ThemeProfile
    {
        DB::transaction(function () use ($profile) {
            ThemeProfile::where('is_active', true)->update(['is_active' => false]);
            $profile->update(['is_active' => true]);
        });
        $this->clearPlatformCache();
        return $profile->fresh();
    }

    /**
     * Assign a theme profile to a specific tenant site.
     */
    public function activateForTenant(int $siteId, ThemeProfile $profile): void
    {
        TenantThemeOverride::setForSite($siteId, $profile->id);
        $this->clearTenantCache($siteId);
    }

    /**
     * Remove tenant override — tenant reverts to platform theme.
     */
    public function resetTenantToDefault(int $siteId): void
    {
        TenantThemeOverride::removeForSite($siteId);
        $this->clearTenantCache($siteId);
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): ThemeProfile
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_system'] = false;
        return ThemeProfile::create($data);
    }

    public function update(ThemeProfile $profile, array $data): ThemeProfile
    {
        if ($profile->is_system) {
            // System presets: allow color updates but not structural changes
            $allowed = ['name', 'description', 'colors', 'surfaces', 'typography', 'borders', 'sidebar', 'header'];
            $data = array_intersect_key($data, array_flip($allowed));
        }
        $profile->update($data);
        $this->clearRelatedCache($profile);
        return $profile->fresh();
    }

    public function clone(ThemeProfile $source, string $newName): ThemeProfile
    {
        return ThemeProfile::create([
            'name'        => $newName,
            'slug'        => Str::slug($newName) . '-' . Str::random(6),
            'description' => $source->description . ' (کلون)',
            'is_default'  => false,
            'is_system'   => false,
            'is_active'   => false,
            'preset_key'  => null,
            'colors'      => $source->colors,
            'surfaces'    => $source->surfaces,
            'typography'  => $source->typography,
            'borders'     => $source->borders,
            'sidebar'     => $source->sidebar,
            'header'      => $source->header,
        ]);
    }

    /**
     * Delete a custom (non-system) profile.
     * If it was active, falls back to the default Enterprise Blue.
     */
    public function delete(ThemeProfile $profile): void
    {
        if ($profile->is_system) {
            throw new \LogicException('پریست‌های سیستمی قابل حذف نیستند.');
        }
        $wasActive = $profile->is_active;
        $profile->delete();

        if ($wasActive) {
            $default = ThemeProfile::defaultPreset();
            if ($default) {
                $this->activatePlatform($default);
            }
        } else {
            $this->clearPlatformCache();
        }
    }

    /**
     * Reset platform theme to Enterprise Blue.
     */
    public function resetToDefault(): ThemeProfile
    {
        $default = ThemeProfile::defaultPreset();
        if (!$default) {
            throw new \RuntimeException('تم پیش‌فرض Enterprise Blue در دیتابیس یافت نشد.');
        }
        return $this->activatePlatform($default);
    }

    // ── Preview ──────────────────────────────────────────────────────────────

    /**
     * Get preview CSS vars for a profile without activating it.
     * Safe to call from AJAX — no DB writes.
     */
    public function previewTokens(ThemeProfile $profile): array
    {
        return $profile->toCssVars();
    }

    /**
     * Store a preview token set in cache for temporary preview mode.
     * Keyed by session/user — expires after 30 minutes.
     */
    public function storePreview(string $previewKey, ThemeProfile $profile): void
    {
        Cache::put(
            'theme_preview:' . $previewKey,
            $profile->toCssVars(),
            1800
        );
    }

    public function getPreview(string $previewKey): ?array
    {
        return Cache::get('theme_preview:' . $previewKey);
    }

    public function clearPreview(string $previewKey): void
    {
        Cache::forget('theme_preview:' . $previewKey);
    }

    // ── Cache ────────────────────────────────────────────────────────────────

    public function clearPlatformCache(): void
    {
        Cache::forget(self::PLATFORM_CACHE_KEY);
    }

    public function clearTenantCache(int $siteId): void
    {
        Cache::forget(self::TENANT_CACHE_PREFIX . $siteId);
    }

    private function clearRelatedCache(ThemeProfile $profile): void
    {
        if ($profile->is_active) {
            $this->clearPlatformCache();
        }
        $tenantIds = $profile->tenantOverrides()->pluck('site_id');
        foreach ($tenantIds as $siteId) {
            $this->clearTenantCache($siteId);
        }
    }
}
