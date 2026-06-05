<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;

class EducationFeatureUsageRepository implements EducationFeatureUsageRepositoryInterface
{
    public function track(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): EducationFeatureUsage
    {
        // F-06: Atomic UPSERT — prevents lost-update race condition between concurrent requests.
        // edu_usage_unique index on (user_id, module_slug, feature_slug, site_id) guarantees
        // ON DUPLICATE KEY triggers correctly even under parallel writes.
        $now = now()->toDateTimeString();

        DB::statement(
            "INSERT INTO edu_feature_usage
                (user_id, site_id, module_slug, feature_slug, usage_count, first_used_at, last_used_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                usage_count  = usage_count + 1,
                last_used_at = VALUES(last_used_at),
                updated_at   = VALUES(updated_at)",
            [$userId, $siteId, $moduleSlug, $featureSlug, $now, $now, $now, $now]
        );

        return EducationFeatureUsage::where([
            'user_id'      => $userId,
            'site_id'      => $siteId,
            'module_slug'  => $moduleSlug,
            'feature_slug' => $featureSlug,
        ])->first();
    }

    public function forUser(int $userId, ?int $siteId): Collection
    {
        return EducationFeatureUsage::forUser($userId)->forSite($siteId)->get();
    }

    public function neverUsedFeatures(int $userId, ?int $siteId): Collection
    {
        // C-01: SQL NOT EXISTS replaces full PHP-memory load+filter for large feature catalogs.
        // C-05: Scoped to $userId — "never used by this user", not "never used by anyone".
        return EducationFeature::forSite($siteId)
            ->whereNotExists(function ($query) use ($userId) {
                $query->from('edu_feature_usage')
                    ->whereColumn('edu_feature_usage.module_slug',  'edu_features.module_slug')
                    ->whereColumn('edu_feature_usage.feature_slug', 'edu_features.feature_slug')
                    ->where('edu_feature_usage.user_id', $userId);
            })
            ->get();
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
        // C-02: SQL NOT EXISTS replaces the two-query PHP-memory load+filter.
        // Returns features that have ZERO usage records across all users for this site.
        return EducationFeature::forSite($siteId)
            ->whereNotExists(function ($query) {
                $query->from('edu_feature_usage')
                    ->whereColumn('edu_feature_usage.module_slug',  'edu_features.module_slug')
                    ->whereColumn('edu_feature_usage.feature_slug', 'edu_features.feature_slug');
            })
            ->get();
    }
}
