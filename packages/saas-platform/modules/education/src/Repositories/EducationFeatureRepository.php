<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\DataTransferObjects\FeatureDto;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

class EducationFeatureRepository implements EducationFeatureRepositoryInterface
{
    public function all(?int $siteId = null): Collection
    {
        return EducationFeature::forSite($siteId)->orderBy('module_slug')->orderBy('feature_slug')->get();
    }

    public function findBySlug(string $moduleSlug, string $featureSlug, ?int $siteId): ?EducationFeature
    {
        return EducationFeature::where('module_slug', $moduleSlug)
            ->where('feature_slug', $featureSlug)
            ->where('site_id', $siteId)
            ->first();
    }

    public function findByRoute(string $routeName, ?int $siteId): ?EducationFeature
    {
        return EducationFeature::where('route_name', $routeName)
            ->where('site_id', $siteId)
            ->first();
    }

    public function upsert(FeatureDto $dto, ?int $siteId = null): EducationFeature
    {
        $data = $dto->toArray();

        return EducationFeature::updateOrCreate(
            [
                'module_slug'  => $dto->moduleSlug,
                'feature_slug' => $dto->featureSlug,
                'site_id'      => $siteId,
            ],
            array_merge($data, [
                'site_id'       => $siteId,
                'discovered_at' => now(),
            ])
        );
    }

    public function upsertMany(array $dtos, ?int $siteId = null): int
    {
        $count = 0;
        foreach ($dtos as $dto) {
            $this->upsert($dto, $siteId);
            $count++;
        }
        return $count;
    }

    public function countByModule(?int $siteId = null): array
    {
        return EducationFeature::forSite($siteId)
            ->select('module_slug', DB::raw('COUNT(*) as total'))
            ->groupBy('module_slug')
            ->pluck('total', 'module_slug')
            ->toArray();
    }

    public function allRouteNames(?int $siteId = null): array
    {
        return EducationFeature::forSite($siteId)
            ->whereNotNull('route_name')
            ->pluck('route_name')
            ->toArray();
    }
}
