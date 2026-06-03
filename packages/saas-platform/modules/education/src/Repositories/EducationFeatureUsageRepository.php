<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;

class EducationFeatureUsageRepository implements EducationFeatureUsageRepositoryInterface
{
    public function track(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): EducationFeatureUsage
    {
        $record = EducationFeatureUsage::firstOrNew([
            'user_id'      => $userId,
            'site_id'      => $siteId,
            'module_slug'  => $moduleSlug,
            'feature_slug' => $featureSlug,
        ]);

        if (! $record->exists) {
            $record->usage_count   = 1;
            $record->first_used_at = now();
        } else {
            $record->usage_count++;
        }

        $record->last_used_at = now();
        $record->save();

        return $record;
    }

    public function forUser(int $userId, ?int $siteId): Collection
    {
        return EducationFeatureUsage::forUser($userId)->forSite($siteId)->get();
    }

    public function neverUsedFeatures(int $userId, ?int $siteId): Collection
    {
        $usedSlugs = EducationFeatureUsage::forUser($userId)
            ->forSite($siteId)
            ->get(['module_slug', 'feature_slug'])
            ->map(fn($r) => "{$r->module_slug}.{$r->feature_slug}");

        return EducationFeature::forSite($siteId)
            ->get()
            ->filter(fn($f) => ! $usedSlugs->contains("{$f->module_slug}.{$f->feature_slug}"))
            ->values();
    }

    public function usageCountForFeature(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): int
    {
        return EducationFeatureUsage::forUser($userId)
            ->forSite($siteId)
            ->forModule($moduleSlug)
            ->where('feature_slug', $featureSlug)
            ->value('usage_count') ?? 0;
    }

    public function adoptionStats(?int $siteId = null): array
    {
        $totalFeatures = EducationFeature::forSite($siteId)->count();
        if ($totalFeatures === 0) {
            return ['total' => 0, 'adopted' => 0, 'adoption_pct' => 100.0];
        }

        $adoptedFeatureSlugs = EducationFeatureUsage::forSite($siteId)
            ->select('module_slug', 'feature_slug')
            ->groupBy('module_slug', 'feature_slug')
            ->get()
            ->map(fn($r) => "{$r->module_slug}.{$r->feature_slug}")
            ->unique()
            ->count();

        return [
            'total'        => $totalFeatures,
            'adopted'      => $adoptedFeatureSlugs,
            'adoption_pct' => round($adoptedFeatureSlugs / $totalFeatures * 100, 1),
        ];
    }

    public function topFeaturesByUsage(?int $siteId = null, int $limit = 10): Collection
    {
        return EducationFeatureUsage::forSite($siteId)
            ->selectRaw('module_slug, feature_slug, SUM(usage_count) as total_usage')
            ->groupBy('module_slug', 'feature_slug')
            ->orderByDesc('total_usage')
            ->limit($limit)
            ->get();
    }

    public function unusedFeatures(?int $siteId = null): Collection
    {
        $usedKeys = EducationFeatureUsage::forSite($siteId)
            ->select('module_slug', 'feature_slug')
            ->groupBy('module_slug', 'feature_slug')
            ->get()
            ->map(fn($r) => "{$r->module_slug}.{$r->feature_slug}")
            ->toArray();

        return EducationFeature::forSite($siteId)
            ->get()
            ->reject(fn($f) => in_array("{$f->module_slug}.{$f->feature_slug}", $usedKeys, true))
            ->values();
    }
}
