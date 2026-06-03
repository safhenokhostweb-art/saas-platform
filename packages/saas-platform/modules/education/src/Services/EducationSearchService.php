<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationSearchServiceInterface;
use SaaSPlatform\Modules\Education\Dto\EducationSearchQuery;

class EducationSearchService implements EducationSearchServiceInterface
{
    public function __construct(
        private readonly EducationArticleRepositoryInterface $articles,
    ) {}

    public function search(EducationSearchQuery $query): Collection
    {
        if (strlen(trim($query->term)) < 2) {
            return collect();
        }

        return $this->articles->search(
            $query->term,
            $query->siteId,
            $query->role,
            $query->moduleSlug,
        );
    }

    public function contextual(string $routeName, ?int $siteId, ?string $role): Collection
    {
        return $this->articles->forRoute($routeName, $siteId, $role);
    }
}
