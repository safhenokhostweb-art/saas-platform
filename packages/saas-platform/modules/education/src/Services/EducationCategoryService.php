<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationCategoryRepositoryInterface;
use SaaSPlatform\Modules\Education\Models\EducationCategory;

class EducationCategoryService
{
    public function __construct(
        private readonly EducationCategoryRepositoryInterface $categories,
    ) {}

    public function all(?int $siteId = null): Collection
    {
        return $this->categories->all($siteId);
    }

    public function roots(?int $siteId = null): Collection
    {
        return $this->categories->roots($siteId);
    }

    public function find(int $id): ?EducationCategory
    {
        return $this->categories->find($id);
    }

    public function create(array $data): EducationCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        }
        return $this->categories->create($data);
    }

    public function update(EducationCategory $category, array $data): EducationCategory
    {
        return $this->categories->update($category, $data);
    }

    public function delete(EducationCategory $category): void
    {
        $this->categories->delete($category);
    }
}
