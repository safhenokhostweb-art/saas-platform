<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\DataTransferObjects\FeatureDto;
use SaaSPlatform\Modules\Education\Models\EducationFeature;

interface EducationFeatureRepositoryInterface
{
    public function all(?int $siteId = null): Collection;
    public function findBySlug(string $moduleSlug, string $featureSlug, ?int $siteId): ?EducationFeature;
    public function findByRoute(string $routeName, ?int $siteId): ?EducationFeature;
    public function upsert(FeatureDto $dto, ?int $siteId = null): EducationFeature;
    public function upsertMany(array $dtos, ?int $siteId = null): int;
    public function countByModule(?int $siteId = null): array;
    public function allRouteNames(?int $siteId = null): array;
}
