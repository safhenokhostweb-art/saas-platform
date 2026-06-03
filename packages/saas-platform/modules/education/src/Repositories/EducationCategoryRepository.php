<?php

namespace SaaSPlatform\Modules\Education\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationCategoryRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationCategory;

class EducationCategoryRepository implements EducationCategoryRepositoryInterface
{
    public function all(?int $siteId = null): Collection
    {
        return EducationCategory::active()
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('children')
            ->orderBy('sort_order')
            ->get();
    }

    public function roots(?int $siteId = null): Collection
    {
        return EducationCategory::active()
            ->whereNull('parent_id')
            ->when($siteId !== null, fn($q) => $q->forSite($siteId))
            ->with('children.articles')
            ->orderBy('sort_order')
            ->get();
    }

    public function find(int $id): ?EducationCategory
    {
        return EducationCategory::find($id);
    }

    public function create(array $data): EducationCategory
    {
        return EducationCategory::create($data);
    }

    public function update(EducationCategory $category, array $data): EducationCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(EducationCategory $category): void
    {
        $category->delete();
    }
}
